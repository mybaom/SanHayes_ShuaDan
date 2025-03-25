<?php

namespace app\index\pay;

use think\Db;

class Ipf360 extends PayBase
{
    const PAY_URL = 'http://api.ifp360.com/v1/trade';
    const PAYOUT_URL = 'http://payment.ifp360.com/v1/pay';

    //const PAY_URL = 'https://globalpay.pw/api/payTest/order';
    //const PAYOUT_URL = 'https://globalpay.pw/api/payTest/collection';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.ipf360.' . $param);
        }
        return config('pay.ipf360.type.t' . $type . '.' . $param);
    }

    public function get_mch_id()
    {
        return $this->getConfig('mch_id');
    }

    public function get_secret()
    {
        return $this->getConfig('secret');
    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'partner' => $this->get_mch_id(),
            'trade_no' => $op_data['sn'],
            'amount' => $op_data['amount'],
            'open_url' => url('/index/my/index', '', true, true),
            'notify_url' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'name' => 'zhangsan',
            'email' => '888@gmail.com',
            'goods' => 'recharge',
            'phone' => $oUser['tel'],
            'pay_type' => $this->getConfig('pay_type'),
            'wallet_code' => $this->getConfig('wallet_code'),
            'currency' => $this->getConfig('currency'),
            'sign_type' => 'MD5'
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 100000) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['trade_url']];
        }
        return ['respCode' => 'ERROR', 'payInfo' => '', 'resData' => $res, 'postData' => $data];
    }

    /**
     * 验证代收回调
     * @param string $type
     * @return array ['status'=>'SUCCESS',oid=>'订单号',amount=>'金额','data'=>'原始数据 array']
     */
    public function parsePayCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $data = json_decode($put, true);
        if (empty($data)) $data = $_POST;
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['trade_status'] == 'SUCCESS' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['trade_no'],
            'amount' => $data['amount'],
            'data' => $data
        ];
    }

    public function payCallbackSuccess()
    {
        echo 'SUCCESS';
    }

    public function payCallbackFail()
    {
        echo 'error';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'partner' => $this->get_mch_id(),
            'trade_sn' => $oinfo['id'],
            'amount' => $oinfo['num'],
            'wallet_code' => $this->getConfig('payout_wallet_code'),
            'currency' => $this->getConfig('payout_currency'),
            'card' => $blank_info['cardnum'],
            'account_name' => $blank_info['username'],
            'bank_code' => $blank_info['bank_code'],
            'phone' => $blank_info['tel'],
            'email' => 'test@123.com',
            'notify_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),

            'sign_type' => 'MD5'
        ];
        if (config('default_country') == 'INR') {
            $data['other'] = $blank_info['document_id'];
            $data['other_type'] = 'IFSC';
        }
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 100000) {
            return true;
        }
        $this->_payout_msg = !empty($res['message']) ? $res['message'] : 'fail';
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $data = json_decode($put, true);
        if (empty($data)) $data = $_POST;
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        if ($data['trade_status'] == 'WAITING') exit();
        return [
            'status' => ($data['trade_status'] == 'SUCCESS' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['trade_sn'],
            'amount' => $data['amount'],
            'msg' => !empty($data['trade_status']) ? $data['trade_status'] : '',
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "error";
    }

    public function parsePayoutCallbackSuccess()
    {
        echo "SUCCESS";
    }


    /**
     * 创建签名
     * @param $data array  数据包
     * @return string
     */
    private function _make_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if ($value) $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ($value) $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_secret()));
    }
}