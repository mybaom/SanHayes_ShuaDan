<?php

namespace app\index\pay;

use think\Db;

class Tokpay
{
    private $appid = '';
    private $secret = '';
    const PAY_URL = 'https://tokushimapay.com/api/pay';
    const PAYOUT_URL = 'https://tokushimapay.com/api/autoWithdrawal';

    public static function instance()
    {
        return new self();
    }

    public function get_appid()
    {
        return config('pay.tokpay.appid');
    }

    public function get_secret()
    {
        return config('pay.tokpay.secret');
    }

    public function get_pay_url()
    {
        return self::PAY_URL;
    }

    /**
     * 创建支付订单
     * @param array $data
     * @return json
     */
    public function create_order(array $data)
    {
        $data['appid'] = $this->get_appid();
        $data['sign'] = $this->_make_sign($data);
        return $data;
    }

    /**
     * 支付回掉- 验证签名
     * @param $data array  数据包
     * @return bool
     */
    public function check_sign(array $data): bool
    {
        $md5SrcStr = $this->get_appid() . $data['orderno'] . $data['actualamount'] . $data['status'] . $this->get_secret();
        //全部转成大写
        $md5SrcStr = strtoupper($md5SrcStr);
        //MD5 摘要信息计算
        $local_sign = md5($md5SrcStr);
        return $data['sign'] == $local_sign;
    }


    public $_payout_msg = '';

    /**
     * 创建付款订单--银行卡付款
     * @param $oinfo array  申请提现记录 对应xy_deposit表
     * @param $blank_info array  银行卡信息 对应xy_bankinfo表
     * @return bool
     */
    public function create_pix_payout($oinfo, $blank_info)
    {
        $params = [
            'appid' => $this->get_appid(),
            'settAmount' => $oinfo['real_num'],
            'orderno' => $oinfo['id'],
            'notifyurl' => url('/index/callback/payout_tokpay', '', true, true),
            'payType' => 'PIX',
        ];
        $params['ifscCode'] = 'cpf';
        $params['bankaccountname'] = $blank_info['username'];
        $params['cardno'] = $blank_info['document_id'];
        $params['sign'] = $this->_make_payout_sign($params);
        $log_file = APP_PATH . 'payout_tokpay_pix.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        $ret = $this->_post(self::PAYOUT_URL, $params);
        if (!$ret) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ': not content' . "\n", FILE_APPEND);
            return false;
        }
        file_put_contents($log_file, 'response: ' . $ret . "\n", FILE_APPEND);
        if ($ret == 'SUCCESS') {
            return true;
        }
        $this->_payout_msg = $ret;
        return false;
    }

    /**
     * 生成代付签名
     * @param $data array
     * @return string
     */
    private function _make_payout_sign($data)
    {
        $data['privateKey'] = $this->get_secret();
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }
        $str = strtoupper(substr($str, 0, -1));

        $log_file = APP_PATH . 'payout_tokpay_pix.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $str . "\n", FILE_APPEND);
        return md5($str);
    }

    /**
     * 生成支付签名
     * 注意：空值不参与加密，加密字段顺序按照下方示例顺序
     * //字段顺序为 固定 顺序:appid+支付类型+金额+订单号+私钥
     * $md5SrcStr = $appid . $paytype . $orderamount . $orderno . $privateKey;
     * //全部转成大写
     * $md5SrcStr = strtoupper($md5SrcStr);
     * //MD5 摘要信息计算
     * $sign = md5( $md5SrcStr );
     * @param $data array
     * @return string
     */
    private function _make_sign($data)
    {
        $md5SrcStr = $data['appid'] . $data['paytype'] . $data['orderamount'] . $data['orderno'] . $this->get_secret();
        $md5SrcStr = strtoupper($md5SrcStr);
        return md5($md5SrcStr);
    }

    /**
     * 发起请求
     * @param $payUrl string
     * @param $data array
     * @return string|null
     */
    private function _post(string $payUrl, array $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $payUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $output = curl_exec($ch);
        if (curl_error($ch)) return null;
        curl_close($ch);
        return $output;
    }
}