<?php

namespace app\index\pay;

use think\Db;

class Wepayglobal extends PayBase
{
    const PAY_URL = 'https://payment.wepaygloabal.net/pay/web';
    const PAYOUT_URL = 'https://payment.wepaygloabal.net/pay/transfer';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.wepayglobal.' . $param);
        }
        return config('pay.wepayglobal.type.t' . $type . '.' . $param);
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
            'version' => '1.0',
            'mch_id' => $this->get_mch_id(),
            'pay_type' => $this->getConfig('pay_type'),
            'goods_name' => $userName,
            'mch_order_no' => $op_data['sn'],
            'trade_amount' => floatval($op_data['amount']),
            'order_date' => date('Y-m-d H:i:s'),
            'notify_url' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
            'page_url' => url('/index/my/index', '', true, true),
        ];
        $data['sign'] = $this->_make_sign($data);
        $data['sign_type'] = 'MD5';
        $res = $this->_post(self::PAY_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['respCode']) && $res['respCode'] == 'SUCCESS') {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['payInfo']];
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
        unset($data['signType']);
        $sign = $this->_make_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['tradeResult'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['mchOrderNo'],
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

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'mch_id' => $this->get_mch_id(),
            'mch_transferId' => $oinfo['id'],
            'receive_account' => $blank_info['cardnum'],
            'receive_name' => $blank_info['username'],
            'bank_code' => $blank_info['bank_code'],
            'payer_mobile' => $blank_info['tel'],
            'apply_date' => date('Y-m-d H:i:s'),
            'transfer_amount' => floatval($oinfo['num']),
            'back_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
            'remark' => 'remark',
        ];
        if (config('default_country') == 'COL') {
            unset($data['payer_mobile']);
            $data['remark'] = $blank_info['document_id'];
        }
        if (config('default_country') == 'BRA') {
            unset($data['payer_mobile']);
            $data['receive_account'] = '123456';
            $data['document_id'] = $blank_info['wallet_document_id'];
            $data['document_type'] = $blank_info['wallet_document_type'];
        }
        $data['sign'] = $this->_make_payout_sign($data);
        $data['sign_type'] = 'MD5';
        $res = $this->_post(self::PAYOUT_URL, $data);
        $res = json_decode($res, true);
        if (!empty($res['respCode']) && $res['respCode'] == 'SUCCESS') {
            return true;
        }
        $this->_payout_msg = json_encode($res) . json_encode($data);
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
        unset($data['signType']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        if (in_array($data['tradeResult'], [0, 4])) exit;
        return [
            'status' => ($data['tradeResult'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merTransferId'],
            'amount' => $data['transferAmount'],
            'msg' => !empty($data['errorMsg']) ? $data['errorMsg'] : '',
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
    private function _make_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        $str = $str . 'key=' . $this->getConfig('payout_secret');
        return strtolower(md5($str));
    }
}