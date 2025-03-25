<?php

namespace app\index\pay;

use think\Db;

class Intfpay extends PayBase
{
    const PAY_URL = 'http://sapi.intfpay.com/v1/trade';
    const PAYOUT_URL = 'http://sapi.intfpay.com/v1/pay';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.intfpay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.intfpay.secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        if (is_numeric(substr($userName, 0, 1))) {
            $userName = 'a' . $userName;
        }
        $data = [
            'partner' => $this->get_mch_id(),
            'sign_type' => 'MD5',
            'trade_no' => $op_data['sn'],
            'trade_amount' => $op_data['amount'] * 100,
            'country_code' => config('pay.intfpay.country_code'),
            'pay_type' => config('pay.intfpay.pay_type'),
            'goods' => 'all',
            'name' => $userName,
            'email' => $oUser['tel'] . '@' . request()->rootDomain(),
            'phone' => $oUser['tel'],
            'notify_url' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'open_url' => url('/index/my/index', '', true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == '100000' && $res['message'] == 'SUCCESS') {
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
            'amount' => $data['trade_amount'] / 100,
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
            'partner' => $this->get_mch_id(),
            'sign_type' => 'MD5',
            'trade_sn' => $oinfo['id'],
            'pay_amount' => $oinfo['num'] * 100,
            'wallet_code' => config('pay.intfpay.wallet_code'),
            'country_code' => config('pay.intfpay.country_code'),
            'bank_card_no' => $blank_info['cardnum'],
            'bank_account' => $blank_info['username'],
            'bank_code' => config('pay.intfpay.bank_code'),
            //'bank_code' => $blank_info['bank_code'],
            'bank_site' => config('pay.intfpay.bank_code'),
            'bank_province' => $blank_info['document_id'],
            'bank_city' => $blank_info['document_id'],
            'phone' => $blank_info['tel'],
            'acc_type' => 'SAVING',
            'pass' => 'deposit',
            'notify_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == '100000' && $res['message'] == 'SUCCESS') {
            return true;
        }
        $this->_payout_msg = !empty($res['message']) ? $res['message'] : '';
        $this->_payout_msg .= '____' . json_encode($data);
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $data = json_decode($put, true);
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        if (!in_array($data['trade_status'], ['SUCCESS', 'FAIL'])) {
            exit();
        }
        return [
            'status' => ($data['trade_status'] == 'SUCCESS' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['trade_sn'],
            'amount' => $data['pay_amount'] / 100,
            'msg' => !empty($data['message']) ? $data['message'] : '',
            'data' => $data
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