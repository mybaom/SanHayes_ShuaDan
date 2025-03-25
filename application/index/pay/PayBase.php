<?php

namespace app\index\pay;

use think\Db;

abstract class PayBase
{
    /**
     * 验证代收回调
     * @param $type string 通道类型
     * @return array ['status'=>'SUCCESS',oid=>'订单号',amount=>'金额']
     * */
    abstract public function parsePayCallback($type = ''): array;

    /**
     * 用户支付成功
     * */
    abstract public function payCallbackSuccess();

    /**
     * 用户支付失败
     * */
    abstract public function payCallbackFail();

    /**
     * 创建代收订单
     * @param $op_data array
     * @return array  ['respCode' => 'SUCCESS', 'payInfo' => $res['order_data']];
     */
    abstract public function createPay(array $op_data): array;

    /**
     * 创建代付订单
     * @param $oinfo array
     * @param $blank_info array
     * @return bool
     */
    abstract public function create_payout(array $oinfo, array $blank_info): bool;

    /**
     * 代付回掉订单验证
     * @param $type string
     * @return array ['status'=>'SUCCESS',oid=>'订单号',amount=>'金额','data'=>'原始数据 array']
     */
    abstract public function parsePayoutCallback($type = ''): array;

    /**
     * 代付成功
     * */
    abstract public function parsePayoutCallbackSuccess();

    /**
     * 代付失败
     * */
    abstract public function parsePayoutCallbackFail();

    protected function _get($url, $header = [])
    {
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 超时设置,以秒为单位
        curl_setopt($curl, CURLOPT_TIMEOUT, 1);
        // 设置请求头
        if ($header) curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //执行命令
        $data = curl_exec($curl);
        // 显示错误信息
        if (curl_error($curl)) {
            return null;
        } else {
            curl_close($curl);
            return $data;
        }
    }

    /**
     * 发送 post 请求
     * @param $url string 请求地址
     * @param $data array 请求数据
     * @param $type string 请求类型支持 form-data  / json
     * @param $header array
     * @param $closeSSl boolean 是否关闭SSL检查
     * @return string|bool
     */
    protected function _post($url, array $data, $type = 'form-data', $header = [], $closeSSl = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (substr_count($url, 'https://') > 0 && $closeSSl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($type == 'json') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($header, ['Content-Type: application/json; charset=utf-8']));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            if ($header) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    /**
     * 随机生成用户名
     * @return string
     * */
    protected function randUsername($len = 0): string
    {
        $len = $len == 0 ? mt_rand(8, 14) : $len;
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        $username = "";
        for ($i = 0; $i < $len; $i++) {
            $username .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $username;
    }
}