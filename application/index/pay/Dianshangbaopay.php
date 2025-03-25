<?php

namespace app\index\pay;

use think\Db;

class Dianshangbaopay extends PayBase
{
    const PAY_URL = 'https://pay.51dianshangbao.com/index.php?/pay';
    const PAYOUT_URL = 'http://transfer.51dianshangbao.com/index.php?/transfer';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.dianshangbaopay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.dianshangbaopay.secret');
    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'merchant_code' => $this->get_mch_id(),
            'service_type' => 'direct_pay',
            'pay_type' => 'bank',
            'interface_version' => "V3.0",
            'input_charset' => "UTF-8",
            'order_no' => $op_data['sn'],
            'order_time' => date('Y-m-d H:i:s'),
            'product_name' => $op_data['sn'],
            'extend_param' => $op_data['sn'],
            'currency' => config('pay.dianshangbaopay.currency'),
            'order_amount' => sprintf("%.2f", $op_data['amount']),
            'notify_url' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'return_url' => url('/index/my/index', '', true, true),
        ];
        if (input('get.type/d', 0) == 2) {
            $data['pay_type'] = 'payid';
        }
        $data['sign'] = $this->_make_sign($data);
        $data['sign_type'] = "RSA-S";
        return [
            'respCode' => 'SUCCESS',
            'respType' => 'code',
            'payInfo' => self::PAY_URL,
            'data' => $data,
        ];
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
        //$data = json_decode($put, true);
        if (!isset($data['sign'])) {
            exit();
        }
        $check = $this->_check_callback_sign($data);
        if (!$check) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['trade_status'] == 'SUCCESS' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['order_no'],
            'amount' => $data['order_amount'],
            'data' => $data,
            'msg' => !empty($data['trade_desc']) ? $data['trade_desc'] : '',
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
            'bank_branch' => $blank_info['document_type'],
            'interface_version' => "V3.1.0",
            'currency' => config('pay.dianshangbaopay.currency'),
            'tran_code' => 'DMTI',
            'merchant_no' => $this->get_mch_id(),
            'mer_transfer_no' => $oinfo['id'],
            'tran_amount' => sprintf("%.2f", $oinfo['num']),
            'tran_fee_type' => 0,
            'tran_type' => 1,
            //'recv_province' => $blank_info['bank_code'],
            //'recv_city' => $blank_info['bank_code'],
            'recv_bank_code' => $blank_info['bank_code'],
            'recv_name' => $blank_info['username'],
            'recv_accno' => $blank_info['cardnum'],
            'return_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        if ($oinfo['type'] == 'wallet') {
            $data['recv_bank_code'] = 'payid';
            $data['recv_accno'] = $blank_info['document_id'];
        }
        $data['sign_info'] = $this->_make_payout_sign($data);
        $data['sign_type'] = "RSA";
        $res = $this->_post(self::PAYOUT_URL, $data);
        $res = json_decode($res, true);
        if (isset($res['result_code']) && $res['result_code'] == 0) {
            return true;
        }
        $this->_payout_msg = !empty($res['recv_info']) ? $res['recv_info'] : '';
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        parse_str($put, $data);
        if (!isset($data['sign']) || !isset($data['recv_code'])) {
            exit();
        }
        if ($data['recv_code'] == '0001') {
            exit();
        }
        $check = $this->_check_payout_callback_sign($data);
        if (!$check) {
            exit('sign error');
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['recv_code'] == '0000' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['mer_transfer_no'],
            'amount' => $data['tran_apply_amount'],
            'msg' => !empty($data['recv_info']) ? $data['recv_info'] : '',
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
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        $str = substr($str, 0, -1);
        $merchant_private_key = config('pay.dianshangbaopay.private');
        $merchant_private_key = openssl_get_privatekey($merchant_private_key);
        openssl_sign($str, $sign_info, $merchant_private_key, OPENSSL_ALGO_MD5);
        $sign = base64_encode($sign_info);
        return $sign;
    }

    private function _check_callback_sign(array $data): string
    {
        ksort($data);
        $str = '';
        $sign_str = base64_decode($data['sign']);
        unset($data['sign']);
        unset($data['sign_type']);
        foreach ($data as $key => $value) {
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        $str = substr($str, 0, -1);
        $dinpay_public_key = config('pay.dianshangbaopay.main_public');
        $dinpay_public_key = openssl_get_publickey($dinpay_public_key);
        $flag = openssl_verify($str, $sign_str, $dinpay_public_key, OPENSSL_ALGO_MD5);
        return $flag ? true : false;
    }

    private function _make_payout_sign(array $data): string
    {
        return $this->_make_sign($data);
    }

    private function _check_payout_callback_sign(array $data): string
    {
        $str = '';
        $sign_str = base64_decode($data['sign']);
        unset($data['sign']);
        ksort($data);
        foreach ($data as $key => $value) {
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        $str = substr($str, 0, -1);
        $dinpay_public_key = config('pay.dianshangbaopay.main_payout_public');
        $dinpay_public_key = openssl_get_publickey($dinpay_public_key);
        $flag = openssl_verify($str, $sign_str, $dinpay_public_key, OPENSSL_ALGO_MD5);
        return $flag ? true : false;
    }
}