<?php

namespace app\index\pay;

use think\Db;

class Letspay extends PayBase
{
    const PAY_URL = 'http://api.letspayfast.com/apipay';
    const PAYOUT_URL = 'http://api.letspayfast.com/apitrans';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.letspay.' . $param);
        }
        return config('pay.letspay.type.t' . $type . '.' . $param);
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
            'mchId' => $this->get_mch_id(),
            'orderNo' => $op_data['sn'],
            'amount' => $op_data['amount'],
            'product' => $this->getConfig('product'), //baxipix
            'bankcode' => 'all',
            'goods' => 'email:' . $oUser['tel'] . '@gmail.com'
                . '/name:' . $userName
                . '/phone:' . $oUser['tel']
                . '/cpf:1234567892',
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'returnUrl' => url('/index/my/index', '', true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['retCode']) && $res['retCode'] == 'SUCCESS') {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['payUrl']];
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
        if (strtolower($sign_old) != strtolower($sign)) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        if (!in_array($data['status'], [2, 5])) {
            return ['status' => 'FAIL', 'msg' => '数据类型错误', 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 2 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['orderNo'],
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
            'type' => 'api',
            'mchId' => $this->get_mch_id(),
            'mchTransNo' => $oinfo['id'],
            'accountNo' => $blank_info['cardnum'],
            'accountName' => $blank_info['username'],
            'bankCode' => $blank_info['bank_code'],
            'remarkInfo' => 'email:' . $blank_info['tel'] . '@gmail.com/phone:' . $blank_info['tel'],
            //'mobile_no' => $blank_info['tel'],
            //'province' => $blank_info['document_id'],
            //'ccy_no' => $this->getConfig('currency'),
            'amount' => $oinfo['num'],
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        if (config('default_country') == 'BRA') {
            $data['bankCode'] = $this->getConfig('bankCode');
            $data['accountNo'] = $blank_info['document_id'];
        }
        $data['sign'] = $this->_make_payout_sign($data);
        //file_put_contents(APP_PATH . 'letspay.create.payout.txt', date('Y-m-d H:i:s') . ' ' . json_encode($data) . "\n", FILE_APPEND);
        $res = $this->_post(self::PAYOUT_URL, $data);
        //file_put_contents(APP_PATH . 'letspay.create.payout.txt', date('Y-m-d H:i:s') . ' ' . $res . "\n", FILE_APPEND);
        $res = json_decode($res, true);
        if (!empty($res['retCode']) && $res['retCode'] == 'SUCCESS') {
            return true;
        }
        $this->_payout_msg = !empty($res['retMsg']) ? $res['retMsg'] : '';
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
        unset($data['msg']);
        $sign = $this->_make_payout_sign($data);
        if (strtolower($sign_old) != strtolower($sign)) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        if (!in_array($data['status'], [2, 3])) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 2 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['mchTransNo'],
            'amount' => $data['amount'],
            'msg' => $data['status'] == 2 ? 'Successful transfer' : 'FAIL',
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
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_secret()));
    }
}