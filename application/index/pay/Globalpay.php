<?php

namespace app\index\pay;

use think\Db;

class Globalpay extends PayBase
{
    const PAY_URL = 'https://goobal.gdsua.com/ty/orderPay';
    const PAYOUT_URL = 'https://yugob.gdsua.com/withdraw/singleOrder';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.globalpay.' . $param);
        }
        return config('pay.globalpay.type.t' . $type . '.' . $param);
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
            'mer_order_no' => $op_data['sn'],
            'pname' => $userName,
            'pemail' => $oUser['tel'] . '@' . request()->rootDomain(),
            'phone' => $oUser['tel'],
            'order_amount' => $op_data['amount'],
            'countryCode' => $this->getConfig('country'),
            'ccy_no' => $this->getConfig('currency'),
            'busi_code' => $this->getConfig('pay_type'),
            'goods' => 'all',
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'pageUrl' => url('/index/my/index', '', true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (!empty($res['status']) && $res['status'] == 'SUCCESS') {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['order_data']];
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
        unset($data['sign']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 'SUCCESS' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['mer_order_no'],
            'amount' => $data['pay_amount'],
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
            'mer_no' => $this->get_mch_id(),
            'mer_order_no' => $oinfo['id'],
            'acc_no' => $blank_info['cardnum'],
            'acc_name' => $blank_info['username'],
            'bank_code' => $blank_info['bank_code'],
            'mobile_no' => $blank_info['tel'],
            'province' => $blank_info['document_id'],
            'ccy_no' => $this->getConfig('currency'),
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
            'msg' => $data['status'] == 'SUCCESS' ? 'Successful transfer' : 'FAIL',
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
        return strtolower(md5($str . 'key=' . $this->get_secret()));
    }
}