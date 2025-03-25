<?php

namespace app\api\pay;

use think\Db;

class Trcpay
{
    //收款url
    const PAY_URL = 'https://api.trc20api.com/index.php/pay/api/createOrder';
    const APPID = 'NV76IJEJITY7RSZ1';
    const AccessKeySecret = 'LCMS87UW22K3ST2CF4MVFLYNP958BFP1';


    /**
     * 创建支付订单
     * @param array $data
     * @return json
     */
    public function create_order($arr)
    {
        $data['appid'] = self::APPID;
        $data['stamp'] = time();
        $data['money'] = $arr['money'];
        $data['t_order_num'] = $arr['sn'];
        $data['recharge_ctype'] = 2;
        $data['signature'] = $this->_make_sign($data);
        $json_res = $this->_post(self::PAY_URL, $data);
        return json_decode($json_res,true);
    }


    /**
     * 生成支付签名
     * 注意：空值不参与加密，加密字段顺序按照下方示例顺序
     * sign = md5(payType=支付方式&merchantId=商户号&amount=订单金额&orderId=订单号&notifyUrl=通知地址&key=商户私钥)
     * @param $data array
     * @return string
     */
    private function _make_sign($params)
    {
        if(isset($params['signature'])){
            unset($params['signature']);
        }
        ksort($params);
        $str = '';
        foreach ($params as $key=>$val){
            //if($key != 'list'){
            $str .= $val;
            //}
        }
        $sign = md5($str . self::AccessKeySecret);
        return $sign;
    }

    /**
     * 校验支付回掉的签名
     * 签名规则：
     * md5加密，加密字段顺序按照下方示例顺序
     * sign = md5(merchantId=商户号&amount=订单金额&orderId=订单号&orderStatus=订单状态&key=商户私钥)
     * @return bool
     */
    public function check_sign($params)
    {
        if(isset($params['signature'])){
            unset($params['signature']);
        }
        ksort($params);
        $str = '';
        foreach ($params as $key=>$val){
            //if($key != 'list'){
            $str .= $val;
            //}
        }
        $sign = md5($str . self::AccessKeySecret);
        return $sign;
    }

    /**
     * 发起请求
     * @param $payUrl string
     * @param $data string
     * @return string
     */
    private function _post($url, $data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_HTTPGET, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
        }
        curl_setopt($curl, CURLOPT_TIMEOUT,10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

}