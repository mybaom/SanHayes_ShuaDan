<?php
namespace app\index\controller;

use library\Controller;
use think\Db;

class Wallet extends Base
{
    //   /index/wallet/index
    public function index()
    {
       
          $uid = session('user_id');
        $info = db('xy_bankinfo')->where('uid', $uid)->find();
       $ka = Db::name('user_wallet')->where('uid', $uid)->find();
      if(!$ka){
            $ka=['full_name'=>'','address'=>'','phone'=>''];
      }
         $this->ka = $ka;
     if(!$info){
         $info=['bank_code'=>'','username'=>'','cardnum'=>'','tel'=>'','document_type'=>'','document_id'=>'','qq'=>''];
     }
        $uinfo = db('xy_users')->find($uid);
         $bank_list =  [
       "IDPT0001"=>"Gcash",
       "IDPT0002"=>"BDOUNIBANKINC",
	"IDPT0003"=>"LAND BANK OF THE PHILIPPINES",
	"IDPT0004"=>"BANK OF THE PHILISLANDS",
	"IDPT0005"=>"METROPOLITAN BANK & TCO",
	"IDPT0006"=>"PHIL NATIONAL BANK",
	"IDPT0007"=>"CHINA BANKING CORP",
	"IDPT0008"=>"RIZAL COMML BANKING CORP",
	"IDPT0009"=>"DEVELOPMENT BANK OF THE PHIL",
	"IDPT0010"=>"SECURITY BANK CORP",
     	"IDPT0011"=>"UNION BANK OF THE PHILS",
	"IDPT0012"=>"EAST WEST BANKING CORP",

       
       
       
       
       
       
//      	"GCASH"=>"Qeapay Bank",
// 	"IDPT0002"=>"DCB Bank",
// 	"IDPT0003"=>"Federal Bank",
// 	"IDPT0004"=>"HDFC Bank",
// 	"IDPT0005"=>"Punjab National Bank",
// 	"IDPT0006"=>"Indian Bank",
// 	"IDPT0007"=>"ICICI Bank",
// 	"IDPT0008"=>"Syndicate Bank",
// 	"IDPT0009"=>"Karur Vysya Bank",
// 	"IDPT0010"=>"Union Bank of India",
// 	"IDPT0011"=>"Kotak Mahindra Bank",
// 	"IDPT0012"=>"IDFC First Bank",
// 	"IDPT0013"=>"Andhra Bank",
// 	"IDPT0014"=>"Karnataka Bank",
// 	"IDPT0015"=>"icici corporate bank",
// 	"IDPT0016"=>"Axis Bank",
// 	"IDPT0017"=>"UCO Bank",
// 	"IDPT0018"=>"South Indian Bank",
// 	"IDPT0019"=>"Yes Bank",
// 	"IDPT0020"=>"Standard Chartered Bank",
// 	"IDPT0021"=>"State Bank of India",
// 	"IDPT0022"=>"Indian Overseas Bank",
// 	"IDPT0023"=>"Bandhan Bank",
// 	"IDPT0024"=>"Central Bank of India",
// 	"IDPT0025"=>"Bank of Baroda",
// 	"IDPT0026"=>"Punjab National Bank",
// 	"IDPT0027"=>"Paytm payment bank",
// 	"IDPT0031"=>"Airtel payments bank limited",
// 	"IDPT0033"=>"Equitas small finance bank",
// 	"IDPT0034"=>"Bank Of India",
// 	"IDPT0036"=>"Kerala Gramin Bank",
// 	"IDPT0037"=>"IDBI bank",
// 	"IDPT0038"=>"Citi bank",
// 	"IDPT0039"=>"City Union Bank",
// 	"IDPT0040"=>"Post payments Bank",
// 	"IDPT0041"=>"bank of maharashtra",
// 	"IDPT0042"=>"Ujjivian bank",
// 	"IDPT0043"=>"IndusInd ban",
// 	"IDPT0044"=>"card Lakshmi vilas bank",
// 	"IDPT0045"=>"Jharkhand rajya gramin Bank",
// 	"IDPT0046"=>"SVC CO-OPERATIVE BANK LTD",
// 	"IDPT0047"=>"INDUSLND BANK",
// 	"IDPT0048"=>"Pragathi gramina",
// 	"IDPT0049"=>"Post Bank of India"
            ];
        $this->assign('bank_list', $bank_list);
        
        $po = Db::name('user_wallet')->where('uid', session('user_id'))->find();
        $uid= session('user_id');
        if (empty($po)){
            model('index/UserWallet')
                ->insert("","",$uid,"");
            $po = Db::name('user_wallet')->where('uid', session('user_id'))->find();
        }
        $this->assign('po', $po);
        $this->info = $info;
        //查询是否已经绑定了银行卡 已经绑定跳转支付  没有绑定跳转绑定
        $yhk = Db::name('xy_bankinfo')->where('uid', session('user_id'))->find();
        
        if($yhk){//已经绑定提现页面
            $user = Db::name('xy_users')->where('id', session('user_id'))->find();
            $user['tel'] = substr_replace($user['tel'], '****', 3, 4);
            // $bank = Db::name('xy_bankinfo')->where(['uid' => session('user_id')])->find();
             $bank = Db::name('user_wallet')->where('uid', session('user_id'))->find();
         
            $bank['cardnum'] = substr_replace($bank['address'], '****', 7, 7);
            $this->assign('info', $bank);
            $this->assign('user', $user);
            //提现限制
            $level = $user['level'];
            !$user['level'] ? $level = 0 : '';
            $ulevel = Db::name('xy_level')->where('level', $level)->find();
            $this->usdt_pay_info = Db::name('xy_pay')->where('name2', 'bit')->find();
            $this->shouxu = $ulevel['tixian_shouxu'];
            $this->desc_info = Db::name('xy_index_msg')->where('id', 14)->value('content');
            $csURL = Db::name('system_config')->where('id', 7)->value('value');//客服地址
            $this->csURL=$csURL;
          
             return $this->fetch('ctrl/deposit');
             
        }else{//绑定页面
            return $this->redirect('/index/my/bind_bank');
             return $this->fetch('my/bind_bank');
        }


       
    }
    public function saveUserWallet(){
        $uid= session('user_id');
        $address = input('post.address/s', '');
        $phone = input('post.phone/s', '');
        $full_name = input('post.full_name/s', '');

        $res=Db::name('user_wallet')->where('uid', $uid)->update([
            'address' => $address,
            'phone' => $phone,
            'full_name'=>$full_name,
            'create_date' => date("Y-m-d H:m:i")
        ]);

        $jo["code"]=0;


        return json($jo);

    }

}