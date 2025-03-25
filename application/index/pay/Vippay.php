<?php

namespace app\index\pay;

use think\Db;

class Vippay extends PayBase
{
    const PAY_URL = 'https://ord.payvip.net/pay/order';
    const PAYOUT_URL = 'https://withdraw.payvip.net/withdraw/createOrder';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.vippay.' . $param);
        }
        return config('pay.vippay.type.t' . $type . '.' . $param);
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
            'merNo' => $this->get_mch_id(),
            'merchantOrderNo' => $op_data['sn'],
            'payCode' => $this->getConfig('payCode'),
            'currency' => $this->getConfig('currency'),
            'amount' => $op_data['amount'],
            'goodsName' => 'user recharge',
            'callbakUrl' => url('/index/my/index', '', true, true),
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'form-data', [
            'Contet-Type:application/x-www-form-urlencoded;charset=UTF-8'
        ]);
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 'SUCCESS') {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['payLink']];
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
            'status' => ($data['result'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merchantOrderNo'],
            'amount' => $data['amount'],
            'data' => $data
        ];
    }

    public function payCallbackSuccess()
    {
        echo 'success';
    }

    public function payCallbackFail()
    {
        echo 'error';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'merNo' => $this->get_mch_id(),
            'merchantOrderNo' => $oinfo['id'],
            'amount' => $oinfo['num'],
            'currency' => $this->getConfig('payout_currency'),
            'bankCode' => $blank_info['bank_code'],
            'customerName' => $blank_info['username'],
            'customerAccount' => $blank_info['cardnum'],
            'customerMobile' => $blank_info['tel'],
            'customerEmail' => 'test@123.com',
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data);
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 'SUCCESS') {
            return true;
        }
        $this->_payout_msg = json_encode($res);
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
        return [
            'status' => ($data['result'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merchantOrderNo'],
            'amount' => $data['amount'],
            'msg' => !empty($data['ptOrderNo']) ? $data['ptOrderNo'] : '',
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "error";
    }

    public function parsePayoutCallbackSuccess()
    {
        echo "success";
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