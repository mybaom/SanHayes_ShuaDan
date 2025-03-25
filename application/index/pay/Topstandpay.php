<?php

namespace app\index\pay;

use think\Db;

class Topstandpay extends PayBase
{
    const PAY_URL = 'https://geepay.site/appclient/getUrl.do';
    const PAYOUT_URL = 'https://geepay.site/appclient/withdraw.do';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.topstandpay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.topstandpay.secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $url = self::PAY_URL;
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'version' => '1.0',
            'appid' => $this->get_mch_id(),
            'out_trade_no' => $op_data['sn'],
            'type' => 7,
            'amount' => $op_data['amount'] * 100,
            'currency' => config('pay.topstandpay.currency'),
            'notify_url' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'callback_url' => url('/index/my/index', '', true, true),
        ];
        if (config('default_country') == 'COL') {
            $url = 'https://geepay.site/appclient/getUrlCol.do';
            unset($data['version']);
            unset($data['type']);
            unset($data['callback_url']);
        }
        $data['sign'] = $this->_make_sign($data);
        if (config('default_country') == 'MEX') {
            $data = array_merge($data, [
                'cardHolder' => $userName,
                'email' => $oUser['tel'] . '@' . request()->rootDomain(),
                'mobile' => $oUser['tel'],
            ]);
        }
        $resss = $this->_post($url, $data);
        $res = json_decode($resss, true);
        if (isset($res['data']['url'])) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['url']];
        }
        return ['respCode' => 'ERROR', 'payInfo' => '', 'resData' => $res, 'resss' => $resss, 'postData' => $data];
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
        if (empty($data['data']) || !is_array($data['data'])) {
            exit();
        }
        $old_data = $data;
        $data = $old_data['data'];
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误',
                'data' => $data, 'sign' => $sign, 'sign_old' => $sign_old];
        }
        return [
            'status' => ($old_data['code'] == 0 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['out_trade_no'],
            'amount' => $data['amount'] / 100,
            'data' => $old_data
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
        $userName = preg_replace("/\\d+/", '', $blank_info['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'appid' => $this->get_mch_id(),
            'out_trade_no' => $oinfo['id'],
            'type' => 1,
            'bank_account' => $blank_info['cardnum'],
            'name' => $blank_info['username'],
            'bank_code' => $blank_info['bank_code'],
            'branch_code' => $blank_info['bank_code'],
            'email' => $userName . '@' . request()->rootDomain(),
            'mobile' => $blank_info['tel'],
            'province' => $blank_info['document_id'],
            'currency' => config('pay.topstandpay.currency'),
            'amount' => $oinfo['num'] * 100,
            'notify_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
            'version' => 'v1.0'
        ];
        if (config('default_country') == 'COL') {
            $data['document_id'] = $blank_info['document_id'];
        }
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data);
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
        $sign_old = $data['data']['sign'];
        unset($data['data']['sign']);
        $sign = $this->_make_payout_sign($data['data']);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误',
                'data' => $data, 'sign' => $sign, 'sign_old' => $sign_old];
        }
        return [
            'status' => ($data['code'] == 0 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['data']['out_trade_no'],
            'amount' => $data['data']['amount'] / 100,
            'msg' => !empty($data['msg']) ? $data['msg'] : '',
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
        return strtoupper(md5($str . 'key=' . $this->get_secret()));
    }
}