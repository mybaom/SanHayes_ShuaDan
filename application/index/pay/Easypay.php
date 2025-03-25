<?php

namespace app\index\pay;

use think\Db;

class Easypay extends PayBase
{
    const PAY_URL = 'https://pay.gamegods2020.com/pay/v1/pay/createOrder';
    const PAYOUT_URL = 'http://payment.gamegods2020.com/pay/v1/df/createOrder';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.easypay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.easypay.secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'merchantId' => $this->get_mch_id(),
            'orderId' => $op_data['sn'],
            'attach' => $op_data['sn'],
            'coin' => floatval($op_data['amount']),
            'productId' => config('pay.easypay.pay_type'),
            'goods' => $op_data['sn'],
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'redirectUrl' => url('/index/my/index', '', true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $resData = json_decode($res, true);
        if (isset($resData['code']) && $resData['code'] == 0) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $resData['data']['url']];
        }
        return ['respCode' => 'ERROR', 'payInfo' => '', 'res' => $res, 'resData' => $resData, 'postData' => $data];
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
        if (!isset($data['sign'])) {
            exit();
        }
        if ($data['code'] == 0) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        if (isset($data['attach'])) unset($data['attach']);
        if (isset($data['time'])) unset($data['time']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['code'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['outTradeNo'],
            'amount' => $data['coin'],
            'data' => $data
        ];
    }

    public function payCallbackSuccess()
    {
        echo 'OK';
    }

    public function payCallbackFail()
    {
        echo 'ERROR';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'merchantId' => $this->get_mch_id(),
            'outTradeNo' => $oinfo['id'],
            'coin' => $oinfo['num'],
            'transferCategory' => config('pay.easypay.payout_type'),
            'bankAccountName' => $blank_info['username'],
            'bankCardNum' => $blank_info['cardnum'],
            'ifscCode' => $blank_info['document_id'],
            'bankName' => 'bankName',
            'bankBranchName' => 'bankBranchName',
            'city' => 'city',
            'province' => 'province',
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 0) {
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
        if (!isset($data['sign'])) {
            exit();
        }
        if ($data['code'] == 0) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        if (isset($data['attach'])) unset($data['attach']);
        if (isset($data['time'])) unset($data['time']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['code'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['outTradeNo'],
            'amount' => $data['coin'],
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
        echo "OK";
    }


    /**
     * 创建签名
     * @param $data array  数据包
     * @return string
     */
    private function _make_sign(array $data): string
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if ($value) $str .= $key . '=' . $value . '&';
        }
        return strtoupper(md5($str . 'key=' . $this->get_secret()));
    }

    private function _make_payout_sign(array $data): string
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ($value) $str .= $key . '=' . $value . '&';
        }
        return strtoupper(md5($str . 'key=' . $this->get_secret()));
    }
}