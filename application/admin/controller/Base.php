<?php

namespace app\admin\controller;

use library\Controller;
use library\tools\Data;
use think\App;
use think\Db;
use think\facade\Cookie;

class Base extends Controller
{
    protected $agent_id = 0;//代理id
    protected $agent_uid = 0;//代理用户账号id
    protected $adminId = 0;

    public function __construct(App $app)
    {
        parent::__construct($app);
        Cookie::set('think_var', 'en');
        //初始化代理信息
        $this->agent_id = model('admin/Users')->get_admin_agent_id();
        $this->agent_uid = model('admin/Users')->get_admin_agent_uid();
        $uid = session('admin_user.id');
        $uid = $uid ? intval($uid) : 0;
        $this->adminId = $uid;
        if (!$this->adminId) {
            \think\facade\Session::clear();
            \think\facade\Session::destroy();
            return $this->redirect('/');
        }
    }
}