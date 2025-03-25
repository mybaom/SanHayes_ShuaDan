<?php

namespace app\index\pay;

use think\Db;

class Globalpay51 extends PayBase
{
    const PAY_URL = 'https://api.51globalpay.net/payin/unifiedorder.do';
    const PAYOUT_URL = 'https://api.51globalpay.net/payout/unifiedorder.do';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.globalpay51.' . $param);
        }
        return config('pay.globalpay51.type.t' . $type . '.' . $param);
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
            'merchantNo' => $this->get_mch_id(),
            'merchantOrderId' => $op_data['sn'],
            'channelCode' => $this->getConfig('channelCode'),
            'currency' => $this->getConfig('currency'),
            'amount' => $op_data['amount'] * 100,
            'expireTime' => 700,
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $data['subject'] = 'order';
        $data['version'] = '1.0';
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == '000') {
            if (!isset($res['data']['checkStand'])) {
                echo json_encode($res);
                exit;
            }
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['checkStand']];
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
            'status' => ($data['status'] == 'SUCCESS' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merchantOrderId'],
            'amount' => $data['amount'] / 100,
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
            'merchantNo' => $this->get_mch_id(),
            'merchantOrderId' => $oinfo['id'],
            'channelCode' => $this->getConfig('payout_channelCode'),
            'currency' => $this->getConfig('currency'),
            'email' => $blank_info['tel'] . '@GMAIL.COM',
            'userName' => $blank_info['username'],
            'mobileNo' => $blank_info['tel'],
            'amount' => $oinfo['num'] * 100,
            'expireTime' => 700,
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $data['version'] = '1.0';
        $bkInfo = [
            'bankName' => $blank_info['bank_code'],
            'cardNumber' => $blank_info['cardnum']
        ];
        if (config('default_country') == 'TUR') {
            //unset($bkInfo['bankName']);
            $bkInfo['cardNumber'] = strtolower($bkInfo['cardNumber']);
            //$bkInfo['bankCode'] = $blank_info['bank_code'];
        }
        $data['bankInfo'] = json_encode($bkInfo, JSON_UNESCAPED_UNICODE);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == '000') {
            return true;
        }
        $this->_payout_msg = !empty($res['msg']) ? $res['msg'] : '';
        //$this->_payout_msg .= json_encode($data);
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
            'status' => ($data['status'] == 'SUCCESS' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merchantOrderId'],
            'amount' => $data['amount'] / 100,
            'msg' => !empty($data['errMsg']) ? $data['errMsg'] : '',
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
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        $str = $str . 'key=' . $this->get_secret();
        $hash = hash_hmac('sha256', $str, $this->get_secret());
        //echo $str, "===", $hash;
        return $hash;
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return hash_hmac('sha256', $str . 'key=' . $this->get_secret(), $this->get_secret());
    }
}