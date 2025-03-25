<?php

namespace app\index\pay;

use think\Db;

class Mxpay
{
    //const PAY_URL = 'http://test.api.mxpay.cash/openapi/pay/channel';
    //const PAYOUT_URL = 'http://test.api.mxpay.cash/openapi/pay/channel';
    const PAY_URL = 'http://api.mxpay.cash/openapi/pay/channel';
    const PAYOUT_URL = 'http://api.mxpay.cash/openapi/pay/channel';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        if (input('type') === 2) {
            return config('pay.mxpay.type2.mch_id');
        } else {
            return config('pay.mxpay.mch_id');
        }
    }

    public function get_secret()
    {
        if (input('type') === 2) {
            return config('pay.mxpay.type2.secret');
        } else {
            return config('pay.mxpay.secret');
        }
    }

    public function get_payout_secret()
    {
        return config('pay.mxpay.payout_secret');
    }

    //发起代收订单
    public function createPay($op_data)
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        $json = (object)[];
        $json->method = 'payment.url.create';
        $json->merchantNo = $this->get_mch_id();
        $json->currency = config('pay.mxpay.currency');
        $json->amount = $op_data['amount'];
        $json->merTransNo = $op_data['sn'];
        $json->merUserNo = $oUser['id'];
        $json->lastName = $userName;
        $json->userName = $userName;
        $json->userPhone = $oUser['tel'];
        $json->email = $oUser['tel'] . '@' . request()->rootDomain();
        $json->description = '';
        $json = (array)$json;
        $url = $this->param_build($json);
        $aes_str = openssl_encrypt($url, 'AES-256-ECB', config('pay.mxpay.aes_key'), 0);
        $sign = md5($aes_str . $this->get_secret());
        $para = [];
        $para['params'] = $aes_str;
        $para['sign'] = $sign;
        $para['merchantNo'] = $this->get_mch_id();
        $res = $this->_post(self::PAY_URL, $para);
        if (!empty($res['success']) && $res['success'] == true) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['result']];
        }
        return ['respCode' => 'ERROR', 'payInfo' => '', 'resData' => $res];
    }

    /**
     * 拼接参数序列
     * @param array $data json格式的参数
     * @return string
     */
    private function param_build($data)
    {
        ksort($data);
        $url = "";
        foreach ($data as $key => $val) {
            if ($val != '') {
                if ($url == "") {
                    $url = $url . $key . "=" . $val;
                } else {
                    $url = $url . "&" . $key . "=" . $val;
                }
            }
        }
        return $url;
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
            'method' => 'payout.order.create',
            'merchantNo' => $this->get_mch_id(),
            'currency' => config('pay.mxpay.currency'),
            'merTransNo' => $oinfo['id'],
            'amount' => $oinfo['real_num'],
            'cardType' => 'CLABE',
            'bankName' => $blank_info['bank_code'],
            'userName' => $blank_info['username'],
            'bankRef' => $blank_info['cardnum'],
            'merUserNo' => $oinfo['uid'],
            'userPhone' => $blank_info['tel'],
        ];
        $url = $this->param_build($data);
        $aes_str = openssl_encrypt($url, 'AES-256-ECB', config('pay.mxpay.aes_key'), 0);
        $sign = md5($aes_str . $this->get_secret());
        $para = [];
        $para['params'] = $aes_str;
        $para['sign'] = $sign;
        $para['merchantNo'] = $this->get_mch_id();
        $res = $this->_post(self::PAY_URL, $para);
        if (!empty($res['success']) && $res['success'] == true) {
            return true;
        }
        $this->_payout_msg = !empty($res['message']) ? $res['message'] : '';
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $output = curl_exec($ch);
        if (curl_error($ch)) return null;
        curl_close($ch);
        return json_decode($output, true);
    }

    /**
     * 支付回掉- 验证签名
     * @param $data string  数据包
     * @return bool
     */
    public function des_params(string $data)
    {
        return openssl_decrypt($data, 'AES-256-ECB', config('pay.mxpay.aes_key'), 0);
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