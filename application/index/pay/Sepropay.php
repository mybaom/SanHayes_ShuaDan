<?php

namespace app\index\pay;

use think\Db;

class Sepropay
{
    const PAY_URL = 'https://pay.sepropay.com/sepro/pay/web';
    const PAYOUT_URL = 'https://pay.sepropay.com/pay/transfer';

    public static function instance(): Sepropay
    {
        return new self();
    }

    public function get_mch_id(): string
    {
        return config('pay.sepropay.mch_id');
    }

    public function get_secret(): string
    {
        return config('pay.sepropay.secret');
    }

    public function get_pay_url(): string
    {
        return self::PAY_URL;
    }

    public function get_payout_url(): string
    {
        return self::PAYOUT_URL;
    }

    public function get_payout_secret()
    {
        return config('pay.sepropay.payout_secret');
    }

    /**
     * 创建支付订单
     * @param array $data
     * @return json
     */
    public function create_order(array $data)
    {
        $data['version'] = '1.0';
        $data['pay_type'] = config('pay.sepropay.pay_type');
        $data['order_date'] = date('Y-m-d H:i:s');
        $data['mch_id'] = $this->get_mch_id();
        $data['sign'] = $this->_make_sign($data);
        $data['sign_type'] = 'MD5';
        //return $data;
        return $this->_post($this->get_pay_url(), $data);
    }

    /**
     * 支付回掉- 验证签名
     * @param $data array  数据包
     * @return bool
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

    public $_payout_msg = '';

    /**
     * 创建付款订单--PIX
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
            'remark' => $blank_info['document_id'],
            'back_url' => url('/index/callback/payout_sepropay', '', true, true),
        ];
        if (config('default_country') == 'INR') {
            $data['bank_code'] = 'IDPT0001';
        }
        if (config('default_country') == 'MEX') {
            $data['bank_code'] = $blank_info['bank_code'];
            unset($data['remark']);
        }
        if (config('default_country') == 'BRA') {
            $data['account_digit'] = $blank_info['account_digit'];
            $data['document_id'] = $blank_info['wallet_document_id'];
            $data['document_type'] = $blank_info['wallet_document_type'];
        }
        $data['sign'] = $this->_make_payout_sign($data);
        $data['sign_type'] = 'MD5';
        $res = $this->_post($this->get_payout_url(), $data);
        if (!empty($res['respCode']) && $res['respCode'] == 'SUCCESS') {
            return true;
        }
        $logFile = APP_PATH . 'sepropay_create_payout.txt';
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
}