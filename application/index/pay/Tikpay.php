<?php

namespace app\index\pay;

use think\Db;

class Tikpay extends PayBase
{
    const PAY_URL = 'https://payapi.soon-ex.com/otc/api/getRechargeData';
    const PAYOUT_URL = 'https://payapi.soon-ex.com/otc/api/issue';
    //测试
    //const PAY_URL = 'https://payapitest.soon-ex.com/otc/api/getRechargeData';
    //const PAYOUT_URL = 'https://payapitest.soon-ex.com/otc/api/issue';

    //充值回调地址 https://amaz365.com/index/callback/pay/Tikpay/0.html
    //提现回调地址 https://amaz365.com/index/callback/payout/Tikpay/0.html

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.tikpay.' . $param);
        }
        return config('pay.tikpay.type.t' . $type . '.' . $param);
    }

    public function get_mch_id()
    {
        return $this->getConfig('mch_id');
    }

    public function get_secret()
    {
        return $this->getConfig('secret');
    }

    public function get_secret_key16()
    {
        return substr($this->get_secret(), 0, 16);
    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'thirdOrderNumber' => $op_data['sn'],
            'amount' => $op_data['amount'],
            'thirdUserId' => $op_data['uid'],
        ];
        $res = $this->_post(self::PAY_URL, $data, 'json', [
            'Authorization: Basic ' . base64_encode($this->get_mch_id() . ':' . $this->get_secret())
        ], false);
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 0) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $this->getConfig('rechargeUrl') . $res['data']['orderNumber'] .
                '&phone=' . $oUser['tel'] .
                '&mail=' . $oUser['tel'] . '@' . request()->rootDomain()
            ];
        }
        return ['respCode' => 'ERROR', 'payInfo' => '', 'resData' => $res, 'postData' => $data];
    }

    /**
     * 验证代收回调
     * @param string $type
     * @return array ['status'=>'SUCCESS',oid=>'订单号',amount=>'金额','data'=>'原始数据 array']
     */
    public function parsePayCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $data = json_decode($put, true);
        if (empty($data['encryptedData'])) {
            exit();
        }
        $result = $this->decode($data['encryptedData']);
        $result = json_decode($result, true);
        if (empty($result['thirdOrderNumber'])) {
            return ['status' => 'FAIL', 'msg' => '数据解密失败', 'data' => $data];
        }
        if ($result['orderType'] == 1) {
            exit();
        }
        return [
            'status' => ($result['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $result['thirdOrderNumber'],
            'amount' => $result['amount'],
            'data' => $result
        ];
    }

    public function payCallbackSuccess()
    {
        echo json_encode([
            'code' => 1,
            'message' => 'success',
            'success' => true
        ], JSON_UNESCAPED_UNICODE);
    }

    public function payCallbackFail()
    {
        echo json_encode([
            'code' => 0,
            'message' => 'error',
            'success' => false
        ], JSON_UNESCAPED_UNICODE);
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        if ($oinfo['type'] != 'wallet') {
            //$this->_payout_msg = '不支持此付款方式，仅支持电子钱包PIX';
            //return false;
        }

        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        //生成12位的随机数
        //$nonce = date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $nonce = uniqid();
        //默认是巴西的
        $en = [
            'amount' => $oinfo['num'],
            'thirdOrderNumber' => $oinfo['id'],
            'thirdUserId' => $oinfo['id'],
            'issuePayPo' => [
                'accountName' => $blank_info['wallet_document_id'],  //pix钥匙串
                'name' => $blank_info['username'],  //提现用户姓名
                'paymentId' => $this->getConfig('paymentId')
            ]
        ];
        if (config('default_country') == 'TUR') {
            $en['issuePayPo']['accountName'] = $blank_info['cardnum'];
        }
        $data = [
            'encryptedData' => $this->encode(json_encode($en)),   //提现数据加密
            'signaturePo' => [
                'apiId' => $this->get_mch_id(),
                'nonce' => $nonce . '',
                'signature' => '',
                'timestamp' => $msectime . ''
            ],
        ];
        $signature = $this->sign([   //调用签名函数进行数据签名
            $data['signaturePo']['timestamp'] . '',
            $data['signaturePo']['nonce'] . '',
            $data['signaturePo']['apiId'], $this->get_secret(),
            json_encode($en), //将数组转json格式的数据
        ]);
        $data['signaturePo']['signature'] = $signature;


        $res = $this->_post(self::PAYOUT_URL, $data, 'json');
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 0 && $res['success'] == true) {
            return true;
        }
        $this->_payout_msg = json_encode($res).json_encode($en);
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $data = json_decode($put, true);
        if (empty($data['encryptedData'])) {
            exit();
        }
        $result = $this->decode($data['encryptedData']);
        $result = json_decode($result, true);
        if (empty($result['thirdOrderNumber'])) {
            return ['status' => 'FAIL', 'msg' => '数据解密失败', 'data' => $data];
        }
        if ($result['orderType'] == 0) {
            exit();
        }
        if (!isset($result['status'])) exit();
        return [
            'status' => ($result['status'] == 1 ? 'SUCCESS' : 'FAIL'),
            'oid' => $result['thirdOrderNumber'],
            'amount' => $result['amount'],
            'msg' => !empty($result['message']) ? $result['message'] : '',
            'data' => $result
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo json_encode([
            'code' => 0,
            'message' => 'error',
            'success' => false
        ], JSON_UNESCAPED_UNICODE);
    }

    public function parsePayoutCallbackSuccess()
    {
        echo json_encode([
            'code' => 1,
            'message' => 'success',
            'success' => true
        ], JSON_UNESCAPED_UNICODE);
    }


    /**
     * 创建签名
     * @param $data array  数据包
     * @return string
     */
    private function _make_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return strtoupper(md5($str . 'key=' . $this->get_secret()));
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if (strlen($value) > 0) $str .= $key . '=' . $value . '&';
        }
        return strtoupper(md5($str . 'key=' . $this->get_secret()));
    }

    //数据加密函数
    public function encode($str)
    {
        $data = base64_encode(openssl_encrypt($str, 'AES-128-CBC', $this->get_secret_key16(), OPENSSL_RAW_DATA, $this->getConfig('iv')));
        return $data;
    }

    //数据解密函数
    public function decode($secretData)
    {
        return openssl_decrypt(base64_decode($secretData), 'AES-128-CBC', $this->get_secret_key16(), OPENSSL_RAW_DATA, $this->getConfig('iv'));
    }

    //数据签名
    public function sign($data)
    {
        sort($data, SORT_LOCALE_STRING);
        $str = $data[0] . $data[1] . $data[2] . $data[3] . $data[4];
        return strtoupper(sha1($str));
    }
}