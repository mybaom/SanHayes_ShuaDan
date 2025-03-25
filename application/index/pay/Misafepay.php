<?php

namespace app\index\pay;

use think\Db;

class Misafepay extends PayBase
{
    const PAY_URL = 'https://gateway.misafepay.com/pay/order/create';
    const PAYOUT_URL = 'https://gateway.misafepay.com/withdraw/order/create';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.misafepay.' . $param);
        }
        return config('pay.misafepay.type.t' . $type . '.' . $param);
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
            'mer_no' => $this->get_mch_id(),
            'order_no' => $op_data['sn'],
            'order_amount' => $op_data['amount'],
            'currency' => $this->getConfig('currency'),
            'pay_code' => $this->getConfig('pay_code'),
            'order_date' => date('Y-m-d H:i:s'),
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'callbackUrl' => url('/index/my/index', '', true, true)
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 'SUCCESS') {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['pay_url']];
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
        if (empty($data)) parse_str($put, $data);
        if (empty($data)) $data = $_POST;
        if (empty($data['sign'])) {
            exit('no sign');
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['payResult'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['orderNo'],
            'amount' => $data['payAmount'],
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
            'mer_no' => $this->get_mch_id(),
            'settle_id' => $oinfo['id'],
            'currency' => $this->getConfig('currency'),
            'settle_amount' => floatval($oinfo['num']),
            'bankCode' => $blank_info['bank_code'],
            'accountNo' => $blank_info['cardnum'],
            'accountName' => $blank_info['username'],
            'settle_date' => date('Y-m-d H:i:s'),
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        if (config('default_country') == 'INR') {
            $data['ifsc'] = $blank_info['document_id'];
            $data['bankCode'] = 'SBIN';
        }
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 'SUCCESS') {
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
        if (empty($data)) parse_str($put, $data);
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
            'status' => ($data['payResult'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['orderNo'],
            'amount' => $data['payAmount'],
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
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_secret()));
    }
}