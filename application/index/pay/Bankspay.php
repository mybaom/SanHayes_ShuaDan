<?php

namespace app\index\pay;

use think\Db;

class Bankspay extends PayBase
{
    const PAY_URL = 'https://apis.siyamall.com:1234/api/v2/topup';
    const PAYOUT_URL = 'https://apis.siyamall.com:1234/api/v2/withdraw';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.bankspay.' . $param);
        }
        return config('pay.bankspay.type.t' . $type . '.' . $param);
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
            'partnerid' => $this->get_mch_id(),
            'paytype' => $this->getConfig('pay_type'),
            'amount' => "" . floatval($op_data['amount']),
            'orderid' => $op_data['sn'],
            'notifyurl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true)
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['status']) && $res['status'] == 200) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['url']];
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
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['orderid'],
            'amount' => $data['amount'],
            'data' => $data
        ];
    }

    public function payCallbackSuccess()
    {
        echo '{"code":200,"msg":"ok"}';
    }

    public function payCallbackFail()
    {
        echo 'ERROR';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'partnerid' => $this->get_mch_id(),
            'orderid' => $oinfo['id'],
            'accountname' => $blank_info['username'],
            'cardnumber' => $blank_info['tel'],
            'amount' => "" . floatval($oinfo['num']),
            'notifyurl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['status']) && $res['status'] == 1) {
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
        if (empty($data)) $data = $_POST;
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
            'status' => ($data['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['orderid'],
            'amount' => $data['amount'],
            'msg' => !empty($data['partnerorder']) ? $data['partnerorder'] : $data['msg'],
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "ERROR";
    }

    public function parsePayoutCallbackSuccess()
    {
        echo '{"code":200,"msg":"ok"}';
    }


    /**
     * 创建签名
     * @param $data array  数据包
     * @return string
     */
    private function _make_sign(array $data)
    {
        $a = "" . floatval($data['amount']);
        return strtolower(md5($this->get_secret() . $this->get_mch_id() . $a));
    }

    private function _make_payout_sign(array $data)
    {
        $a = "" . floatval($data['amount']);
        return strtolower(md5($this->get_secret() . $this->get_mch_id() . $a));
    }
}