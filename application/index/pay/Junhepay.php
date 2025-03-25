<?php

namespace app\index\pay;

use think\Db;

class Junhepay extends PayBase
{
    const PAY_URL = 'http://mex.junhepay.com/api/otcOrder/quickOrder';
    const PAYOUT_URL = 'http://mex.junhepay.com/api/otcPayOrder/unifiedOrder';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.junhepay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.junhepay.secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'appId' => $this->get_mch_id(),
            'ts' => time() * 1000,
            'currencyId' => 1,
            'outOrderNo' => $op_data['sn'],
            'payType' => config('pay.junhepay.pay_type'),
            'phone' => $oUser['tel'],
            'tradeAmount' => floatval($op_data['amount']),
            'tradeType' => 0,
            'terminalType' => 'app',
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            //'pageUrl' => url('/index/my/index', '', true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 200 && !empty($res['success'])) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['redirectHtml']];
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
            'status' => ($data['orderState'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['outOrderNo'],
            'amount' => $data['tradeAmount'],
            'data' => $data
        ];
    }

    public function payCallbackSuccess()
    {
        echo json_encode([
            "code" => 200,
            "data" => true,
            "message" => "请求成功",
            "success" => true
        ]);
    }

    public function payCallbackFail()
    {
        echo json_encode([
            "code" => 500,
            "data" => false,
            "message" => "ERROR",
            "success" => false
        ]);
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'appId' => $this->get_mch_id(),
            'terminalType' => 'app',
            'ts' => time() * 1000,
            'payType' => 1,
            'tradeAmount' => floatval($oinfo['num']),
            'outOrderNo' => $oinfo['id'],
            'bankCode' => $blank_info['bank_code'],
            'receiveAccount' => $blank_info['cardnum'],
            'receiveName' => $blank_info['username'],
            'customerMobile' => $blank_info['tel'],
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 200 && !empty($res['success'])) {
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
        unset($data['sign']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['orderState'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['outOrderNo'],
            'amount' => $data['tradeAmount'],
            'msg' => !empty($data['message']) ? $data['message'] : '',
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo json_encode([
            "code" => 500,
            "data" => false,
            "message" => "ERROR",
            "success" => false
        ]);
    }

    public function parsePayoutCallbackSuccess()
    {
        echo json_encode([
            "code" => 200,
            "data" => true,
            "message" => "请求成功",
            "success" => true
        ]);
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
            $str .= $key . '=' . $value . '&';
        }
        $str = substr($str, 0, -1);
        $str = urlencode($str);
        return hash_hmac("sha1", $str, $this->get_secret());
    }

    private function _make_payout_sign(array $data)
    {
        return $this->_make_sign($data);
    }
}