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

namespace app\api\controller;

use library\Controller;
use think\Db;
use think\App;
use think\facade\Request;
use think\facade\Cookie;
use think\facade\Lang;

/**
 * 登录控制器
 */
class User extends Controller
{
    protected $table = 'xy_users';

    public function __construct(App $app)
    {
        parent::__construct($app);

        //切换语言
        $lang = Request::header('language')??'en';
        Lang::range($lang);
        config('lang_set',$lang);//设置语言
        lang::load(APP_PATH . '/lang/' . $lang . '.php');
        //切换语言

    }
    public function sys()
    {
        $info['area_code']=sysconf('area_code');
        return $this->success('success', $info);
    }
    //用户登录接口
    public function login()
    {
        $tel = input('post.phone/s', '');
        /*if(!is_mobile($tel)){
            return json(['code'=>1,'info'=>lang('sjhmgzbzq')]);
        }*/
        $num = Db::table($this->table)->where(['tel|username' => $tel])->count();
        if (!$num) {
            return $this->error(lang('zhbcz') ?? 'Account does not exist', '', 1);
        }

        $pwd = input('post.pwd/s', '');
        $keep = input('post.keep/b', false);
        $jizhu = input('post.jizhu/s', 0);


        $userinfo = Db::table($this->table)->field('id,pwd,salt,pwd_error_num,allow_login_time,status,login_status,headpic')->where('tel', $tel)->find();
        if (!$userinfo) $this->error(lang('not_user'), '', 1);
//        if ($userinfo['status'] != 1) return $this->error(lang('yhybjy'), '', 1);
        //if($userinfo['login_status'])return ['code'=>1,'info'=>'此账号已在别处登录状态'];
        if ($userinfo['allow_login_time'] &&
            ($userinfo['allow_login_time'] > time()) &&
            ($userinfo['pwd_error_num'] > config('pwd_error_num'))) {
            return $this->error(sprintf(lang('pass_err_times'), config('allow_login_min')), '', 1);
        }
        if ($userinfo['pwd'] != sha1($pwd . $userinfo['salt'] . config('pwd_str'))) {
            Db::table($this->table)->where('id', $userinfo['id'])->update(['pwd_error_num' => Db::raw('pwd_error_num+1'), 'allow_login_time' => (time() + (config('allow_login_min') * 60))]);
            return $this->error(lang('pass_error') ?? 'incorrect password', '', 1);
        }

        $res = Db::table($this->table)->where('id', $userinfo['id'])->update(['pwd_error_num' => 0, 'allow_login_time' => 0,'login_time'=>time(), 'login_status' => 1]);
        if ($res !== false) {
            $token = getToken($userinfo['id']);
            return $this->success(lang('loging_ok'), ['token' => $token], 0);
        }
    }

    /**
     * 用户注册接口
     */
    public function do_register()
    {
        $tel = input('post.phone/s', '');
        $user_name = input('post.username/s', '');
//        $verify = input('post.verify/d', '');       //短信验证码
        $pwd = input('post.pwd/s', '');//登录密码
        $deposit_pwd = input('post.deposit_pwd/s', '');//提现密码
        $pwd2 = input('post.re_pwd/s', '');//确认登录密码
        $gender = input('post.gender/s', '');     //性别
        $invite_code = input('post.invite_code/s', '');     //邀请码

        if ($pwd && $pwd2 && $pwd != $pwd2) {  //两次密码不一致  登陆密码
            return $this->error(lang('pass_two_error'), '', 1);
        }

        if (config('reg_ip') == 1) {
            $find = Db::table($this->table)->where('ip', config('reg_ip'))->find();
            if ($find) {
                return $this->error(lang('ip_not'), '', 1);
            }
        }
        if (!$invite_code) $this->error(lang('code_not'), '', 1);;

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
            $parentinfo = Db::table($this->table)->field('id,status,agent_id,parent_id,level,	agent_service_id')->where('invite_code', $invite_code)->find();
            if (!$parentinfo) return $this->error(lang('code_not'), '', 1);
            $is_invite = Db::table('xy_level')
                ->where('level', $parentinfo['level'])
                ->value('is_invite');
            if (empty($is_invite)) return $this->error(lang('user_not_auth'), '', 1);
//            if ($parentinfo['status'] != 1) return $this->error(lang('disable_user'), '', 1);
            $pid = $parentinfo['id'];
            if ($parentinfo['agent_id'] > 0) {
                $agent_id = $parentinfo['agent_id'];
            }
        }
        if ($agent_id == 0) {
            $agent_id = model('admin/Users')->get_agent_id();
        }
        $res = model('admin/Users')
            ->add_users($tel, $user_name, $pwd, $pid, '', $deposit_pwd, $agent_id, $this->request->ip(), '',0);
        if ($agent_id>0 && $res['code']==0){
            $sys = Db::name('system_user')->where('id', $agent_id)->find();
            Db::name($this->table)
                ->where('id', $res['id'])
                ->update([
                    'agent_service_id' => $parentinfo['agent_service_id']
                ]);
        }
        return json($res);
    }


    /**
     * 客服列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_customer()
    {
        $services = Db::name('xy_cs')->field('username,btime,etime,url')->where(['status' => 1])->select();
        if ($services) {
            return $this->success('success', $services, 0);
        }

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

    public function lang()
    {
        $language = Db::table('xy_language')->field('id,title,name,link')->where(['state' => 1])->select();
        $this->assign('language', $language);
        return $this->fetch();
    }

    public function lang_set()
    {
        $lang = input('lang');
        cookie('think_var', $lang);//es-mx
        $this->redirect('/index', 302);
    }

    public function reset_qrcode()
    {
        $uinfo = Db::name('xy_users')->field('id,invite_code')->select();
        foreach ($uinfo as $v) {
            $model = model('admin/Users');
            $model->create_qrcode($v['invite_code'], $v['id']);
        }
        return '重新生成用户二维码图片成功';
    }

    //测试
    public function testss()
    {
        // date_default_timezone_set('UTC-4'); 
        $a = config('order_time_1');
        $b = config('order_time_2');

        $nowtime = date('H', time());
        echo '当前时间H：' . $nowtime . '<br/>'; //
        $start = $a;
        $end = $b;
        echo 'a：' . $a . '<br/>'; //
        echo 'b：' . $b . '<br/>'; //
        echo time() . '<br/>';
    }

}