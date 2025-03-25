<?php

namespace app\index\pay;

use think\Db;

class Nibpay extends PayBase
{
    const PAY_URL = 'https://api.nibpay.com';
    const PAYOUT_URL = 'https://api.nibpay.com';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.nibpay.' . $param);
        }
        return config('pay.nibpay.type.t' . $type . '.' . $param);
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
            'service' => 'App.Pay.GatewayPay',
            'merchant_id' => $this->get_mch_id(),
            'out_order_id' => $op_data['sn'],
            'amount' => floatval($op_data['amount']),
            'notify_url' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'buy_currency' => $this->getConfig('currency'),
            'pay_currency' => $this->getConfig('currency'),
            'nonce' => $this->getNonce(),
            'timestamp' => time()
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['ret']) && $res['ret'] == 200) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['gateway_url']];
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
        if (!in_array($data['order_status'], [1, 2])) {
            exit();
        }
        return [
            'status' => ($data['order_status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['out_order_id'],
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
        $uName = explode(' ', $blank_info['username']);
        if (empty($uName[1])) $uName[1] = $uName[0];
        $data = [
            'service' => 'App.Payout.Payout',
            'merchant_id' => $this->get_mch_id(),
            'nonce' => $this->getNonce(),
            'timestamp' => time(),

            'out_order_id' => $oinfo['id'],
            'receive_type' => 'BANK',
            'receive_currency' => $this->getConfig('currency'),
            'receive_country' => $this->getConfig('country'),

            'bank_account_number' => $blank_info['cardnum'],
            'first_name' => $uName[0],
            'last_name' => $uName[1],
            'payer_bank' => $blank_info['bank_code'],

            'amount' => $oinfo['num'],
            'notify_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 1) {
            return true;
        }
        $this->_payout_msg = !empty($res['msg']) ? $res['msg'] : '';
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
            'status' => ($data['status'] == 2 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['order_sn'],
            'amount' => $data['amount'],
            'msg' => !empty($data['pt_order_sn']) ? $data['pt_order_sn'] : '',
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
        return strtoupper(md5($str . 'secret=' . $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        return $this->_make_sign($data);
    }

    /* 生成随机数*/
    private function getNonce($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }

        return $str;
    }


    public function getCountry()
    {
        $data = [
            'service' => 'App.Payout.GetReceiveCurrency',
            'merchant_id' => $this->get_mch_id(),
            'nonce' => $this->getNonce(),
            'timestamp' => time(),
            'receive_type' => 'BANK',
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        //echo $this->get_mch_id(), '---', $this->get_secret(), "<br>";
        echo($res);
    }

    public function getBank()
    {
        $data = [
            'service' => 'App.Payout.GetServicePoint',
            'merchant_id' => $this->get_mch_id(),
            'nonce' => $this->getNonce(),
            'timestamp' => time(),
            'receive_type' => 'BANK',
            'receive_currency' => $this->getConfig('currency'),
            'receive_country' => $this->getConfig('country')
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        //echo $this->get_mch_id(), '---', $this->get_secret(), "<br>";
        echo($res);
    }
}