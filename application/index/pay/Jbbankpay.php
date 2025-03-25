<?php

namespace app\index\pay;

use think\Db;

class Jbbankpay extends PayBase
{
    const PAY_URL = 'https://api.jbbanks.com/1.0/payin/order/create';
    const PAYOUT_URL = 'https://api.jbbanks.com/1.0/payout/order/create';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.jbbankpay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.jbbankpay.secret');

    }

    public function get_payout_secret()
    {
        return config('pay.jbbankpay.payout_secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'merchantId' => $this->get_mch_id(),
            'merchantOrderId' => $op_data['sn'],
            'currency' => config('pay.jbbankpay.currency'),
            'amount' => $op_data['amount'] * 100,
            'merchantNotifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'merchantReturnUrl' => url('/index/my/index', '', true, true),
            'payerIP' => request()->ip(),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 0) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['merchantReturnUrl']];
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
            'status' => ($data['status'] == 'PAID' ? 'SUCCESS' : 'FAIL'),
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
        echo 'ERROR';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        if ($oinfo['type'] != 'wallet') {
            $this->_payout_msg = '不支持此付款方式，仅支持电子钱包PIX';
            return false;
        }
        $data = [
            'merchantId' => $this->get_mch_id(),
            'merchantOrderId' => $oinfo['id'],
            'currency' => config('pay.jbbankpay.currency'),
            'type' => 1,
            'receiverIdCard' => $blank_info['wallet_document_id'],
            'receiverPixAccount' => $blank_info['wallet_document_id'],
            'receiverName' => $blank_info['username'],
            'receiverPixType' => $blank_info['wallet_document_type'],
            'receiverEmail' => $blank_info['wallet_tel'] . '@' . request()->rootDomain(),
            'amount' => $oinfo['num'] * 100,
            'merchantNotifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data);
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
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        if ($data['status'] == 'PROCESSING') {
            exit();
        }
        return [
            'status' => ($data['status'] == 'TRANSFERRED' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merchantOrderId'],
            'amount' => $data['amount'] / 100,
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
        return strtolower(md5($str . 'key=' . $this->get_payout_secret()));
    }
}