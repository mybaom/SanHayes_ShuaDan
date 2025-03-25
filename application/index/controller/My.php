<?php

namespace app\index\controller;

use think\App;
use think\Controller;
use think\Exception;
use think\Request;
use think\Db;
use think\View;

class My extends Base
{
    protected $msg = ['__token__' => 'post error'];

    /**
     * 首页
     */
    public function index()
    {
        $this->info = db('xy_users')->field('username,tel,level,id,agent_id,headpic,balance,freeze_balance,lixibao_balance,invite_code,show_td')->find(session('user_id'));
        $this->lv3 = [0, config('vip_3_num')];
        $this->lv2 = [0, config('vip_2_num')];
        $this->sell_y_num = db('xy_convey')->where('status', 1)->where('uid', session('user_id'))->sum('commission');

        $level = $this->info['level'];
        !$level ? $level = 0 : '';

        $this->level_name = db('xy_level')->where('level', $level)->value('name');

        $this->info['lixibao_balance'] = number_format($this->info['lixibao_balance'], 2);

        $this->rililv = config('lxb_bili') * 100 . '%';
        $this->lxb_shouyi = db('xy_lixibao')->where('status', 1)->where('uid', session('user_id'))->sum('num');
        $uinfo = db('xy_users')->where('id', session('user_id'))->find();
        $level_name = 'Free';
        $level1208=$uinfo['level'];


        if (!empty($uinfo['level'])||$level1208==0) {
            //$order_num = db('xy_level')->where('level', $uinfo['level'])->value('order_num');
            $level_name = db('xy_level')->where('level', $uinfo['level'])->value('name');
            //$level_nums = db('xy_level')->where('level', $uinfo['level'])->value('num');
        }
        $this->level_name = $level_name;

        $this->jinri=Db::name('xy_balance_log')->where('uid',session('user_id'))->where('type','in',[3,6])->where('addtime','>=',strtotime(date('Y-m-d 00:00:00')))->sum('num');
        $msg_list = Db::name('xy_message')
            ->field('id,content')
            ->where('uid', session('user_id'))
            ->where('is_read', 1)
            ->order('id desc')
            ->select();
        $this->msg = '';
        if ($msg_list) {
            Db::name('xy_message')
                ->where('uid', session('user_id'))
                ->where('is_read', 1)
                ->update([
                    'is_read' => 2,
                    'read_time' => time()
                ]);
            foreach ($msg_list as $v) {
                $this->msg .= str_replace("'", ' ', $v['content']) . '<br>';
            }
        }

        $csURL = Db::name('system_config')->where('id', 7)->value('value');//客服地址
        if($this->info['agent_id']>0){
            $kf=Db::name('xy_cs')->where('uid', $this->info['agent_id'])->select();
            // $kf = Db::name('system_user')->where('id', $this->info['agent_id'])->value('chats');//客服地址
            if($kf){
                if(count($kf)==1){
                    $csURL=$kf[0]['url'];
                }else{
                    $csURL=$kf;
                }
                
            }
        }
        $this->csURL=$csURL;
        return $this->fetch();
    }

    /**
     * 获取收货地址
     */
    public function get_address()
    {
        $id = session('user_id');
        $data = db('xy_member_address')->where('uid', $id)->select();
        if ($data)
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data]);
        else
            return json(['code' => 1, 'info' => lang('zwsj')]);
    }

    public function reload()
    {
        $id = session('user_id');;
        $user = db('xy_users')->find($id);

        $n = ($id % 20);

        $dir = './upload/qrcode/user/' . $n . '/' . $id . '.png';
        if (file_exists($dir)) {
            unlink($dir);
        }

        $res = model('admin/Users')->create_qrcode($user['invite_code'], $id);
        if (0 && $res['code'] !== 0) {
            return $this->error(lang('qqcw'));
        }
        return $this->success(lang('czcg'));
    }


    /**c
     * 添加收货地址
     */
    public function add_address()
    {
        if (request()->isPost()) {
            $uid = session('user_id');
            $name = input('post.name/s', '');
            $tel = input('post.tel/s', '');
            $address = input('post.address/s', '');
            $area = input('post.area/s', '');
            $token = input("token");//获取提交过来的令牌
            $data = ['__token__' => $token];
            $validate = \Validate::make($this->rule, $this->msg);
            if (!$validate->check($data)) {
                return json(['code' => 1, 'info' => $validate->getError()]);
            }
            $data = [
                'uid' => $uid,
                'name' => $name,
                'tel' => $tel,
                'area' => $area,
                'address' => $address,
                'addtime' => time()
            ];
            $tmp = db('xy_member_address')->where('uid', $uid)->find();
            if (!$tmp) $data['is_default'] = 1;
            $res = db('xy_member_address')->insert($data);
            if ($res)
                return json(['code' => 0, 'info' => lang('czcg')]);
            else
                return json(['code' => 1, 'info' => lang('czsb')]);
        }
        return json(['code' => 1, 'info' => lang('qqcw')]);
    }

    /**
     * 编辑收货地址
     */
    public function edit_address()
    {
        if (request()->isPost()) {
            $uid = session('user_id');
            $name = input('post.shoujianren/s', '');
            $tel = input('post.shouhuohaoma/s', '');
            $address = input('post.address/s', '');

            $area = input('post.area/s', '');


            $ainfo = db('xy_member_address')->where('uid', $uid)->find();
            if ($ainfo) {
                $res = db('xy_member_address')
                    ->where('id', $ainfo['id'])
                    ->update([
                        'uid' => $uid,
                        'name' => $name,
                        'tel' => $tel,
                        'area' => $area,
                        'address' => $address,
                        //'addtime'   => time()
                    ]);
            } else {
                $res = db('xy_member_address')
                    ->insert([
                        'uid' => $uid,
                        'name' => $name,
                        'tel' => $tel,
                        'area' => $area,
                        'address' => $address,
                        'addtime' => time()
                    ]);
            }

            if ($res)
                return json(['code' => 0, 'info' => lang('czcg')]);
            else
                return json(['code' => 1, 'info' => lang('czsb')]);
        } elseif (request()->isGet()) {
            $id = session('user_id');
            $this->info = db('xy_member_address')->where('uid', $id)->find();

            return $this->fetch();
        }
    }

    public function team()
    {
        $uid = session('user_id');
        //$this->info = db('xy_member_address')->where('uid',$id)->find();
        $uids = model('admin/Users')->child_user($uid, 5);
        array_push($uids, $uid);
        $uids ? $where[] = ['uid', 'in', $uids] : $where[] = ['uid', 'in', [-1]];

        $datum['sl'] = count($uids);
        $datum['yj'] = db('xy_convey')->where('status', 1)->where($where)->sum('num');
        $datum['cz'] = db('xy_recharge')->where('status', 2)->where($where)->sum('num');
        $datum['tx'] = db('xy_deposit')->where('status', 2)->where($where)->sum('num');


        //
        $uids1 = model('admin/Users')->child_user($uid, 1);
        $uids1 ? $where1[] = ['sid', 'in', $uids1] : $where1[] = ['sid', 'in', [-1]];
        $datum['log1'] = db('xy_balance_log')->where('uid', $uid)->where($where1)->where('f_lv', 1)->sum('num');

        $uids2 = model('admin/Users')->child_user($uid, 2);
        $uids2 ? $where2[] = ['sid', 'in', $uids2] : $where2[] = ['sid', 'in', [-1]];
        $datum['log2'] = db('xy_balance_log')->where('uid', $uid)->where($where2)->where('f_lv', 2)->sum('num');

        $uids3 = model('admin/Users')->child_user($uid, 3);
        $uids3 ? $where3[] = ['sid', 'in', $uids3] : $where3[] = ['sid', 'in', [-1]];
        $datum['log3'] = db('xy_balance_log')->where('uid', $uid)->where($where3)->where('f_lv', 3)->sum('num');


        $uids5 = model('admin/Users')->child_user($uid, 5);
        $uids5 ? $where5[] = ['sid', 'in', $uids5] : $where5[] = ['sid', 'in', [-1]];
        $datum['yj2'] = db('xy_convey')->where('status', 1)->where($where)->sum('commission');
        $datum['yj3'] = db('xy_balance_log')->where('uid', $uid)->where($where5)->where('type', 6)->sum('num');;


        $this->info = $datum;

        return $this->fetch();
    }

    //  /index/my/caiwu
    public function caiwu()
    {
        $id = session('user_id');
        $day = input('get.day/s', '');
        $where = [];
        // if ($day) {
        //     $start = strtotime("-$day days");
        //     $where[] = ['addtime', 'between', [$start, time()]];
        // }

        // $start = input('get.start/s', '');
        // $end = input('get.end/s', '');
        // if ($start || $end) {
        //     $start ? $start = strtotime($start) : $start = strtotime('2020-01-01');
        //     $end ? $end = strtotime($end . ' 23:59:59') : $end = time();
        //     $where[] = ['addtime', 'between', [$start, $end]];
        // }


        // $this->start = $start ? date('Y-m-d', $start) : '';
        // $this->end = $end ? date('Y-m-d', $end) : '';

        // $this->type = $type = input('get.type/d', 0);

        // if ($type == 1) {
        //     $where['type'] = 7;
        // } elseif ($type == 2) {
        //     $where['type'] = 1;
        // }


        $this->_query('xy_balance_log')
            ->where('uid', $id)->where($where)->order('id desc')->page();
        //var_dump($_REQUEST);die;
    }


    public function headimg()
    {
        $uid = session('user_id');
        if (request()->isPost()) {
            $username = input('post.pic/s', '');
            $res = db('xy_users')->where('id', session('user_id'))->update(['headpic' => $username]);
            if ($res !== false) {
                return json(['code' => 0, 'info' => lang('czcg')]);
            } else {
                return json(['code' => 1, 'info' => lang('czsb')]);
            }
        }
        $this->info = db('xy_users')->find($uid);
        return $this->fetch();
    }

    public function bind_bank()
    {
        $id = input('post.id/d', 0);
        $uid = session('user_id');
        $info = db('xy_bankinfo')->where('uid', $uid)->find();
         $ka = Db::name('user_wallet')->where('uid', $uid)->find();
        
     if(!$info){
         $info=['bank_code'=>'','username'=>'','cardnum'=>'','tel'=>'','document_type'=>'','document_id'=>'','qq'=>''];
     }
        $uinfo = db('xy_users')->find($uid);
         $bank_list =  [
                   "GCASH"=>"Gcash",
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
             
             
             
             
//              "IDPT0001"=>"Gcash",
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

        // $bank_list = $this->getBankList();

        $pwd2 = input('post.paypassword/s', '');
        $user_info = db('xy_users')->field('pwd2,salt2')->find(session('user_id'));
        if ($user_info['pwd2'] == '') {
            header('Location:' . url('/index/ctrl/edit_pwd2'));
            exit;
        }

        if (request()->isPost()) {
            //验证支付密码
            if ($user_info['pwd2'] != sha1($pwd2 . $user_info['salt2'] . config('pwd_str'))) return json(['code' => 1, 'info' => lang('pass_error')]);


            $username = input('post.username/s', '');
            //$bankname = input('post.bankname/s', '');
            $cardnum = input('post.id_number/s', '');
            $document_type = input('post.document_type/s', '');
            $document_id = input('post.document_id/s', '');
            $bank_code = input('post.bank_code/s', '');
            $bank_branch = input('post.bank_branch/s', '');
            $bank_type = input('post.bank_type/s', '');
            $account_digit = input('post.account_digit/s', '');
            //$site = input('post.document_type/s', '');
            $qq = input('post.qq/s', '');
            $tel = input('post.tel/s', '');
            $address = input('post.address/s', '');

            //同一姓名和卡号只绑定一次
            $res = db('xy_bankinfo')
                ->where('username', $username)
                ->where('cardnum', $cardnum)
                ->find();
            if ($res && $res['uid'] != $uid) {
                return json(['code' => 1, 'info' => lang('bind_bank_err')]);
            }
            $data = array(
                'username' => $username,
                'bankname' => $bank_code ? $bank_list[$bank_code] : '',
                'cardnum' => $cardnum,
                'document_type' => $document_type,
                'document_id' => $document_id,
                'bank_code' => "GCASH",
                'bank_branch' => $bank_branch,
                'bank_type' => $bank_type,
                'account_digit' => $account_digit,
                'wallet_tel' => input('post.wallet_tel/s', ''),
                'wallet_document_id' => input('post.wallet_document_id/s', ''),
                'wallet_document_type' => input('post.wallet_document_type/s', ''),
                'site' => '',
                'address' => $address,
                'qq' => $qq,
                'tel' => $tel,
                'status' => 1
            );
            if (!empty($info['id'])) {
                $res = db('xy_bankinfo')->where('uid', $uid)->update($data);
            } else {
                $data['uid'] = $uid;
                $res = db('xy_bankinfo')->insert($data);
            }
            if ($res) {
                return json(['code' => 0, 'info' => lang('czcg')]);
            } else {
                return json(['code' => 1, 'info' => lang('czsb'), 'sql' => Db::name('xy_bankinfo')->getLastSql()]);
            }
        }
        $this->info = $info;
          $this->ka = $ka;
        $this->assign('bank_list', $bank_list);


        $c = config('default_country');
        $file = APP_PATH . request()->module() . '/view/my/bind_bank_' . $c . '.html';
        if (file_exists($file)) {
            return $this->fetch('bind_bank_' . $c);
        } else {
            return $this->fetch();
        }
        /*if (config('default_country') == 'AUS') {
            return $this->fetch('bind_bank_AUS');
        }
        if (config('default_country') == 'BRA') {
            return $this->fetch('bind_bank_BRA');
        }
        if (config('default_country') == 'INR') {
            return $this->fetch('bind_bank_INR');
        }
        return $this->fetch();*/
    }


    /**
     * 设置默认收货地址
     */
    public function set_address()
    {
        if (request()->isPost()) {
            $id = input('post.id/d', 0);
            Db::startTrans();
            $res = db('xy_member_address')->where('uid', session('user_id'))->update(['is_default' => 0]);
            $res1 = db('xy_member_address')->where('id', $id)->update(['is_default' => 1]);
            if ($res && $res1) {
                Db::commit();
                return json(['code' => 0, 'info' => lang('czcg')]);
            } else {
                Db::rollback();
                return json(['code' => 1, 'info' => lang('czsb')]);
            }
        }
        return json(['code' => 1, 'info' => lang('qqcw')]);
    }

    /**
     * 删除收货地址
     */
    public function del_address()
    {
        if (request()->isPost()) {
            $id = input('post.id/d', 0);
            $info = db('xy_member_address')->find($id);
            if ($info['is_default'] == 1) {
                return json(['code' => 1, 'info' => lang('def_delete_address')]);
            }
            $res = db('xy_member_address')->where('id', $id)->delete();
            if ($res)
                return json(['code' => 0, 'info' => lang('czcg')]);
            else
                return json(['code' => 1, 'info' => lang('czsb')]);
        }
        return json(['code' => 1, 'info' => lang('qqcw')]);
    }

    public function get_bot()
    {
        $data = model('admin/Users')->get_botuser(session('user_id'), 3);
        halt($data);
    }


    public function yue()
    {
        $uid = session('user_id');
        $this->info = db('xy_users')->find($uid);
        return $this->fetch();
    }


    public function edit_username()
    {
        $uid = session('user_id');
        if (request()->isPost()) {
            $username = input('post.username/s', '');
            $res = db('xy_users')->where('id', session('user_id'))->update(['username' => $username]);
            if ($res !== false) {
                return json(['code' => 0, 'info' => lang('czcg')]);
            } else {
                return json(['code' => 1, 'info' => lang('czsb')]);
            }
        }
        $this->info = db('xy_users')->find($uid);
        return $this->fetch();
    }


    /**
     * 用户账号充值
     */
    public function user_recharge()
    {
        $tel = input('post.tel/s', '');
        $num = input('post.num/d', 0);
        $pic = input('post.pic/s', '');
        $real_name = input('post.real_name/s', '');
        $uid = session('user_id');

        if (!$pic || !$num) return json(['code' => 1, 'info' => lang('cscw')]);
        //if(!is_mobile($tel)) return json(['code'=>1,'info'=>'手机号码格式不正确']);

        if (is_image_base64($pic))
            $pic = '/' . $this->upload_base64('xy', $pic);  //调用图片上传的方法
        else
            return json(['code' => 1, 'info' => lang('tpgscw')]);
        $id = getSn('SY');
        $res = db('xy_recharge')
            ->insert([
                'id' => $id,
                'uid' => $uid,
                'tel' => $tel,
                'real_name' => $real_name,
                'pic' => $pic,
                'num' => $num,
                'addtime' => time()
            ]);
        if ($res)
            return json(['code' => 0, 'info' => lang('czcg')]);
        else
            return json(['code' => 1, 'info' => lang('czsbqshcs')]);
    }

    //邀请界面
    public function invite()
    {
        $uid = session('user_id');
        $this->assign('pic', '/upload/qrcode/user/' . ($uid % 20) . '/' . $uid . '.png');
        $user = db('xy_users')->find($uid);
        $url = SITE_URL . url('@index/user/register/invite_code/' . $user['invite_code']);
        $this->assign('url', $url);
        $this->assign('invite_code', $user['invite_code']);
        $this->assign('invite_msg', Db::name('xy_index_msg')->where('id', 13)->value('content'));
        return $this->fetch();
    }

    //我的资料
    public function do_my_info()
    {
        if (request()->isPost()) {
            $headpic = input('post.headpic/s', '');
            $wx_ewm = input('post.wx_ewm/s', '');
            $zfb_ewm = input('post.zfb_ewm/s', '');
            $nickname = input('post.nickname/s', '');
            $sign = input('post.sign/s', '');
            $data = [
                'nickname' => $nickname,
                'signiture' => $sign
            ];
        $headpic=NULL;
            if ($headpic) {
                // if (is_image_base64($headpic))
                //     $headpic = '/' . $this->upload_base64('xy', $headpic);  //调用图片上传的方法
                // else
                //     return json(['code' => 1, 'info' => lang('tpgscw')]);
                // $data['headpic'] = $headpic;
            }

            if ($wx_ewm) {
                // if (is_image_base64($wx_ewm))
                //     $wx_ewm = '/' . $this->upload_base64('xy', $wx_ewm);  //调用图片上传的方法
                // else
                //     return json(['code' => 1, 'info' => lang('tpgscw')]);
                // $data['wx_ewm'] = $wx_ewm;
            }

            if ($zfb_ewm) {
                // if (is_image_base64($zfb_ewm))
                //     $zfb_ewm = '/' . $this->upload_base64('xy', $zfb_ewm);  //调用图片上传的方法
                // else
                //     return json(['code' => 1, 'info' => lang('tpgscw')]);
                // $data['zfb_ewm'] = $zfb_ewm;
            }


            $res = db('xy_users')->where('id', session('user_id'))->update($data);
            if ($res !== false) {
                if ($headpic) session('avatar', $headpic);
                return json(['code' => 0, 'info' => lang('czcg')]);
            } else {
                return json(['code' => 1, 'info' => lang('czsb')]);
            }
        } elseif (request()->isGet()) {
            $info = db('xy_users')->field('username,headpic,nickname,signiture sign,wx_ewm,zfb_ewm')->find(session('user_id'));
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $info]);
        }
    }

    // 消息   /index/my/activity
    public function activity()
    {
        $where[] = ['title', 'like', '%' . '活动' . '%'];

        $this->msg = db('xy_index_msg')->where($where)->select();
        return $this->fetch();
    }


    // 消息
    public function msg()
    { 
          
        $this->info = db('xy_message')->alias('m')
            // ->leftJoin('xy_users u','u.id=m.sid')
            ->leftJoin('xy_reads r', 'r.mid=m.id and r.uid=' . session('user_id'))
            ->field('m.*,r.id rid')
            ->where('m.uid', 'in', [0, session('user_id')])
            ->where("find_in_set(".session('user_id').",m.uids)  or m.uids=-1 ")
            ->order('addtime desc')
            ->select();
       
        $this->msg = db('xy_index_msg')->where('status', 1)->select();

        return $this->fetch();
    }

    // 消息
    public function detail()
    {
        $id = input('get.id/d', 0);

        $this->msg = db('xy_index_msg')->where('id', $id)->find();


        return $this->fetch();
    }

    //记录阅读情况
    public function reads()
    {
        if (\request()->isPost()) {
            $id = input('post.id/d', 0);
            db('xy_reads')->insert(['mid' => $id, 'uid' => session('user_id'), 'addtime' => time()]);
            return $this->success('成功');
        }
    }

    public function gonggao()
    {

    }

    //修改绑定手机号
    public function reset_tel()
    {
        $pwd = input('post.pwd', '');
        $verify = input('post.verify/s', '');
        $tel = input('post.tel/s', '');
        $userinfo = Db::table('xy_users')->field('id,pwd,salt')->find(session('user_id'));
        if ($userinfo['pwd'] != sha1($pwd . $userinfo['salt'] . config('pwd_str'))) return json(['code' => 1, 'info' => lang('pass_error')]);
        if (config('app.verify')) {
            $verify_msg = Db::table('xy_verify_msg')->field('msg,addtime')->where(['tel' => $tel, 'type' => 3])->find();
            if (!$verify_msg) return json(['code' => 1, 'info' => lang('yzmbcz')]);
            if ($verify != $verify_msg['msg']) return json(['code' => 1, 'info' => lang('yzmcw')]);
            if (($verify_msg['addtime'] + (config('app.zhangjun_sms.min') * 60)) < time()) return json(['code' => 1, 'info' => lang('yzmysx')]);
        }
        $res = db('xy_users')->where('id', session('user_id'))->update(['tel' => $tel]);
        if ($res !== false)
            return json(['code' => 0, 'info' => lang('czcg')]);
        else
            return json(['code' => 1, 'info' => lang('czsb')]);
    }

    //团队佣金列表
    public function get_team_reward()
    {
        $uid = session('user_id');
        $lv = input('post.lv/d', 1);
        $num = Db::name('xy_reward_log')->where('uid', $uid)->where('addtime', 'between', strtotime(date('Y-m-d')) . ',' . time())->where('lv', $lv)->where('status', 1)->sum('num');

        if ($num) return json(['code' => 0, 'info' => lang('czcg'), 'data' => $num]);
        return json(['code' => 1, 'info' => lang('zwsj')]);
    }
    
}