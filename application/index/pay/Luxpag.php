<?php

namespace app\index\pay;

use think\Db;

class Luxpag
{
    //收款URL
    const PAY_URL = "https://gateway.luxpag.com/trade/create";

    public $_payout_msg = 'Network Error!';
    //付款到银行卡URL
    //private $_payout_transfersmile_bank_url = 'https://sandbox.transfersmile.com/api/v1/payout';
    const PAYOUT_BANK_URL = 'https://www.transfersmile.com/api/v1/payout';
    //付款到钱包URL
    //private $_payout_transfersmile_wallet_url = 'https://sandbox.transfersmile.com/api/v1/sdk/walletSession';
    const PAYOUT_WALLET_URL = 'https://www.transfersmile.com/api/v1/sdk/walletSession';

    //实例化
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
     * 创建支付订单
     * @param array $data
     * @return json
     */
    public function create_order($data)
    {
        $data['app_id'] = $this->get_mch_id();
        return $this->_post(self::PAY_URL, json_encode($data), array(
            'X-AjaxPro-Method:ShowList',
            'Content-Type:application/json; charset=utf-8',
            'Authorization:Basic ' . base64_encode($this->get_mch_id() . ':' . $this->get_token()),
        ));
    }

    /**
     * 获取商户号
     * @return string
     */
    public function get_mch_id()
    {
        return config('pay.luxpag.app_id');
    }

    /**
     * 获取商户密钥
     * @return string
     */
    public function get_token()
    {
        return config('pay.luxpag.secret');
    }

    /**
     * 收款生成签名
     * */
    private function _make_sign(&$data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }
        return strtolower(md5($str . 'key=' . $this->get_token()));
    }

    private function _post($url, $postdata, $header = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        $data = curl_exec($curl);
        if (curl_error($curl)) {
            return null;
        } else {
            $resData = json_decode($data, true);
            if (isset($resData['code'])) {
                return $resData;
            }
            return null;
        }
    }



    //----------------以下是付款功能---------
    /**
     * 付款到钱包
     * @param $oinfo array
     * @param $blank_info array
     * @return bool
     */
    public function payout_transfersmile_wallet($oinfo, $blank_info)
    {
        $params = [
            'amount' => $oinfo['real_num'],
            'notify_url' => url('/index/callback/payout_transfersmile', '', true, true),
            'custom_code' => $oinfo['id'],
            'payout_currency' => config('pay.transfersmile.payout.payout_currency'),
            'source_currency' => config('pay.transfersmile.payout.source_currency'),
            'restriction_info' => [
                'phone_number' => $blank_info['wallet_tel'],
                'document_id' => $blank_info['wallet_document_id']
            ]
        ];
        $header = [
            'X-AjaxPro-Method:ShowList',
            'Content-Type:application/json; charset=utf-8',
            'merchantId:' . config('pay.transfersmile.payout.mch_id'),
            'Authorization:' . $this->_payout_transfersmile_sign($params)
        ];
        $log_file = APP_PATH . 'payout_luxpag_wallet.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        file_put_contents($log_file, 'header: ' . json_encode($header, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        $ret = $this->curl_post(self::PAYOUT_WALLET_URL, json_encode($params), $header);
        file_put_contents($log_file, 'response: ' . $ret . "\n", FILE_APPEND);
        if (!$ret) return false;
        $data = json_decode($ret, true);
        if (!empty($data['code'])) {
            if ($data['code'] == 200) {
                return true;
            } else {
                $this->_payout_msg = $data['msg'];
            }
        }
        return false;
    }

    /**
     * 付款到银行卡
     * @param $oinfo array
     * @param $blank_info array
     * @return bool
     */
    public function payout_transfersmile_bank($oinfo, $blank_info)
    {
        $params = [
            'name' => $blank_info['username'],
            'bankcode' => $blank_info['bank_code'], //Bank code list http://docs.pagsmile.com/en/api/Bankinfo
            'branch' => $blank_info['bank_branch'],//The code of bank branch
            'account_type' => $blank_info['bank_type'],
            'account_number' => $blank_info['cardnum'],
            'document_type' => $blank_info['document_type'],
            'document_id' => $blank_info['document_id'],
            'fee' => 'beneficiary',
            'amount' => $oinfo['real_num'],
            'payout_currency' => config('pay.transfersmile.payout.payout_currency'),
            'source_currency' => config('pay.transfersmile.payout.source_currency'),
            'account_digit' => $blank_info['account_digit'],
            'notify_url' => url('/index/callback/payout_luxpay', '', true, true),
            'custom_code' => $oinfo['id'],
            'additional_remark' => 'Withdrawal',
        ];
        $header = [
            'X-AjaxPro-Method:ShowList',
            'Content-Type:application/json; charset=utf-8',
            'merchantId:' . config('pay.transfersmile.payout.mch_id'),
            'Authorization:' . $this->_payout_transfersmile_sign($params)
        ];
        $log_file = APP_PATH . 'payout_luxpag_bank.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        file_put_contents($log_file, 'header: ' . json_encode($header, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        $ret = $this->curl_post(self::PAYOUT_BANK_URL, json_encode($params), $header);
        file_put_contents($log_file, 'response: ' . $ret . "\n", FILE_APPEND);
        if (!$ret) return false;
        $data = json_decode($ret, true);
        if (!empty($data['code'])) {
            if ($data['code'] == 200) {
                return true;
            } else {
                $this->_payout_msg = $data['msg'];
            }
        }
        return false;
    }

    //提交url
    private function curl_post($url, $postdata, $header = [])
    {
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 超时设置
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        //执行命令
        $data = curl_exec($curl);
        // 显示错误信息
        if (curl_error($curl)) {
            return null;
        } else {
            // 打印返回的内容
            curl_close($curl);
            return $data;
        }
    }

    //付款生成签名
    private function _payout_transfersmile_sign($params)
    {
        ksort($params);
        $queryStr = '';
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                ksort($value);
                foreach ($value as $k => $v) {
                    $queryStr .= $key . '["' . $k . '"]=' . $v . '&';
                }
            } else {
                $queryStr .= $key . '=' . $value . '&';
            }
        }
        return md5(rtrim($queryStr, '&') . config('pay.transfersmile.payout.key'));
    }
}