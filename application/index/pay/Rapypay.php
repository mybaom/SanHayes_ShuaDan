<?php

namespace app\index\pay;

use think\Db;

class Rapypay extends PayBase
{
    const PAY_URL = 'https://rpay.cash/rpay-api/order/submit';
    const PAYOUT_URL = 'https://rpay.cash/rpay-api/payout/submit';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.rapypay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.rapypay.secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'merchantId' => $this->get_mch_id(),
            'merchantOrderId' => $op_data['sn'],
            'amount' => sprintf("%.2f", $op_data['amount']),
            'timestamp' => time() * 1000,
            'payType' => config('pay.rapypay.pay_type'),
            'remark' => $op_data['sn'],
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'callbackUrl' => url('/index/my/index', '', true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data, 'json');
        $resData = json_decode($res, true);
        if (isset($resData['code']) && $resData['code'] == 0) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $resData['data']['h5Url']];
        }
        return ['respCode' => 'ERROR', 'payInfo' => '', 'res' => $res, 'resData' => $resData, 'postData' => $data];
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
        if (!isset($data['sign'])) {
            exit();
        }
        if ($data['status'] == 0) {
            exit();
        }
        $data['amount'] = sprintf("%.2f", $data['amount']);
        $sign_old = $data['sign'];
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merchantOrderId'],
            'amount' => $data['amount'],
            'data' => $data,
            'msg' => !empty($data['msg']) ? $data['msg'] : '',
        ];
    }

    public function payCallbackSuccess()
    {
        echo 'OK';
    }

    public function payCallbackFail()
    {
        echo 'ERROR';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'merchantId' => $this->get_mch_id(),
            'merchantOrderId' => $oinfo['id'],
            'amount' => sprintf("%.2f", $oinfo['num']),
            'timestamp' => time() * 1000,
            'fundAccount' => [
                'accountType' => config('pay.rapypay.payout_type'),
                'contact' => [
                    'name' => $blank_info['username']
                ],
                'bankAccount' => [
                    'name' => $blank_info['username'],
                    'ifsc' => $blank_info['document_id'],
                    'accountNumber' => $blank_info['cardnum'],
                ]
            ],
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 0) {
            return true;
        }
        $this->_payout_msg = !empty($res['error']) ? $res['error'] : '';
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $data = json_decode($put, true);
        if (!isset($data['sign'])) {
            exit();
        }
        if ($data['status'] == 0) {
            exit();
        }
        $data['amount'] = sprintf("%.2f", $data['amount']);
        $sign_old = $data['sign'];
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merchantOrderId'],
            'amount' => $data['amount'],
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
        echo "OK";
    }


    /**
     * 创建签名
     * @param $data array  数据包
     * @return string
     */
    private function _make_sign(array $data): string
    {
        $string = 'merchantId=' . $data['merchantId'] .
            '&merchantOrderId=' . $data['merchantOrderId'] .
            '&amount=' . $data['amount'] .
            '&' . $this->get_secret();
        return md5($string);
    }

    private function _make_payout_sign(array $data): string
    {
        return md5('merchantId=' . $data['merchantId'] .
            '&merchantOrderId=' . $data['merchantOrderId'] .
            '&amount=' . $data['amount'] .
            '&' . $this->get_secret());
    }
}