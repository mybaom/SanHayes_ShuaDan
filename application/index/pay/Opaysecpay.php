<?php

namespace app\index\pay;

use think\Db;

class Opaysecpay extends PayBase
{
    const PAY_URL = 'https://web.opaysec.com/gateway/pay';
    const PAYOUT_URL = 'http://issue.opaysec.com/gateway/payIssue';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.opaysecpay.' . $param);
        }
        return config('pay.opaysecpay.type.t' . $type . '.' . $param);
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
        $data = [
            'appId' => $this->get_mch_id(),
            'payWayId' => $this->getConfig('payWayId'),
            'outTradeNo' => $op_data['sn'],
            'totalfee' => sprintf("%.2f", $op_data['amount']),
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $url = self::PAY_URL . '?' . http_build_query($data);
        $res = file_get_contents($url);
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 200) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['url']];
        }
        return ['respCode' => 'ERROR', 'payInfo' => $url, 'resData' => $res, 'postData' => $data];
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
        if (empty($data)) parse_str($put, $data);
        if (empty($data)) $data = $_POST;
        if (empty($data)) $data = $_GET;
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $data['totalfee'] = $data['money'];
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['payStatus'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['outTradeNo'],
            'amount' => $data['money'],
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
            'appId' => $this->get_mch_id(),
            'outTradeNo' => $oinfo['id'],
            'collectionAccount' => $blank_info['cardnum'],
            'collectionName' => $blank_info['username'],
            'bankName' => $blank_info['bankname'],
            'branchName' => $blank_info['bank_code'],
            'collectionAccountType' => '1',
            'money' => $oinfo['num'],
            'collectionType' => 1,
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['notifyUrl'] = urlencode($data['notifyUrl']);
        $data['sign'] = $this->_make_payout_sign($data);
        $res2 = $this->_get(self::PAYOUT_URL . '?' . http_build_query($data));
        $res = json_decode($res2, true);
        if (!empty($res['code']) && $res['code'] == 200) {
            return true;
        }
        $this->_payout_msg = $res2 . json_encode($data);
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $data = json_decode($put, true);
        if (empty($data)) parse_str($put, $data);
        if (empty($data)) $data = $_POST;
        if (empty($data)) $data = $_GET;
        if (empty($data['sign'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_payout_callback_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        if ($data['payStatus'] == 2) {
            exit();
        }
        return [
            'status' => ($data['payStatus'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['outTradeNo'],
            'amount' => $data['money'],
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
        $str = $this->get_mch_id() . $this->getConfig('token') . $this->get_secret()
            . $data['outTradeNo'] . $data['totalfee'];
        return strtolower(md5($str));
    }

    private function _make_payout_sign(array $data)
    {
        $str = $this->get_mch_id() . $this->getConfig('token') . $this->get_secret()
            . $data['outTradeNo'] . $data['money'] . $data['collectionAccount']
            . $data['collectionName'] . $data['collectionAccountType'];
        return strtolower(md5($str));
    }

    private function _make_payout_callback_sign(array $data)
    {
        $str = $this->get_mch_id() . $this->getConfig('token') . $this->get_secret()
            . $data['outTradeNo']. $data['payStatus'];
        return strtolower(md5($str));
    }
}