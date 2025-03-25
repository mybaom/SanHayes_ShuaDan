<?php

namespace app\index\pay;

use think\Db;

class Threektmxpay extends PayBase
{
    const PAY_URL = 'https://pay.3kbpay.com/mexico/recharge';
    const PAYOUT_URL = 'https://pay.3kbpay.com/api/mexico/withdrawal';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return $this->getConfig('mch_id');
    }

    public function get_secret()
    {
        return $this->getConfig('secret');

    }

    public function get_payout_secret()
    {
        return $this->getConfig('payout_secret');
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.threektmxpay.' . $param);
        }
        return config('pay.threektmxpay.type.t' . $type . '.' . $param);
    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'merchant_id' => $this->get_mch_id(),
            'order_id' => $op_data['sn'],
            'pay_type' => $this->getConfig('pay_type'),
            'amount' => $op_data['amount'],
            'remark' => lang('log_cz'),
            'notify_url' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'redirect_url' => url('/index/my/index', '', true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['status']) && $res['status'] == 0) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['pay_url']];
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
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        unset($data['signType']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['order_status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['order_id'],
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
        echo 'ERROR';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'merchant_id' => $this->get_mch_id(),
            'order_id' => $oinfo['id'],
            'bank_code' => $blank_info['bank_code'],
            'receive_account' => $blank_info['cardnum'],
            'receive_name' => $blank_info['username'],
            'amount' => $oinfo['num'],
            'notify_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['status']) && $res['status'] == 0) {
            return true;
        }
        $this->_payout_msg = !empty($res['message']) ? $res['message'] : '';
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $data = json_decode($put, true);
        if (empty($data['data']['sign'])) {
            exit();
        }
        if ($data['data']['transfer_status'] == 1) {
            exit();
        }
        $data2 = $data['data'];
        $sign_old = $data2['sign'];
        unset($data2['sign']);
        $sign = $this->_make_payout_sign($data2);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data2['transfer_status'] == 2 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data2['order_id'],
            'amount' => $data2['amount'],
            'msg' => !empty($data['message']) ? $data['message'] : '',
            'data' => $data2
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "ERROR";
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
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_payout_secret()));
    }
}