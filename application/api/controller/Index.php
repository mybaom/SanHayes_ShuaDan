<?php

namespace app\api\controller;

use app\http\middleware\Auth;
use think\Db;
use think\facade\Lang;
use think\Request;
use think\facade\Cookie;

/**
 * 应用入口
 * Class Index
 * @package app\index\controller
 */
class Index extends Base
{
    /**
     * 返回首页信息
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function home(Request $request)
    {
        //获取用户信息
        $data['userinfo'] = Db::name('xy_users')->field('id,username,tel,level')->find($this->_uid);
        $data['userinfo']['level'] += 1;
        //获取公告
        //英文版 2 印尼版 3
        if (Cookie::get('think_var') == 'ina') {
            $artile_id = 3;
        } else {
            $artile_id = 2;
        }
        $data['notice'] = Db::name('xy_index_msg')->where(['type' => 1, 'id' => $artile_id])->find();
        //过滤p标签
        $data['notice']['content'] = strip_tags($data['notice']['content']);

        //首页弹窗
        $data['popup'] = Db::name('xy_index_msg')->where(['id' => 1])->value('content');
        //轮播图
        $banner = Db::name('xy_banner')->select();
        foreach ($banner as $k => $vv) {
            $banner[$k]['image'] = SITE_URL . "/".$vv['image'];
        }
        $data['banner'] = $banner;
        //获取vip等级列表
        $data['level_list'] = Db::table('xy_level')->field('id,name,order_num,level,bili,num_min,task_num')->where('level<4')->select();

        foreach ($data['level_list'] as $k => &$infos) {
            $infos['images'] = SITE_URL . "/users_vip/VIP" . ($k + 1) . ".png";
        }
        if ($data) {
            return $this->success('success', $data);
        }
        return $this->error(lang('zwsj'));
    }

    //获取首页图文
    public function get_msg()
    {
        //key=FS:Financial Statement(id:4) TU:Terms of Use(id:5) RS:Revenue Simulation(id:6)  MD:Member Description(id:7) AU:About Us(id:8)
        //key=FS:Intruksi Keuangan(id:16) TU:Syarat Pengguna(id:17) RS:Simulasi Penghasilan(id:18)  MD:Deskripsi Anggota(id:19) AU:Tentang kami(id:20)
//        dump($this->lang);
        $type = input('get.key/s');
        $lang = $this->lang;
        $id = 0;
        if($lang == 'ina'){
            if ($type == 'FS') {
                $id = 16;
            }elseif($type =='TU'){
                $id = 17;
            }elseif($type =='RS'){
                $id = 18;
            }elseif($type =='MD'){
                $id = 19;
            }else{
                $id = 20;
            }
        }else{
            if ($type == 'FS') {
                $id = 4;
            }elseif($type =='TU'){
                $id = 5;
            }elseif($type =='RS'){
                $id = 6;
            }elseif($type =='MD'){
                $id = 7;
            }else{
                $id = 8;
            }
            $lang = 'en';
        }

        $data = Db::name('xy_index_msg')->field('id,title,content')->where(['id' => $id, 'lang' => $lang])->find();
        if ($data)
            return $this->success(lang('czcg'), $data);
        else
            return $this->error(lang('zwsj'));
    }

    /**
     * vip列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_viplist()
    {
        //获取vip等级列表
        $data['viplist'] = Db::table('xy_level')->field('id,name,order_num,level,bili,num_min,task_num')->where('level<4')->select();
        $data['current'] = Db::name('xy_users')->where(['id' => $this->_uid])->value('level') + 1;
        foreach ($data['viplist'] as $k => &$infos) {
            $infos['images'] = SITE_URL . "/users_vip/VIP" . ($k + 1) . ".png";
        }
        return $this->success('success', $data);
    }

    /**
     * 充值快捷金额
     * @return void
     */
    public function recharge_amount()
    {
        $res = config('recharge_money_list');
        $res = explode('/', $res);
        return $this->success('success', $res);
    }

    public function qingli()
    {
        var_dump("111111");
        die;
    }
}
