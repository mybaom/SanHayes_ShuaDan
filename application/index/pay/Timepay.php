<?php

namespace app\index\pay;

use think\Db;

class Timepay extends PayBase
{
    const PAY_URL = 'https://order.hopeallgood168.com/index/payOrderV2?request=json';
    const PAYOUT_URL = 'https://order.hopeallgood168.com/withdrawal/createorder';


    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.timepay.' . $param);
        }
        return config('pay.timepay.type.t' . $type . '.' . $param);
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
            'merchantId' => $this->get_mch_id(),
            'paytype' => $this->getConfig('paytype'),
            'merchantorder' => $op_data['sn'],
            'money' => $op_data['amount'],
            'versions' => '2.0',
            'verifyurl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'successurl' => url('/index/my/index', '', true, true),
        ];
        $data['digest'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 2000) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['data']['url']];
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
        if (empty($data['digest'])) {
            exit();
        }
        $sign_old = $data['digest'];
        unset($data['digest']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 'PAY_SUCCESS' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merchantorder'],
            'amount' => $data['money_true'],
            'data' => $data
        ];
    }

    public function payCallbackSuccess()
    {
        echo 'ok';
    }

    public function payCallbackFail()
    {
        echo 'error';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'merchantId' => $this->get_mch_id(),
            'merchantorder' => $oinfo['id'],
            'account' => $blank_info['cardnum'],
            'accountName' => $blank_info['username'],
            'bankID' => $blank_info['bank_code'],
            'IFSC' => $blank_info['document_id'],
            'remark' => $blank_info['tel'],
            'bankType' => 2,
            'money' => $oinfo['num'],
            'callback' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['digest'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (!empty($res['code']) && $res['code'] == 1) {
            return true;
        }
        $this->_payout_msg = !empty($res['msg']) ? $res['msg'] : '';
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $res = json_decode($put, true);
        if (empty($res)) $res = $_POST;
        if (empty($res['data']['digest'])) {
            exit();
        }
        $data = $res['data'];
        $sign_old = $data['digest'];
        unset($data['digest']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($res['code'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merchantorder'],
            'amount' => $data['money'],
            'msg' => !empty($data['systemorder']) ? $data['systemorder'] : '',
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "error";
    }

    public function parsePayoutCallbackSuccess()
    {
        echo "ok";
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