<?php

namespace app\index\pay;

use think\Db;

class Seapay extends PayBase
{
    const PAY_URL = 'http://seapay_mxg.huayiks.cn/seapay.php?a=pay_mxg';
    const PAYOUT_URL = 'http://seapay_mxg.huayiks.cn/seapay.php?a=order';
    const BANK_URL = 'http://seapay_mxg.huayiks.cn/seapay.php?a=bank';

    public static function instance()
    {
        return new self();
    }

    public function getConfig($param)
    {
        $type = input('get.type/d', 0);
        if ($type == 0) {
            return config('pay.seapay.' . $param);
        }
        return config('pay.seapay.type.t' . $type . '.' . $param);
    }

    public function get_mch_id()
    {
        return $this->getConfig('mch_id');
    }

    public function get_secret()
    {
        return $this->getConfig('secret');
    }

    public function getBankList()
    {
        $res = $this->_post(self::BANK_URL, [], 'json', [
            'Authorization: ' . $this->_make_sign([]),
            'AppId: ' . $this->get_mch_id()
        ]);
        $res = json_decode($res,true);
        foreach($res['msg']['bank'] as $v){
            echo $v['bankCode'],'|',$v['bankName'],"\n";
        }
    }

    //发起代收订单
    public function createPay(array $op_data): array
    {
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $userName = preg_replace("/\\d+/", '', $oUser['username']);
        if (!$userName) $userName = $this->randUsername();
        $data = [
            'orderNo' => $op_data['sn'],
            'curp' => 'ND',
            'monto' => $op_data['amount'],
            'name' => $userName,
            'email' => '888@gmail.com',
            'phone' => $oUser['tel'],
            'notifyUrl' => url('/index/callback/pay', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
                'type' => input('get.type/d', 0)
            ], true, true),
        ];
        $res = $this->_post(self::PAY_URL, $data, 'json', [
            'Authorization: ' . $this->_make_sign($data),
            'AppId: ' . $this->get_mch_id()
        ]);
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 200) {
            return ['respCode' => 'SUCCESS', 'payInfo' => $res['msg']['url']];
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
        if (empty($data)) $data = $_POST;
        if (empty($data['sign'])) {
            exit();
        }
        $authorization = $_SERVER["HTTP_AUTHORIZATION"];
        $firstMd5 = md5($put);
        $authorizations = md5($firstMd5 . $this->get_secret());
        if ($authorizations != $authorization) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        $signarr = [];
        $signarr["orderNo"] = $data["orderNo"];
        $signarr["clabe"] = $data["clabe"];
        $signarr["barcode"] = $data["barcode"];
        $signarr["monto"] = $data["monto"];
        $signarr["type"] = $data["type"];
        $signarr["key"] = $this->get_mch_id();
        ksort($signarr);
        $verify = "";
        foreach ($signarr as $x_value) $verify = $verify . $x_value;
        $sign = strtolower(md5($verify));
        if ($sign != $data["sign"]) {
            return ['status' => 'FAIL', 'msg' => '签名错误2', 'data' => $data];
        }
        return [
            'status' => 'SUCCESS',
            'oid' => $data['orderNo'],
            'amount' => $data['monto'],
            'data' => $data
        ];
    }

    public function payCallbackSuccess()
    {
        echo '{"code": 200}';
    }

    public function payCallbackFail()
    {
        echo 'error';
    }

    public $_payout_msg = '';

    public function create_payout(array $oinfo, array $blank_info): bool
    {
        $data = [
            'name' => $blank_info['username'],
            'curp' => 'ND',
            'orderNo' => $oinfo['id'],
            'monto' => $oinfo['num'],
            'bankNum' => $blank_info['cardnum'],
            'bankCode' => $blank_info['bank_code'],
            'bankType' => 'CLABE',
            'notifyUrl' => url('/index/callback/payout', [
                'gateway' => (new \ReflectionClass(__CLASS__))->getShortName(),
            ], true, true),
        ];
        $res = $this->_post(self::PAYOUT_URL, $data, 'json', [
            'Authorization:' . $this->_make_payout_sign($data),
            'AppId:' . $this->get_mch_id()
        ]);
        $res = json_decode($res, true);
        if (isset($res['code']) && $res['code'] == 200) {
            return true;
        }
        $this->_payout_msg = is_array($res) ? json_encode($res,JSON_UNESCAPED_UNICODE) : $res;
        return false;
    }

    //["status"=>"SUCCESS","oid"=>"订单号","amount"=>"支付金额"]
    public function parsePayoutCallback($type = ''): array
    {
        $put = file_get_contents('php://input');
        $data = json_decode($put, true);
        if (empty($data)) $data = $_POST;
        if (empty($data['sign'])) {
            exit();
        }
        $authorization = $_SERVER["HTTP_AUTHORIZATION"];
        $firstMd5 = md5($put);
        $authorizations = md5($firstMd5 . $this->get_secret());
        if ($authorizations != $authorization) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        $signarr = [];
        $signarr["orderNo"] = $data["orderNo"];
        $signarr["status"] = $data["status"];
        $signarr["key"] = $this->get_mch_id();
        ksort($signarr);
        $verify = "";
        foreach ($signarr as $x_value) $verify = $verify . $x_value;
        $sign = strtolower(md5($verify));
        if ($sign != $data["sign"]) {
            return ['status' => 'FAIL', 'msg' => '签名错误', 'data' => $data];
        }
        $oinfo = Db::name('xy_deposit')->find($data['orderNo']);
        if (empty($oinfo)) return ['status' => 'FAIL', 'msg' => '订单不存在', 'data' => $data];
        return [
            'status' => ($data['status'] == 'Success' ? 'SUCCESS' : 'FAIL'),
            'oid' => $data['orderNo'],
            'amount' => $oinfo['num'],
            'msg' => !empty($data['Success']) ? $data['Success'] : '',
            'data' => $data
        ];
    }

    public function parsePayoutCallbackFail()
    {
        echo "error";
    }

    public function parsePayoutCallbackSuccess()
    {
        echo '{"code": 200}';
    }


    /**
     * 创建签名
     * @param $data array  数据包
     * @return string
     */
    private function _make_sign(array $data)
    {
        $firstMd5 = md5(json_encode($data));
        return md5($firstMd5 . $this->get_secret());
    }

    private function _make_payout_sign(array $data)
    {
        $firstMd5 = md5(json_encode($data));
        return md5($firstMd5 . $this->get_secret());
    }
}