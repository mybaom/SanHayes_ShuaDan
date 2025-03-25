<?php

namespace app\index\pay;

use think\Db;

class Liu6pay extends PayBase
{
    const PAY_URL = 'http://wap.liu6pay.cc/client/pay/getPayUrl';
    const PAYOUT_URL = 'http://wap.liu6pay.cc/client/pay/withdrawal';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.liu6pay.' . $param);
        }
        return config('pay.liu6pay.type.t' . $type . '.' . $param);
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
            'appId' => $this->get_mch_id(),
            'version' => '1.0',
            'payType' => $this->getConfig('pay_type'),
            'customOrderId' => $op_data['sn'],
            'amount' => floatval($op_data['amount']) * 100,
            'remark' => $oUser['id'] . "",
            'responseType' => 'JSON',
            'ip' => request()->ip(),
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'callbackUrl' => url('/index/my/index', '', true, true)
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 1) {
            return [
                'respCode' => 'SUCCESS',
                'payInfo' => $res['data']['payUrl']
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
        $sign = $this->_make_callback_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'sign' => $sign, 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['customOrderId'],
            'amount' => $data['realAmount'] / 100,
            'data' => $data
        ];
    }

    public function payCallbackSuccess()
    {
        echo 'SUCCESS';
    }

    public function payCallbackFail()
    {
        echo 'fail';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'appId' => $this->get_mch_id(),
            'withdrawalType' => 2,
            'version' => '1.0',
            'remark' => $oinfo['id'],
            'amount' => (floatval($oinfo['num']) * 100) . "",
            'phone' => $blank_info['tel'],
            'username' => $blank_info['username'],
            'callback' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 1) {
            return true;
        }
        $this->_payout_msg = !empty($res['msg']) ? $res['msg'] : '';
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
        $sign = $this->_make_callback_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'sign' => $sign, 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['remark'],
            'amount' => $data['amount'] / 100,
            'msg' => $data['withdrawId'],
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "fail";
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
        return strtolower(md5($data['appId'] .
            $data['version'] .
            $data['amount'] .
            $data['customOrderId'] .
            $data['notifyUrl'] .
            $data['callbackUrl'] .
            $data['payType'] .
            $data['remark'] .
            $data['responseType'] .
            $data['ip'] .
            $this->get_secret()));
    }

    private function _make_callback_sign(array $data)
    {
        return strtolower(md5($this->get_mch_id() .
            $data['status'] .
            $data['customOrderId'] .
            $data['amount'] .
            $data['realAmount'] .
            $data['payTime'] .
            $data['remark'] .
            $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        //md5(appid+version+amount+username+phone+remark+appsecret)
        return strtolower(md5($data['appId'] .
            $data['version'] .
            $data['amount'] .
            $data['username'] .
            $data['phone'] .
            $data['remark'] .
            $this->get_secret()));
    }

    private function _make_callback_payout_sign(array $data)
    {
        //md5 (appId+amount +remark+withdrawId+appsecret)
        return strtolower(md5($this->get_mch_id() .
            $data['amount'] .
            $data['remark'] .
            $data['withdrawId'] .
            $this->get_secret()));
    }
}