<?php

namespace app\index\pay;

use think\Db;

class Yulupay
{
    public static function instance(): Yulupay
    {
        return new self();
    }

    public function get_appid()
    {
        return config('app.yulupay.appid');
    }

    public function get_appkey()
    {
        return config('app.yulupay.secret');
    }

    public function check_sign($data)
    {
        $sign = $data['sign'];
        unset($data['sign']);
        $data['key'] = $this->get_appkey();
        $d2 = $this->ASCII($data);
        return $d2['sign'] == $sign;
    }

    /**
     * [ ASCII 编码 ]
     * @param array  编码数组
     * @param string 签名键名   => sign
     * @param string 密钥键名   => key
     * @param bool   签名大小写 => false(小写)
     * @param string 签名是否包含密钥 => false(不包含)
     * @return array 编码好的数组
     */
    public function ASCII($asciiData, $asciiSign = 'sign', $asciiKey = 'key', $asciiSize = false, $asciiKeyBool = false)
    {
        //编码数组从小到大排序
        ksort($asciiData);
        //拼接源文->签名是否包含密钥->密钥最后拼接
        $MD5str = "";
        foreach ($asciiData as $key => $val) {
            if (!$asciiKeyBool && $asciiKey == $key) continue;
            $MD5str .= $key . "=" . $val . "&";
        }
        $sign = $MD5str . $asciiKey . "=" . $asciiData[$asciiKey];
        //大小写->md5
        $asciiData[$asciiSign] = $asciiSize ? strtoupper(md5($sign)) : strtolower(md5($sign));
        return $asciiData;
    }

    /**
     * [ curl 请求 ]
     * @param $url    请求链接
     * @param $body   post数据
     * @param $method post或get
     * @return mixed|string
     */
    function curl_mixed($url, $body, $method = 'post')
    {
        $body = json_encode($body);
        if (function_exists("curl_init")) {
            $header = array(
                'Accept:application/json',
                'Content-Type:application/json;charset=utf-8',
            );
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            if ($method == 'post') {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $result = curl_exec($ch);
            curl_close($ch);
        } else {
            $opts = array();
            $opts['http'] = array();
            $headers = array(
                "method" => strtoupper($method),
            );
            $headers[] = 'Accept:application/json';
            $headers['header'] = array();
            $headers['header'][] = 'Content-Type:application/json;charset=utf-8';

            if (!empty($body)) {
                $headers['header'][] = 'Content-Length:' . strlen($body);
                $headers['content'] = $body;
            }

            $opts['http'] = $headers;
            $result = file_get_contents($url, false, stream_context_create($opts));
        }
        return $result;
    }

    //获取通道类型
    public function getBusi()
    {
        $url = "http://yulu-1.in/admin/pay/queryBusi";
        $arr = [
            "appid" => $this->get_appid(),
            "key" => $this->get_appkey()
        ];
        $arr = $this->ASCII($arr, "sign", "key", false, false);
        unset($arr['key']);
        $rows = $this->curl_mixed($url, $arr, "post");
        return json_decode($rows, true);
    }

    //发起代收订单
    public function createPay($data)
    {
        $url = "http://yulu-1.in/admin/pay/create";
        $busi = $this->getBusi();
        $params = [
            "appid" => $this->get_appid(),
            "currency" => "BRL",
            "busi_code" => $busi['busi_code'],
            "goods" => "all",
            "type" => $busi['pay'][0]['value'],
            "key" => $this->get_appkey()
        ];
        $data = array_merge($data, $params);
        $data = $this->ASCII($data, 'sign', 'key', false, false);
        unset($data['key']);
        $rows = $this->curl_mixed($url, $data, "post");
        return json_decode($rows, true);
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
        $url = "http://yulu-1.in/admin/wit/create";
        $busi = $this->getBusi();
        $data = [
            "appid" => $this->get_appid(),
            "mer_order_no" => $oinfo['id'],
            "name" => $blank_info['username'],
            "email" => "a@a.com",
            "phone" => $blank_info['wallet_tel'],
            "amount" => $oinfo['real_num'],
            "busi_code" => $busi['busi_code'],
            "type" => $busi['wit'][0]['value'],
            "currency" => "BRL",
            "bank_account" => $blank_info['wallet_document_id'],
            "branch_code" => $blank_info['wallet_document_id'],
            "bank_encrypt" => $blank_info['wallet_document_type'],
            "bank_code" => $blank_info['wallet_document_type'],
            "key" => $this->get_appkey()
        ];
        $data = $this->ASCII($data, 'sign', 'key', false, false);
        unset($data['key']);
        $rows = $this->curl_mixed($url, $data, "post");
        $res = json_decode($rows, true);
        if (!empty($res['code']) && !empty($res['status']) && $res['status'] == 'success') {
            return true;
        }
        $this->_payout_msg = !empty($res['msg']) ? $res['msg'] : '';
        return false;
    }
}