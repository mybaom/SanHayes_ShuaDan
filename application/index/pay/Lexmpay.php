<?php

namespace app\index\pay;

use think\Db;

class Lexmpay extends PayBase
{
    const PAY_URL = 'https://payment.lexmpay.com/pay/web';
    const PAYOUT_URL = 'https://payment.lexmpay.com/pay/transfer';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return config('pay.lexmpay.mch_id');
    }

    public function get_secret()
    {
        return config('pay.lexmpay.secret');

    }

    public function get_payout_secret()
    {
        return config('pay.lexmpay.payout_secret');

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();

        $pay_type = config('pay.lexmpay.pay_type');
        if (input('type/d') == 2) {
            $pay_type = config('pay.lexmpay.type2.pay_type');
        }

        $data = [
            'version' => '1.0',
            'mch_id' => $this->get_mch_id(),
            'mch_order_no' => $op_data['sn'],
            'pay_type' => $pay_type,
            'trade_amount' => $op_data['amount'],
            'order_date' => date('Y-m-d H:i:s'),
            'goods_name' => lang('log_cz'),
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
        if (isset($res['respCode']) && $res['respCode'] == 'SUCCESS') {
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
        parse_str($put, $data);
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
        echo 'ERROR';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'mch_id' => $this->get_mch_id(),
            'mch_transferId' => $oinfo['id'],
            'bank_code' => $blank_info['bank_code'],
            'receive_account' => $blank_info['cardnum'],
            'receive_name' => $blank_info['username'],
            'remark' => $blank_info['document_id'] ?: 'IFSC',  //印度代付必填IFSC码
            'receiver_telephone' => $blank_info['tel'],
            'apply_date' => date('Y-m-d H:i:s'),
            'transfer_amount' => $oinfo['num'],
            'back_url' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $data['sign'] = $this->_make_payout_sign($data);
        $data['sign_type'] = 'MD5';
        $res = $this->_post(self::PAYOUT_URL, $data);
        $res = json_decode($res, true);
        if (isset($res['respCode']) && $res['respCode'] == 'SUCCESS') {
            return true;
        }
        $this->_payout_msg = !empty($res['errorMsg']) ? $res['errorMsg'] : '';
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
        unset($data['signType']);
        $sign = $this->_make_payout_sign($data);
        if ($sign_old != $sign) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        return [
            'status' => ($data['tradeResult'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['merTransferId'],
            'amount' => $data['transferAmount'],
            'msg' => !empty($data['tradeNo']) ? $data['tradeNo'] : '',
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "ERROR";
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
        return strtolower(md5($str . 'key=' . $this->get_payout_secret()));
    }
}