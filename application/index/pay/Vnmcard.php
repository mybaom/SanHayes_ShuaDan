<?php

namespace app\index\pay;

use think\Db;

class Vnmcard extends PayBase
{
    const PAY_URL = '';
    const PAYOUT_URL = '';

    public static function instance()
    {
        return new self();
    }

    public function get_mch_id()
    {
        return '';
    }

    public function get_secret()
    {
        return '';

    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        return [
            'respCode' => 'SUCCESS',
            'payInfo' => '',
            'respType' => 'blank_code',
            'orderInfo' => $op_data,
            'cardInfo' => config('pay.vnmcard')
        ];
    }

    /**
     * 验证代收回调
     * @param string $type
     * @return array ['status'=>'SUCCESS',oid=>'订单号',amount=>'金额','data'=>'原始数据 array']
     */
    public function parsePayCallback($type = ''): array
    {
        return [];
    }

    public function payCallbackSuccess()
    {
        echo 'SUCCESS';
    }

    public function payCallbackFail()
    {
        echo 'SUCCESS';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $this->_payout_msg = '不支持此方式';
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        return [];
    }

    public function parsePayoutCallbackFail()
    {
        echo "ERROR";
    }

    public function parsePayoutCallbackSuccess()
    {
        echo "SUCCESS";
    }


    /**
     * 创建签名
     * @param $data array  数据包
     * @return string
     */
    private function _make_sign(array $data)
    {
        return '';
    }

    private function _make_payout_sign(array $data)
    {
        return '';
    }
}