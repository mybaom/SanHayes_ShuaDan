<?php

namespace app\index\pay;

use think\Db;

class Brotherpay extends PayBase
{
    const PAY_URL = 'http://openapi.gobinfo.com/api/collection/create';
    const PAYOUT_URL = 'http://openapi.gobinfo.com/api/agentpay/apply';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.brotherpay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.brotherpay.secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'mchId' => $this->get_mch_id(),
            'appId' => config('pay.brotherpay.app_id'),
            'productId' => config('pay.brotherpay.pay_type'),
            'idNumber' => $op_data['sn'],
            'amount' => $op_data['amount'] * 100,
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                //'type' => input('get.type/d', 0),
                'type' => $op_data['sn'],
            ], true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['retCode']) && $res['retCode'] == 'SUCCESS') {
            return [
                'respCode' => 'SUCCESS',
                'respType' => 'code',
                'payInfo' => $res['payCode']
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
        parse_str($put, $data);
        if (empty($data['sign']) || empty($data['payCode'])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return [
                'status' => 'FAIL',
                'msg' => '签名错误',
                'sign_str' => $this->_make_sign($data, true),
                'new_sign' => $sign,
                'data' => $data
            ];
        }
        $oinfo = Db::name('xy_recharge')
            ->where('pay_type', $data['payCode'])
            ->where('pay_name', 'Brotherpay')
            ->order('addtime desc')
            ->find();
        if (empty($oinfo)) {
            exit();
        }
        //单位 分
        $data['amount'] = floatval($data['amount'] / 100);

        //如果订单已经充值了的话
        if ($oinfo['status'] == 2) {
            $SN = getSn('SY');
            $ress = Db::name('xy_recharge')
                ->insert([
                    'id' => $SN,
                    'uid' => $oinfo['uid'],
                    'real_name' => $oinfo['real_name'],
                    'tel' => $oinfo['tel'],
                    'num' => $data['amount'],
                    'type' => $oinfo['type'],
                    'pic' => $oinfo['pic'],
                    'addtime' => time(),
                    'endtime' => time(),
                    'status' => 1,
                    'status2' => 0,
                    'user_realname' => $oinfo['user_realname'],
                    'pay_name' => $oinfo['pay_name'],
                    'pay_status' => 0,
                    'pay_return' => '',
                    'pay_com' => $oinfo['pay_com'],
                    'pay_type' => $oinfo['pay_type'],
                ]);
            $oinfo = Db::name('xy_recharge')->where('id', $SN)->find();
        } else {
            //更改订单金额
            Db::name('xy_recharge')
                ->where('id', $oinfo['id'])
                ->update(['num' => $data['amount']]);
        }
        return [
            'status' => ($data['status'] == 2 ? 'SUCCESS' : 'FAIL'),
            'oid' => $oinfo['id'],
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
        echo 'error';
    }

    public $_payout_msg = '';
    public $_payout_id = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'mchId' => $this->get_mch_id(),
            'mchOrderNo' => $oinfo['id'],
            'amount' => intval($oinfo['num'] * 100),
            'accountType' => 3,
            'accountNo' => $blank_info['cardnum'],
            'accountName' => $blank_info['username'],
            'bankCode' => $blank_info['bank_code'],
            'bankName' => $blank_info['bankname'],
            'phone' => $blank_info['tel'],
            'reqTime' => date('YmdHis'),
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
            'remark' => 'payout'
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['retCode']) && $res['retCode'] == 'SUCCESS') {
            $this->_payout_id = $res['agentpayOrderId'];
            return true;
        }
        $this->_payout_msg = !empty($res['retMsg']) ? $res['retMsg'] : '';
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        parse_str($put, $data);
        if (empty($data['sign']) || empty($data['status'])
            || !in_array($data['status'], [2, 3])) {
            exit();
        }
        $sign_old = $data['sign'];
        unset($data['sign']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 2 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['mchOrderNo'],
            'amount' => $data['fee'] / 100,
            'msg' => !empty($data['transMsg']) ? $data['transMsg'] : '',
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "error";
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
    private function _make_sign(array $data, $getContent = false)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        if ($getContent) return $str . 'key=' . $this->get_secret();
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