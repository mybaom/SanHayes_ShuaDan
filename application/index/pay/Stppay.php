<?php

namespace app\index\pay;

use think\Db;

class Stppay extends PayBase
{
    const PAY_URL = 'https://stppay.mx/api/payment.do';
    const PAYOUT_URL = 'https://stppay.mx/api/withdrawal.do';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.stppay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.stppay.secret');

    }

    public function get_payout_secret()
    {
        return config('pay.stppay.payout_secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'user_id' => $this->get_mch_id(),
            'cus_order_no' => $op_data['sn'],
            'type' => 1,
            'pay_amo' => $op_data['amount'] * 100,
            'currency' => config('pay.stppay.currency'),
            'notify_url' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true)
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 0) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['pay_url']];
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
        $dd = json_decode($put, true);
        if (empty($dd['data'])) {
            exit();
        }
        $data = $dd['data'];
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($dd['code'] == 0 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['cus_order_no'],
            'amount' => $data['pay_amo'] / 100,
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
        $data = [
            'user_id' => $this->get_mch_id(),
            'cus_order_no' => $oinfo['id'],
            'type' => 1,
            'bank_code' => $blank_info['bank_code'],
            'account' => $blank_info['cardnum'],
            'name' => $blank_info['username'],
            'branch_code' => '0001',
            'email' => $blank_info['tel'] . '@' . request()->rootDomain(),
            'mobile' => $blank_info['tel'],
            'pay_amo' => $oinfo['num'] * 100,
            'currency' => config('pay.stppay.currency'),
            'notify_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 0) {
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
        if (empty($data['data']['sign'])) {
            exit();
        }
        $data2 = $data['data'];
        $sign_old = $data2['sign'];
        unset($data2['sign']);
        $sign = $this->_make_payout_sign($data2);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['code'] == 0 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data2['cus_order_no'],
            'amount' => $data2['amount'] / 100,
            'msg' => !empty($data['msg']) ? $data['msg'] : '',
            'data' => $data2
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
        return strtoupper(md5($str . 'key=' . $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return strtoupper(md5($str . 'key=' . $this->get_payout_secret()));
    }
}