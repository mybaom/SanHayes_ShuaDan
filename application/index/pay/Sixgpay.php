<?php

namespace app\index\pay;

use think\Db;

/**
 * 巴西支付对接
 */
class Sixgpay
{
    //收款url
    const PAY_URL = 'https://pay.666886.app/generate/order/';
    //付款url
    const PAYOUT_URL = 'http://66.42.45.182/generate/order/helpPay/brl';
    const PAYOUT_PIC_URL = 'http://66.42.45.182/generate/order/helpPay/picPay';
    public $_payout_msg = 'network error!';

    public static function instance()
    {
        return new self();
    }

    /**
     * 返回银行卡类型 支票账户/储蓄账户
     * @return array
     * */
    public function get_bank_types()
    {
        return ['CHECKING' => 'CHECKING', 'SAVINGS' => 'SAVINGS'];
    }

    /**
     * 返回巴西银行代码及名称
     * @return array
     * */
    public function get_bank_list()
    {
        return [
            '104' => 'Banco Caixa Economica Federal',
            '001' => 'Banco do Brasil',
            '237' => 'Banco Bradesco',
            '341' => 'Banco Itau',
            '033' => 'Banco Santander',
            '121' => 'AGIPLAN',
            '318' => 'Banco BMG',
            '218' => 'Banco Bonsucesso',
            '070' => 'Banco BRB de Brasília',
            '745' => 'Banco Citibank',
            '756' => 'Banco Cooperativa do Brasil',
            '748' => 'Banco Cooperativa Sicred',
            '003' => 'Banco da Amazonia',
            '707' => 'BANCO DAYCOVAL S.A.',
            '087' => 'Banco do Estado de Santa Catarina',
            '047' => 'Banco do Estado de Sergipe',
            '037' => 'Banco do Estado do Para',
            '041' => 'Banco do Estado do Rio Grande do Sul',
            '004' => 'Banco do Nordeste do Brasil',
            '399' => 'Banco HSBC',
            '653' => 'Banco Indusval',
            '077' => 'Banco Intermedium S.A.',
            '389' => 'Banco Mercantil do Brasil',
            '260' => 'Banco Nubank',
            '212' => 'Banco Original S.A.',
            '633' => 'Banco Rendimento',
            '422' => 'Banco Safra',
            '655' => 'Banco Votorantim',
            '021' => 'Banestes S\A - Banco do Estado do Espírito Santo',
            '755' => 'Bank of America',
            '085' => 'Cooperativa Central de Crédito Urbano Cecred',
            '090' => 'Cooperativa Unicred Central SP',
            '136' => 'Cooperativa Unicred de Sete Lagoas',
            '133' => 'CRESOL Confederacao',
            '254' => 'Parana Banco',
            '084' => 'Unicred Norte do Paraná'
        ];
    }

    /**
     * 获取支付链接
     * @return string
     * */
    public function get_pay_url()
    {
        return self::PAY_URL . $this->get_mch_id();
    }

    /**
     * 创建支付订单/ 主要用于拼接生成签名 拼接参数
     * @param array $data
     * @return array
     */
    public function create_order($data)
    {
        $data['mch_id'] = $this->get_mch_id();
        $data['sign'] = $this->_make_sign($data);
        return $data;
    }

    /**
     * 获取商户号
     * @return string
     */
    public function get_mch_id()
    {
        return config('pay.sixgpay.mch_id');
    }

    /**
     * 获取商户密钥
     * @return string
     */
    public function get_token()
    {
        return config('pay.sixgpay.secret');
    }

    /**
     * 校验支付回掉的签名
     * @return bool
     */
    public function check_sign($data)
    {
        if (empty($data['sign'])) return;
        $sign = $data['sign'];
        unset($data['sign']);
        return $sign == $this->_make_sign($data);
    }

    /**
     * 生成支付签名
     * @param $data array
     * @return string
     */
    private function _make_sign(&$data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }
        $data['sing_type'] = 'MD5';
        return strtolower(md5($str . 'key=' . $this->get_token()));
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        if (curl_error($ch)) return null;
        curl_close($ch);
        $response = json_decode($output, true);
        if (isset($response['code'])) return $response;
        return null;
    }


    //==============以下是付款功能============

    /**
     * 获取付款商户编号 mch_id
     * @return bool
     */
    private function get_payout_mch_id()
    {
        return config('pay.sixgpay.withdraw.mch_id');
    }

    /**
     * 获取付款商户密钥
     * @return string
     */
    private function get_payout_token()
    {
        return config('pay.sixgpay.withdraw.token');
    }

    /**
     * 创建付款订单--银行卡付款
     * @param $oinfo array  申请提现记录 对应xy_deposit表
     * @param $blank_info array  银行卡信息 对应xy_bankinfo表
     * @return bool
     */
    public function create_payout($oinfo, $blank_info)
    {
        $params = [
            'mch_id' => $this->get_payout_mch_id(),
            'mch_order_no' => $oinfo['id'],
            'pay_type' => 3,
            'notify_url' => url('/index/callback/payout_sixgpay', '', true, true),
            'trade_amount' => $oinfo['real_num'],
            'currency' => config('pay.sixgpay.withdraw.currency'),
            'name' => $blank_info['username'],
            'bankcode' => $blank_info['bank_code'],
            'branch' => $blank_info['bank_branch'],
            'account_type' => $blank_info['bank_type'],
            'account_number' => $blank_info['cardnum'],
            'account_digit' => $blank_info['account_digit'],
            'document_type' => $blank_info['document_type'],
            'document_id' => $blank_info['document_id'],
        ];
        $params['sign'] = $this->_make_payout_sign($params);
        $log_file = APP_PATH . 'payout_sixpay_bank.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        $ret = $this->_post(self::PAYOUT_URL, json_encode($params));
        if (!$ret) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ': not content' . "\n", FILE_APPEND);
            return false;
        }
        file_put_contents($log_file, 'response: ' . json_encode($ret) . "\n", FILE_APPEND);
        if (isset($ret['code'])) {
            if ($ret['message'] == '') return true;
            $this->_payout_msg = $ret['message'];
            return ($ret['code'] == 1);
        }
        return false;
    }

    /**
     * 创建付款订单--电子钱包
     * @param $oinfo array  申请提现记录 对应xy_deposit表
     * @param $blank_info array  银行卡信息 对应xy_bankinfo表
     * @return bool
     */
    public function create_pic_payout($oinfo, $blank_info)
    {
        //PAYOUT_PIC_URL
        $params = [
            'mch_id' => $this->get_payout_mch_id(), // 商户号	String	Y	平台分配唯一,
            'name' => $blank_info['username'], //收款人姓 名
            'document_id' => $blank_info['wallet_document_id'], //收款人的证件编号(CPF编号)
            'trade_amount' => $oinfo['real_num'], // 转账金额
            'mch_order_no' => $oinfo['id'], // 商户订单号
            'pay_type' => '4', // 通道4 巴西PICPAY通道
            'currency' => config('app.withdraw.sixgpay.currency'), // 货币代码
            'notify_url' => url('/index/callback/payout_sixgpay', '', true, true), // 代付回调
        ];
        $params['sign'] = $this->_make_payout_sign($params);
        $log_file = APP_PATH . 'payout_sixpay_wallet.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        $ret = $this->_post(self::PAYOUT_PIC_URL, json_encode($params));
        if (!$ret) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ': not content' . "\n", FILE_APPEND);
            return false;
        }
        file_put_contents($log_file, 'response: ' . json_encode($ret) . "\n", FILE_APPEND);
        if (isset($ret['code'])) {
            if ($ret['message'] == '') return true;
            $this->_payout_msg = $ret['message'];
            return ($ret['code'] == 1);
        }
        return false;

    }

    /**
     * 生成付款签名
     * @param $data array
     * @return string
     */
    private function _make_payout_sign(&$data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_payout_token()));
    }
}