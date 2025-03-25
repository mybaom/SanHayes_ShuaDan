<?php

namespace app\index\pay;

use think\Db;

class Ceanpay
{
    const PAY_URL = 'https://www.foxconny.com/api/outer/collections/addOrderByLndia';
    const PAYOUT_URL = 'https://www.foxconny.com/api/outer/merwithdraw/addPaid';

    public static function instance(): Ceanpay
    {
        return new self();
    }

    public function get_mch_id(): string
    {
        return config('pay.ceanpay.mch_id');
    }

    public function get_secret(): string
    {
        return config('pay.ceanpay.secret');
    }

    public function get_payout_secret(): string
    {
        return config('pay.ceanpay.secret');
    }

    //发起代收订单
    public function createPay($order)
    {
        $oUser = Db::name('xy_users')->where('id', $order['uid'])->find();
        $data = [];
        $data['name'] = $oUser['username'];
        $data['mobile'] = $oUser['tel'];
        $data['email'] = $oUser['tel'] . '@' . request()->rootDomain();
        $data['merordercode'] = $order['sn'];
        $data['amount'] = $order['amount'];
        $data['callbackurl'] = url('/index/callback/recharge_ceanpay', '', true, true);
        $data['notifyurl'] = url('/index/my/index', '', true, true);
        $data['paycode'] = config('pay.ceanpay.pay_type');
        $data['starttime'] = time() . '000';
        $data['ipaddr'] = request()->ip();
        $data['code'] = $this->get_mch_id();
        $data['signs'] = $this->_make_sign($data);
        $res = $this->_post(self::PAY_URL, $data);
        $ret = ['respCode' => 'ERROR', 'payInfo' => '', 'data' => $res];
        if (!empty($res['success']) && $res['code'] == 200) {
            $ret['respCode'] = 'SUCCESS';
            $ret['payInfo'] = $res['data']['checkstand'];
        }
        return $ret;
    }

    /**
     * 创建付款订单
     * @param $oinfo array  申请提现记录 对应xy_deposit表
     * @param $blank_info array  银行卡信息 对应xy_bankinfo表
     * @return bool
     */
    public function create_payout($oinfo, $blank_info)
    {
        $data = [
            'code' => $this->get_mch_id(),
            'merissuingcode' => $oinfo['id'],
            'amount' => $oinfo['real_num'],
            'mobile' => $blank_info['tel'],
            'accountname' => $blank_info['username'],
            'cardnumber' => $blank_info['cardnum'],
            'bankname' => $blank_info['bank_code'],
            'ifsc' => $blank_info['document_id'],
            'email' => $blank_info['tel'] . '@GMAIL.COM',
            'starttime' => time() . '000',
            'notifyurl' => url('/index/callback/payout_ceanpay', '', true, true),
        ];
        $data['signs'] = $this->_make_payout_sign($data);
        $res = $this->_post(self::PAYOUT_URL, $data);
        if (!empty($res['success']) && $res['code'] == 200) {
            return true;
        }
        $logFile = APP_PATH . 'ceanpay_create_payout.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . json_encode($data) . "\n", FILE_APPEND);
        file_put_contents($logFile, 'ERROR:  ' . json_encode($res) . "\n", FILE_APPEND);
        $this->_payout_msg = !empty($res['msg']) ? $res['msg'] : '';
        return false;
    }

    private function _post($payUrl, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $payUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $header = array('Content-Type: multipart/form-data'); //请求头记得变化-不同的上传方式
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        if (curl_error($ch)) return null;
        curl_close($ch);
        return json_decode($output, true);
    }

    /**
     * 支付回掉- 验证签名
     * @param $data array  数据包
     * @return bool
     */
    public function check_callback_sign(array $data): bool
    {
        $sign = $data['sign'];
        unset($data['sign']);
        $s = $this->_make_callback_sign($data);
        return $s == $sign;
    }

    /**
     * 创建签名
     * @param $data array  数据包
     * @return string
     */
    private function _make_sign(array $data)
    {
        $signStr = 'code=' . $data['code'] . '&merordercode=' . $data['merordercode'] .
            '&notifyurl=' . $data['notifyurl'] . '&callbackurl=' . $data['callbackurl'] .
            '&amount=' . $data['amount'] . '&key=' . $this->get_secret();
        return strtoupper(md5($signStr));
    }

    private function _make_callback_sign(array $data)
    {
        $signStr = 'code=' . $data['code'] . '&key=' . $this->get_secret() .
            '&terraceordercode=' . $data['terraceordercode'] . '&merordercode=' . $data['merordercode'] .
            '&createtime=' . $data['createtime'] . '&chnltrxid=' . $data['chnltrxid'];
        return strtoupper(md5($signStr));
    }


    public function check_payout_sign(array $data): bool
    {
        $sign = $data['signs'];
        $signStr = 'accountname=' . $data['accountname'] . '&amount=' . $data['amount'] .
            '&bankname=' . $data['bankname'] . '&cardnumber=' . $data['cardnumber'] .
            '&code=' . $data['code'] . '&ifsc=' . $data['ifsc'] .
            '&issuingcode=' . $data['issuingcode'] . '&merissuingcode=' . $data['merissuingcode'] .
            '&message=' . $data['message'] . '&returncode=' . $data['returncode'] .
            '&starttime=' . $data['starttime'] . '&key=' . $this->get_secret();
        $s = strtoupper(md5($signStr));
        return $s == $sign;
    }

    private function _make_payout_sign(array $data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }
        return strtoupper(md5($str . 'key=' . $this->get_payout_secret()));
    }
}