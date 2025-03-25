<?php

namespace app\index\pay;

use think\Db;

class Speedypay
{
    //收款url
    const PAY_URL = 'https://pay.yunhuitongpay.net/pay/recharge/order';
    //付款url
    const PAYOUT_URL = 'http://66.42.45.182/api/withdrawal/order/add';
    const PAYOUT_PIC_URL = 'http://66.42.45.182/generate/order/helpPay/picPay';
    public $_payout_msg = 'network error!';

    public static function instance()
    {
        return new self();
    }

    /**
     * 创建支付订单
     * @param array $data
     * @return json
     */
    public function create_order($data)
    {
        $data['merchantId'] = $this->get_mch_id();
        $data['payType'] = config('pay.speedypay.pay_type');
        $data['clientIp'] = request()->ip();
        $data['sign'] = $this->_make_sign($data);
        return $this->_post(self::PAY_URL, json_encode($data), array(
            'Content-Type:application/json; charset=utf-8'
        ));
    }

    /**
     * 获取商户号
     * @return string
     */
    public function get_mch_id()
    {
        return config('pay.speedypay.mch_id');
    }

    /**
     * 获取商户密钥
     * @return string
     */
    public function get_token()
    {
        return config('pay.speedypay.secret');
    }

    /**
     * 生成支付签名
     * 注意：空值不参与加密，加密字段顺序按照下方示例顺序
     * sign = md5(payType=支付方式&merchantId=商户号&amount=订单金额&orderId=订单号&notifyUrl=通知地址&key=商户私钥)
     * @param $data array
     * @return string
     */
    private function _make_sign($data)
    {
        return md5(
            'payType=' . $data['payType'] .
            '&merchantId=' . $data['merchantId'] .
            '&amount=' . $data['amount'] .
            '&orderId=' . $data['orderId'] .
            '&notifyUrl=' . $data['notifyUrl'] .
            '&key=' . $this->get_token());
    }

    /**
     * 校验支付回掉的签名
     * 签名规则：
     * md5加密，加密字段顺序按照下方示例顺序
     * sign = md5(merchantId=商户号&amount=订单金额&orderId=订单号&orderStatus=订单状态&key=商户私钥)
     * @return bool
     */
    public function check_sign($data)
    {
        if (empty($data['sign'])) return;
        $sign = $data['sign'];
        unset($data['sign']);
        return $sign == md5(
                'merchantId=' . $this->get_mch_id() .
                '&amount=' . $data['amount'] .
                '&orderId=' . $data['orderId'] .
                '&orderStatus=' . $data['orderStatus'] .
                '&key=' . $this->get_token()
            );
    }

    /**
     * 发起请求
     * @param $payUrl string
     * @param $data string
     * @return string
     */
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        if (curl_error($ch)) return null;
        curl_close($ch);
        $response = json_decode($output, true);
        if (isset($response['status'])) return $response;
        return null;
    }


    //==============以下是付款功能============

    /**
     * 获取付款商户编号 mch_id
     * @return bool
     */
    private function get_payout_mch_id()
    {
        return config('app.withdraw.sixgpay.mch_id');
    }

    /**
     * 获取付款商户密钥
     * @return string
     */
    private function get_payout_token()
    {
        return config('app.withdraw.sixgpay.token');
    }
}