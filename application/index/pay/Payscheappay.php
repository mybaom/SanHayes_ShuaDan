<?php

namespace app\index\pay;

use think\Db;

class Payscheappay extends PayBase
{
    const PAY_URL = 'https://pro.payscheap.com/api.html';
    const PAYOUT_URL = 'https://pro.payscheap.com/';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.payscheappay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.payscheappay.secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'pay_memberid' => $this->get_mch_id(),
            'pay_orderid' => $op_data['sn'],
            'pay_bankcode' => config('pay.payscheappay.pay_type'),
            'pay_amount' => $op_data['amount'],
            'pay_attach' => $op_data['sn'],
            'pay_getip' => request()->ip(),
            'pay_notifyurl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 'success') {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['payurl']];
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
        $data['data'] = json_decode($data['data'], true);
        if (empty($data['data']) || !is_array($data['data'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_sign($data['data']);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['code'] == 'success' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['data']['orderid'],
            'amount' => $data['data']['money'],
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
            'mer_order_no' => $oinfo['id'],
            'acc_no' => $blank_info['cardnum'],
            'acc_name' => $blank_info['username'],
            'bank_code' => $blank_info['bank_code'],
            'mobile_no' => $blank_info['tel'],
            'province' => $blank_info['document_id'],
            'ccy_no' => config('pay.payscheappay.currency'),
            'order_amount' => $oinfo['num'],
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
            'summary' => 'payout'
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (!empty($res['status']) && $res['status'] == 'SUCCESS') {
            return true;
        }
        $this->_payout_msg = !empty($res['err_msg']) ? $res['err_msg'] : '';
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
        unset($data['sign']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 'SUCCESS' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['mer_order_no'],
            'amount' => $data['order_amount'],
            'msg' => !empty($data['err_msg']) ? $data['err_msg'] : '',
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