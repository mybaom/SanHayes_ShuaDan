<?php

namespace app\index\pay;

use think\Db;

class Pnsafepay extends PayBase
{
    const PAY_URL = 'http://pn.pnsafepay.com/gateway.aspx';
    const PAYOUT_URL = 'http://pn.pnsafepay.com/gateway.aspx';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.pnsafepay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.pnsafepay.secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'mer_no' => $this->get_mch_id(),
            'order_no' => $op_data['sn'],
            'order_amount' => $op_data['amount'],
            'payname' => $userName,
            'payemail' => $oUser['tel'] . '@' . request()->rootDomain(),
            'payphone' => $oUser['tel'],
            'currency' => config('pay.pnsafepay.currency'),
            'paytypecode' => config('pay.pnsafepay.paytypecode'),
            'method' => 'trade.create',
            'returnurl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'pageurl' => url('/index/my/index', '', true, true)
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (!empty($res['status']) && $res['status'] == 'success') {
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
        if (!in_array($data['status'], ['success', 'fail'])) {
            exit();
        }
        return [
            'status' => ($data['status'] == 'success' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['order_no'],
            'amount' => $data['order_realityamount'],
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
            'mer_no' => $this->get_mch_id(),
            'order_no' => $oinfo['id'],
            'method' => 'fund.apply',
            'acc_no' => $blank_info['cardnum'],
            'acc_name' => $blank_info['username'],
            'acc_code' => $blank_info['bank_code'],
            'mobile_no' => $blank_info['tel'],
            'otherpara2' => 'BANK',
            'branchbank' => $blank_info['bank_code'],
            'currency' => config('pay.pnsafepay.currency'),
            'order_amount' => floatval($oinfo['num']),
            'returnurl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        //$this->_payout_msg = $this->_make_payout_sign($data);return false;
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (!empty($res['status']) && $res['status'] == 'success') {
            return true;
        }
        $this->_payout_msg = !empty($res['status_mes']) ? $res['status_mes'] : '';
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
        if (!in_array($data['result'], ['success', 'fail'])) {
            exit();
        }
        return [
            'status' => ($data['result'] == 'success' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['order_no'],
            'amount' => $data['order_amount'],
            'msg' => !empty($data['result_msg']) ? $data['result_msg'] : '',
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
        return strtolower(md5(substr($str, 0, -1) . $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        $str = substr($str, 0, -1) . $this->get_secret();
        //return strtolower(md5($str)) . '====' . $str;
        return strtolower(md5($str));
    }
}