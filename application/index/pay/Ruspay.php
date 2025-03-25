<?php

namespace app\index\pay;

use http\Url;
use think\Db;

class Ruspay extends PayBase
{
    const PAY_URL = 'https://api.ruspay.net/api/Pay/addPay';
    const PAYOUT_URL = 'https://api.ruspay.net/api/Pay/addPayOut';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.ruspay.' . $param);
        }
        return config('pay.ruspay.type.t' . $type . '.' . $param);
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
        $data = [
            'mch_id' => $this->get_mch_id(),
            'pay_type' => $this->getConfig('pay_type'),
            'mch_order_no' => $op_data['sn'],
            'trade_amount' => floatval($op_data['amount']),
            'order_date' => date('Y-m-d H:i:s'),
            'user_id' => $oUser['tel'],
            'notify_url' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 20000 && isset($res['result']['pay_pageurl'])) {
            return [
                'respCode' => 'SUCCESS',
                'payInfo' => $res['result']['pay_pageurl']
            ];
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
        $sign = $this->_make_sign([
            'mch_id' => $data['mch_id'],
            'user_id' => $data['user_id'],
            'mch_order_no' => $data['mch_order_no'],
            'pay_type' => $data['pay_type'],
            'trade_amount' => floatval($data['trade_amount']),
            'order_date' => $data['order_date'],
            'notify_url' => $data['notify_url'],
        ]);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'sign' => $sign, 'data' => $data];
        }
        return [
            'status' => ($data['tradeResult'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['mch_order_no'],
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
        echo 'fail';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'mch_id' => $this->get_mch_id(),
            'channel_id' => $this->getConfig('channel_id'),
            'order_sn' => $oinfo['id'],
            'amount' => floatval($oinfo['num']),
            'payer_ifsc' => 'ifsc1001',
            'payer_account' => $blank_info['tel'],
            'payer_name' => $blank_info['username'],
            'notify_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        if (config('default_country') == 'BRA') {
            $data['payer_ifsc'] = $blank_info['document_id'];
            $data['payer_account'] = $blank_info['account_digit'];
        }
        if (config('default_country') == 'TUR') {
            $data['payer_account'] = strtolower($blank_info['cardnum']);
        }
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 20000) {
            return true;
        }
        $this->_payout_msg = !empty($res['message']) ? $res['message'] : '';
        return false;
    }

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
        //payer_ifsc  order_sn payer_name mch_id  channel_id amount  payer_account notify_url
        $sign = $this->_make_payout_sign([
            'payer_ifsc' => $data['payer_ifsc'],
            'order_sn' => $data['order_sn'],
            'payer_name' => $data['payer_name'],
            'mch_id' => $data['mch_id'],
            'channel_id' => $data['channel_id'],
            'amount' => floatval($data['amount']),
            'payer_account' => $data['payer_account'],
            'notify_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ]);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'sign' => $sign, 'data' => $data];
        }
        return [
            'status' => ($data['tradeResult'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['order_sn'],
            'amount' => $data['amount'],
            'msg' => $data['orderNo'],
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "fail";
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