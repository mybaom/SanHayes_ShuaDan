<?php

namespace app\index\pay;

use think\Db;

class Starspay extends PayBase
{
    const PAY_URL = 'https://api.stars-pay.com/api/gateway/pay';
    const PAYOUT_URL = 'https://api.stars-pay.com/api/gateway/withdraw';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.starspay.' . $param);
        }
        return config('pay.starspay.type.t' . $type . '.' . $param);
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
            'merchant_no' => $this->get_mch_id(),
            'timestamp' => time(),
            'sign_type' => 'MD5',
            'params' => [
                'merchant_ref' => $op_data['sn'],
                'product' => $this->getConfig('pay_product'),
                'amount' => $op_data['amount'],
            ],
        ];
        $data['sign'] = $this->_make_sign($data);
        $data['params'] = json_encode($data['params']);
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 200) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['params']['payurl']];
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
        parse_str($put, $data);
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        $sign = $this->_make_callback_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        $data['params'] = json_decode($data['params'], true);
        if (empty($data['params']['merchant_ref'])) {
            exit();
        }
        return [
            'status' => ($data['params']['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['params']['merchant_ref'],
            'amount' => $data['params']['pay_amount'],
            'data' => $data
        ];
    }

    public function payCallbackSuccess()
    {
        echo 'SUCCESS';
    }

    public function payCallbackFail()
    {
        echo 'SUCCESS';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'merchant_no' => $this->get_mch_id(),
            'timestamp' => time(),
            'sign_type' => 'MD5',
            'params' => [
                'merchant_ref' => $oinfo['id'],
                'product' => $this->getConfig('payout_product'),
                'amount' => $oinfo['num'],
                'account_name' => $blank_info['username'],
                'account_no' => $blank_info['cardnum'],
                'bank_code' => $blank_info['bank_code'],
            ],
        ];
        if (config('default_country') == 'BRA') {
            $data['params']['account_no'] = $blank_info['document_id'];
            $data['params']['bank_code'] = 'CPF';
        }
        $data['sign'] = $this->_make_payout_sign($data);
        $data['params'] = json_encode($data['params']);
        $res = $this->_post(self::PAYOUT_URL, $data);
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 200) {
            return true;
        }
        $this->_payout_msg = !empty($res['message']) ? $res['message'] : '';
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        parse_str($put, $data);
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        $sign = $this->_make_callback_sign($data);
        if ($sign_old != $sign) {
            return [
                'status' => 'FAIL',
                'msg' => '签名错误',
                'sign_old' => $sign_old,
                'sign' => $sign,
                'data' => $data
            ];
        }
        $data['params'] = json_decode($data['params'], true);
        if (empty($data['params']['merchant_ref'])) {
            exit();
        }
        return [
            'status' => ($data['params']['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['params']['merchant_ref'],
            'amount' => $data['params']['pay_amount'],
            'msg' => $data['params']['system_ref'],
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


    private function _make_callback_sign($data)
    {
        //(merchant_no+params+sign_type+timestamp+Key)
        return strtolower(md5($this->get_mch_id() .
            $data['params'] .
            $data['sign_type'] .
            $data['timestamp'] .
            $this->get_secret()));
    }

    /**
     * 创建签名
     * @param $data array  数据包
     * @return string
     */
    private function _make_sign(array $data)
    {
        return strtolower(md5($this->get_mch_id() .
            json_encode($data['params'], JSON_UNESCAPED_UNICODE) .
            $data['sign_type'] .
            $data['timestamp'] .
            $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        return strtolower(md5($this->get_mch_id() .
            json_encode($data['params'], JSON_UNESCAPED_UNICODE) .
            $data['sign_type'] .
            $data['timestamp'] .
            $this->get_secret()));
    }
}