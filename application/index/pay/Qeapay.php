<?php

namespace app\index\pay;

use think\Db;

class Qeapay
{
    const PAY_URL = 'https://payment.qeapay.com/pay/web';
    const PAYOUT_URL = 'https://payment.qeapay.com/pay/transfer';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        if (input('get.type/d') === 2) {
            return config('pay.qeapay.type2.mch_id');
        } elseif (input('get.type/d') === 3) {
            return config('pay.qeapay.type3.mch_id');
        } else {
            return config('pay.qeapay.mch_id');
        }
    }

    public function get_secret()
    {
        if (input('get.type/d') === 2) {
            return config('pay.qeapay.type2.secret');
        } elseif (input('get.type/d') === 3) {
            return config('pay.qeapay.type3.secret');
        } else {
            return config('pay.qeapay.secret');
        }
    }

    public function get_payout_secret()
    {
        return config('pay.qeapay.payout_secret');
    }

    //发起代收订单
    public function createPay($data)
    {
        $data['version'] = '1.0';
        if (input('get.type/d') === 2) {
            $data['pay_type'] = config('pay.qeapay.type2.pay_type');
        } elseif (input('get.type/d') === 3) {
            $data['pay_type'] = config('pay.qeapay.type3.pay_type');
        } else {
            $data['pay_type'] = config('pay.qeapay.pay_type');
        }

        $data['order_date'] = date('Y-m-d H:i:s');
        $data['mch_id'] = $this->get_mch_id();
        $data['sign'] = $this->_make_sign($data);
        $data['sign_type'] = 'MD5';
        $res = $this->_post(self::PAY_URL, $data);
        if (!empty($res['respCode']) && $res['respCode'] == 'SUCCESS') {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['payInfo']];
        }
        return ['respCode' => 'ERROR', 'payInfo' => '', 'resData' => $res, 'postData' => $data];
    }

    /**
     * 创建付款订单
     * @param $oinfo array  申请提现记录 对应xy_deposit表
     * @param $blank_info array  银行卡信息 对应xy_bankinfo表
     * @return bool
     */
    public function create_payout($oinfo, $blank_info)
    {
        $data = [
            'mch_id' => $this->get_mch_id(),
            'mch_transferId' => $oinfo['id'],
            'transfer_amount' => $oinfo['real_num'],
            'apply_date' => date('Y-m-d H:i:s'),
            'bank_code' => $blank_info['bank_code'],
            'receive_name' => $blank_info['username'],
            'receive_account' => $blank_info['cardnum'],
            'receiver_telephone' => $blank_info['tel'],
            'back_url' => url('/index/callback/payout_qeapay', '', true, true),
        ];
        if (config('default_country') == 'INR') {
            $data['remark'] = $blank_info['document_id'];
        }
        if (config('default_country') == 'BRA') {
            $data['account_digit'] = $blank_info['account_digit'];
            $data['receive_account'] = $blank_info['wallet_document_id'];
            $data['document_id'] = $blank_info['wallet_document_id'];
            $data['document_type'] = $blank_info['wallet_document_type'];
            $data['account_type'] = $blank_info['bank_type'];
            $data['receiver_telephone'] = '55' . $blank_info['tel'];
        }
        $data['sign'] = $this->_make_payout_sign($data);
        $data['sign_type'] = 'MD5';
        $res = $this->_post(self::PAYOUT_URL, $data);
        if (!empty($res['respCode']) && $res['respCode'] == 'SUCCESS') {
            return true;
        }
        $logFile = APP_PATH . 'qeapay_create_payout.txt';
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . json_encode($data) . "\n", FILE_APPEND);
        file_put_contents($logFile, 'ERROR:  ' . json_encode($res), FILE_APPEND);
        $this->_payout_msg = !empty($res['errorMsg']) ? $res['errorMsg'] : '';
        return false;
    }

    private function _post($payUrl, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $payUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $output = curl_exec($ch);
        if (curl_error($ch)) return null;
        curl_close($ch);
        $response = json_decode($output, true);
        if (isset($response['respCode'])) return $response;
        return null;
    }

    /**
     * 支付回掉- 验证签名
     * @param $data array  数据包
     * @return bool
     */
    public function check_sign(array $data): bool
    {
        $sign = $data['sign'];
        unset($data['sign']);
        unset($data['signType']);
        $s = $this->_make_sign($data);
        return $s == $sign;
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
        return strtolower(md5($str . 'key=' . $this->get_secret()));
    }


    public function check_payout_sign(array $data): bool
    {
        $sign = $data['sign'];
        unset($data['sign']);
        unset($data['signType']);
        $s = $this->_make_payout_sign($data);
        return $s == $sign;
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_payout_secret()));
    }
}