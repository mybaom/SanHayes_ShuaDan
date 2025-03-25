<?php
namespace app\index\controller;

use library\Controller;
use think\Db;

/**
 * 应用入口
 * Class Index
 * @package app\index\controller
 */
class Index extends Base
{
    /**
     * 入口跳转链接
     */
    public function index()
    {
        $this->home();
    }

    public function home()
    {
        $uid = session('user_id');
        $lang=cookie('think_var');//es-mx

        $this->info = Db::name('xy_users')->find($uid);
        $this->accountNo="";
        if (empty($this->info["tel"])){
            $this->accountNo=$this->info["username"];
        }else{
            $this->accountNo=$this->info["tel"];
        }

        $level=$this->info["level"];
        if (empty($level)){
            $level=0;
        }
        $level=$level+1;
        if ($level=="1"){
            $this->vip_img_path0="/static/img/vip1.png";
        }
        if ($level=="2"){
            $this->vip_img_path0="/static/img/vip2.png";
        }
        if ($level=="3"){
            $this->vip_img_path0="/static/img/vip3.png";
        }
        if ($level=="4"){
            $this->vip_img_path0="/static/img/vip4.png";
        }

        $this->balance = $this->info['balance'];
        $this->banner = Db::name('xy_banner')->select();
        $this->notice = Db::name('xy_index_msg')->where('id', 1)->value('content');
        
        if (empty($lang)||$lang=="zh-cn"){
            
            cookie('think_var', "zh-cn");
            $this->tc = Db::name('xy_index_msg')->where('id', 16)->value('content');//T&C
            $this->latestevents = Db::name('xy_index_msg')->where('id', 17)->value('content');//Latest Events
            $this->faq = Db::name('xy_index_msg')->where('id', 6)->value('content');//FAQ
            $this->aboutus = Db::name('xy_index_msg')->where('id', 18)->value('content');//About US
        }elseif($lang='tr-tr') {
            $this->tc = Db::name('xy_index_msg')->where('id', 21)->value('content');//T&C
            $this->latestevents = Db::name('xy_index_msg')->where('id', 20)->value('content');//Latest Events
            $this->faq = Db::name('xy_index_msg')->where('id', 23)->value('content');//FAQ
            $this->aboutus = Db::name('xy_index_msg')->where('id', 19)->value('content');//About US
        //   $this->tc = Db::name('xy_index_msg')->where('id', 16)->value('content');//T&C
        //     $this->latestevents = Db::name('xy_index_msg')->where('id', 17)->value('content');//Latest Events
        //     $this->faq = Db::name('xy_index_msg')->where('id', 6)->value('content');//FAQ
        //     $this->aboutus = Db::name('xy_index_msg')->where('id', 18)->value('content');//About US
        }else {
            // cookie('think_var', "es-mx");//es-mx  
             cookie('think_var', "ar-ae");//es-mx  
            $this->tc = Db::name('xy_index_msg')->where('id', 21)->value('content');//T&C
            $this->latestevents = Db::name('xy_index_msg')->where('id', 20)->value('content');//Latest Events
            $this->faq = Db::name('xy_index_msg')->where('id', 23)->value('content');//FAQ
            $this->aboutus = Db::name('xy_index_msg')->where('id', 19)->value('content');//About US
        }



        $this->notice = htmlspecialchars_decode($this->notice);
        $this->level_list = Db::table('xy_level')->where('level<4')->select();
//        foreach ($this->level_list as $po){
//            if ($po["id"]==1){
//                $po["path"]="/static/img/vip1.png";
//            }
//            if ($po["id"]==2){
//                $po["path"]="/static/img/vip2.png";
//            }
//            if ($po["id"]==3){
//                $po["path"]="/static/img/vip3.png";
//            }
//            if ($po["id"]==4){
//                $po["path"]="/static/img/vip4.png";
//            }
//        }
        

        $this->index_icon = Db::name('xy_index_msg')->where('id', 'in',[2,3,4,12])->column('title','id');

        if (config('app_only')) {
            $dev = new \org\Mobile();
            $t = $dev->isMobile();
            if (!$t) {
                header('Location:/app');
            }
        }

        $sr_list = Db::query('SELECT uid,sum(`num`) as `today_income` FROM `xy_balance_log` WHERE addtime>' . strtotime('today') . ' and `type` in(3,6) and `status`=1 group by uid order by `today_income` desc limit 20');
        $list = [];
        foreach ($sr_list as $k => $v) {
            $list[$k] = $v;
            $list[$k]['tel'] = Db::name('xy_users')->where('id', $v['uid'])->value('username');
        }

        $this->list = $list;

        $yes1 = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        $yes2 = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));
        $this->tod_user_yongjin = Db::name('xy_convey')->where('uid', $uid)->where('status', 1)->where('addtime', 'between', [strtotime('Y-m-d 00:00:00'), time()])->sum('commission');
        $this->yes_user_yongjin = Db::name('xy_convey')->where('uid', $uid)->where('status', 1)->where('addtime', 'between', [$yes1, $yes2])->sum('commission');
        $this->user_yongjin = Db::name('xy_convey')->where('uid', $uid)->where('status', 1)->sum('commission');

        $this->lixi_count = Db::table('xy_lixibao')->where('uid', session('user_id'))->sum('yuji_num');
        $this->lixi_count_today = Db::table('xy_lixibao')->where('uid', session('user_id'))->where('addtime', 'between', [strtotime('Y-m-d 00:00:00'), time()])->sum('yuji_num');
        $this->today_income = $this->tod_user_yongjin + $this->lixi_count_today;

        return $this->fetch('home');
    }

    //获取首页图文
    public function get_msg()
    {
        $type = input('post.type/d', 1);
        $data = Db::name('xy_index_msg')->find($type);
        if ($data)
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data]);
        else
            return json(['code' => 1, 'info' => lang('zwsj')]);
    }


    //获取首页图文
    public function getTongji()
    {
        $type = input('post.type/d', 1);
        $data = array();

        $data['user'] = Db::name('xy_users')->where('status', 1)->where('addtime', 'between', [strtotime(date('Y-m-d')) - 24 * 3600, time()])->count('id');
        $data['goods'] = Db::name('xy_goods_list')->count('id');;
        $data['price'] = Db::name('xy_convey')->where('status', 1)->where('endtime', 'between', [strtotime(date('Y-m-d')) - 24 * 3600, strtotime(date('Y-m-d'))])->sum('num');
        $user_order = Db::name('xy_convey')->where('status', 1)->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->field('uid')->Distinct(true)->select();
        $data['num'] = count($user_order);

        if ($data) {
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data]);
        } else {
            return json(['code' => 1, 'info' => lang('zwsj')]);
        }
    }


    function getDanmu()
    {
        $barrages =    //弹幕内容
            array(
                array(
                    'info' => '用户173***4985开通会员成功',
                    'href' => '',

                ),
                array(
                    'info' => '用户136***1524开通会员成功',
                    'href' => '',
                    'color' => '#ff6600'

                ),
                array(
                    'info' => '用户139***7878开通会员成功',
                    'href' => '',
                    'bottom' => 450,
                ),
                array(
                    'info' => '用户159***7888开通会员成功',
                    'href' => '',
                    'close' => false,

                ), array(
                'info' => '用户151***7799开通会员成功',
                'href' => '',

            )
            );

        echo json_encode($barrages);
    }
    
    

}
