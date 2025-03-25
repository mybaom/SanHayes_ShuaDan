<?php

namespace app\index\controller;

use app\index\pay\Luxpag;
use app\index\pay\Qeapay;
use app\index\pay\Sepropay;
use app\index\pay\Sixgpay;
use app\index\pay\Speedypay; 
use app\index\pay\Tokpay;
use app\index\pay\Mbitpay; 
use app\index\pay\Yulupay;
use think\facade\Config;
use think\Controller;
use think\Db;
use think\Exception;
use think\Request;

class Pay extends Base
{

    public function index()
    {

    }

    private function _op($payType)
    {
        $vip_id = input('get.vip_id/s', '');
        $vip_info = '';
        if ($vip_id) {
            $vip_info = Db::name('xy_level')->where('id', $vip_id)->find();
        }
        $num = input('get.num/s', '');
        $type = input('get.type/s', '');
        $uid = session('user_id');
        $uinfo = Db::name('xy_users')->field('pwd,salt,tel,username')->find($uid);
        $SN = getSn('SY');

        $pay_com = Db::name('xy_pay')->where('name2', $payType)->value('pay_commission');
        $pay_com = $pay_com ? floatval($pay_com) : 0;
        $dbData = [
            'id' => $SN,
            'uid' => session('user_id'),
            'tel' => $uinfo['tel'],
            'real_name' => $uinfo['username'],
            'pic' => '',
            'num' => $num,
            'addtime' => time(),
            'pay_name' => $payType,
            'pay_com' => $pay_com,
        ];
        if ($vip_info) {
            $num = $vip_info['num'];
            $dbData['num'] = $vip_info['num'];
            $dbData['is_vip'] = 1;
            $dbData['level'] = $vip_info['level'];
        }
        $dbRes = Db::name('xy_recharge')->insert($dbData);
        if (!$dbRes) {
            $this->showMessage(lang('czsbqshcs'));
        }
        return ['uid' => session('user_id'), 'sn' => $SN, 'amount' => $num];
    }

    public function luxpag()
    {
        $op_data = $this->_op('luxpag');
        $data = [];
        $data['timestamp'] = date('Y-m-d H:i:s');
        $data['out_trade_no'] = $op_data['sn'];
        $data['order_currency'] = 'BRL';
        $data['order_amount'] = floatval($op_data['amount']);
        $data['subject'] = 'user recharge';
        $data['content'] = 'user recharge UID:' . session('user_id');
        $data['trade_type'] = 'WEB';
        $data['notify_url'] = url('/index/callback/recharge_luxpag', '', true, true);
        $data['return_url'] = url('/index/my/index', '', true, true);
        $data['buyer_id'] = session('user_id');
        $data['version'] = "2.0";
        $resData = Luxpag::instance()->create_order($data);
        if (!empty($resData['code']) && $resData['code'] == '10000') {
            header('Location:' . $resData['web_url']);
        } else {
            $this->showMessage(lang('czsbqshcs'));
        }
        die;
    }

    public function sixgpay()
    {
        $op_data = $this->_op('sixgpay');
        $this->data = Sixgpay::instance()->create_order([
            'mch_order_no' => $op_data['sn'],
            'pay_type' => '4', //查阅后台商户支付通道
            'notify_url' => url('/index/callback/recharge_sixpag', '', true, true),
            'goods_name' => 'user recharge',
            'order_date' => date('Y-m-d H:i:s'),
            'trade_amount' => $op_data['amount'],
            'currency' => 'BRL', //货币代码商户后台查看
            'page_url' => url('/index/my/index', '', true, true),
            'payer_ip' => \think\facade\Request::ip(),
        ]);
        $this->payUrl = Sixgpay::instance()->get_pay_url();
        $this->fetch();
    }

    public function speedypay()
    {
        $op_data = $this->_op('speedypay');
        $resData = Speedypay::instance()->create_order([
            'orderId' => $op_data['sn'],
            'amount' => $op_data['amount'],
            'notifyUrl' => url('/index/callback/recharge_speedypay', '', true, true),
        ]);
        if (isset($resData['status']) && $resData['status'] == 0) {
            header('Location:' . $resData['data']['payUrl']);
        } else {
            $this->showMessage(lang('czsbqshcs'));
        }
    }

    public function user_ok()
    {
        $realname = $this->request->post('realname/s', '');
        $document_id = $this->request->post('document_id/s', '');
        $sn = $this->request->post('sn/s', '');
        $tNo = $this->request->post('tNo/s', '');
        
        $pic = $this->request->post('pic/s', '');
        $type = $this->request->post('type/s', '');
        $pay_address = $this->request->post('pay_address/s', '');
        
       
        
        $cc = Db::name('xy_recharge')->where('pay_return', $tNo)->find();
       
        if ($cc) {
            //return $this->error(lang('recharge_u_hash_ext'));
        }
        $recharge = Db::name('xy_recharge')->where('id', $sn)->find();
        if (!$recharge) {
            return $this->error(lang('recharge_u_no_order'));
        }
        //获取usdt转换比例  pay['mch_id']
        $pay = Db::name('xy_pay')->find(8);
        if($pay){
          $pric = $recharge['num'] * $pay['mch_id'];  
        }else{
             $pric = $recharge['num'];
        }
        
         
         
        if ($recharge['status2'] == 1) {
            return $this->error(lang('qbycftj'));
        }
        
        
        if (is_image_base64($pic)){
                 $pic = '/' . $this->upload_base64('xy', $pic);  //调用图片上传的方法
            } else{
               return json(['code' => 1, 'info' => lang('tpgscw')]); 
            }
           
               
                
        $res = Db::name('xy_recharge')->where('id', $sn)->update([
            'status2' => 1,
            'user_realname' => $realname,
            'user_document_id' => $document_id,
            'pay_address' => $pay_address,
            'pay_return' => $tNo,
            'pic'=>$pic,
            'type'=>$type,
            'num'=>$pric
        ]);
        
        if (!$res) {
            $this->error(lang('czsb'));
        }
        $this->success(lang('with_q_ok'));
    }

    public function pix($sn = '')
    {
        if ($sn) {
            $recharge = Db::name('xy_recharge')->where('id', $sn)->find();
            $this->op_data['sn'] = $recharge['id'];
            $this->op_data['amount'] = $recharge['num'];
            if ($recharge['status2'] == 1) {
                header('Location:' . url('/index/index/index'));
                exit;
            }
        } else {
            $this->op_data = $this->_op('pix');
            header('Location:' . url('/index/pay/pix', ['sn' => $this->op_data['sn']]));
            exit;
        }
        $this->pay_info = Db::name('xy_pay')->where('name2', 'pix')->find();
        $this->fetch();
    }

    public function mbit($sn = '')
    {
        #num=100&id=81&payer_name=&payer_bank=&payer_cardno=&payer_name=&payer_mobile=&payer_upi=&payer_email=&vip_id=undefined&type=0
        $num =  input('get.num/d', ''); 
        
        
        $op_data = $this->_op('mbit');
        
         $resData = Mbitpay::instance()->create_order([
            'paytype' => 'PIX',
            'orderno' => $op_data['sn'],
            'orderamount' => $op_data['amount'],
            'notifyurl' => url('/index/callback/recharge_mbitpay', '', true, true),
            'returnurl' => url('/index/my/index', '', true, true),
        ]);
        
     
    }
    public function bit($sn = '')
    {
        if ($sn) {
            $recharge = Db::name('xy_recharge')->where('id', $sn)->find();
            if (!$recharge || $recharge['status'] > 1) {
                exit();
            }
            $this->op_data['sn'] = $recharge['id'];
            $this->op_data['amount'] = $recharge['num'];
            if ($recharge['status2'] == 1) {
                header('Location:' . url('/index/index/index'));
                exit;
            }
        } else {
            $this->op_data = $this->_op('bit');
            header('Location:' . url('/index/pay/bit', ['sn' => $this->op_data['sn']]));
            exit;
        }
        $this->desc_info = Db::name('xy_index_msg')->where('id', 15)->value('content');
        $this->pay_info = Db::name('xy_pay')->where('name2', 'bit')->find();
         $arr=explode("\r\n",$this->pay_info['usercode']);
         $ran=rand(0,count($arr)-1);
         $this->pay_info['usercode']=$arr[$ran];
        $recharge = Db::name('xy_recharge')->where('id', $sn)->find();
        if ($recharge['num2'] == 0) {
            $this->op_data['amount2'] = number_format($this->op_data['amount'] * $this->pay_info['mch_id'], 2);
            //更新订单金额
            Db::name('xy_recharge')
                ->where('id', $this->op_data['sn'])
                ->update([
                    'num2' => $this->op_data['amount2'],
                ]);
        } else $this->op_data['amount2'] = $recharge['num2'];
        $this->fetch();
    }

    public function tokpay()
    {
        $op_data = $this->_op('tokpay');
        $resData = Tokpay::instance()->create_order([
            'paytype' => 'PIX',
            'orderno' => $op_data['sn'],
            'orderamount' => $op_data['amount'],
            'notifyurl' => url('/index/callback/recharge_tokpay', '', true, true),
            'returnurl' => url('/index/my/index', '', true, true),
        ]);
        $this->data = $resData;
        $this->payUrl = Tokpay::instance()->get_pay_url();
        $this->fetch();
    }

    public function sepropay()
    {
        $op_data = $this->_op('sepropay');
        $oUser = Db::name('xy_users')->where('id', $op_data['uid'])->find();
        $resData = Sepropay::instance()->create_order([
            'goods_name' => lang('log_cz'),
            'mch_order_no' => $op_data['sn'],
            'trade_amount' => $op_data['amount'],
            'payer_phone' => str_replace('+', "", config('lang_tel_pix')) . '' . $oUser['tel'],
            'order_date' => date('Y-m-d H:i:s'),
            'notify_url' => url('/index/callback/recharge_sepropay', '', true, true),
            'page_url' => url('/index/my/index', '', true, true),
        ]);
        if ($resData['respCode'] != 'SUCCESS') {
            return $this->error(lang('czsbqshcs'), $resData);
        }
        header('Location:' . $resData['payInfo']);
        exit;
    }

    public function yulupay()
    {
        $op_data = $this->_op('yulupay');
        $resData = Yulupay::instance()->createPay([
            'name' => 'd',
            'email' => 'a@a.com',
            'phone' => 1,
            "mer_order_no" => $op_data['sn'],
            "amount" => $op_data['amount'],
            'pageUrl' => url('/index/my/index', '', true, true),
        ]);
        if ($resData['code'] != '1000') {
            return $this->error(lang('czsbqshcs'));
        }
        header('Location:' . $resData['url']);
        exit;
    }

    public function qeapay()
    {
        $op_data = $this->_op('qeapay');
        $resData = Qeapay::instance()->createPay([
            'goods_name' => lang('log_cz'),
            'mch_order_no' => $op_data['sn'],
            'trade_amount' => $op_data['amount'],
            'order_date' => date('Y-m-d H:i:s'),
            'notify_url' => url('/index/callback/recharge_qeapay', ['type' => input('type/d', 0)], true, true),
            'page_url' => url('/index/my/index', '', true, true),
        ]);
        if ($resData['respCode'] != 'SUCCESS') {
            return $this->error(lang('czsbqshcs'), $resData);
        }
        header('Location:' . $resData['payInfo']);
        exit;
    }

    /**
     * 空操作 用于显示错误页面
     */
    public function _empty($name)
    {
        $op_data = $this->_op($name);
        try {
            $className = "\\app\\index\\pay\\" . $name;
            $pay = new $className();
        } catch (Exception $e) {
            exit();
        }
        $resData = $pay->createPay($op_data);
        if ($resData['respCode'] != 'SUCCESS') {
            echo '<h4 style="text-align: center">' . lang('czsbqshcs') . '</h4>' . "\n";
            echo '<div style="display: none">' . json_encode($resData) . '</div>';
            exit;
            //return $this->error(lang('czsbqshcs'), $resData);
        }
        if (!empty($resData['respType']) && $resData['respType'] == 'code') {
            Db::name('xy_recharge')
                ->where('id', $op_data['sn'])
                ->update([
                    'pay_type' => $resData['payInfo']
                ]);
            $this->showCode($resData['payInfo'], $resData);
        } else if (!empty($resData['respType']) && $resData['respType'] == 'blank_code') {
            Db::name('xy_recharge')
                ->where('id', $op_data['sn'])
                ->update([
                    'pay_type' => $resData['payInfo']
                ]);
            $this->data = $resData;
            $this->fetch('bank_code');
        } else {
            header('Location:' . $resData['payInfo']);
        }
        exit;
    }

    private function showCode($code, $payData)
    {
        $this->code = $code;
        $this->payData = $payData;
        $this->fetch();
    }
}