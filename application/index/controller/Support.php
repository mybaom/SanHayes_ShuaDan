<?php

namespace app\index\controller;

use think\Controller;

use think\Exception;
use think\Request;
use think\Db;
use think\View;

class Support extends Base
{
    /**
     * 首页
     */
    public function index()
    {
        $this->info = db('xy_cs')->where('status', 1)->select();
        if (config('open_agent_chat') == 1) {
            $service = model('admin/Users')->get_user_service_id($this->_uid);
            if ($service) {
                foreach ($this->info as $k => $v) {
                    $this->info[$k]['url'] = $service['chats'];
                }
            }
        }
          $user = db('xy_users')->field('username,tel,level,id,agent_id,headpic,balance,freeze_balance,lixibao_balance,invite_code,show_td')->find(session('user_id'));
        
         $csURL = Db::name('system_config')->where('id', 7)->value('value');//客服地址
      
        if($user['agent_id']>0){
            $kf=Db::name('xy_cs')->where('uid', $user['agent_id'])->select();
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
        
        $this->assign('list', $this->info);
        $this->msg = db('xy_index_msg')->where('status', 1)->select();
        return $this->fetch();
    }


    public function index2()
    {
        $this->url = isset($_REQUEST['url']) ? $_REQUEST['url'] : '';
        return $this->fetch();
    }

    /**
     * 首页
     */
    public function detail()
    {
        $id = input('get.id/d', 1);
        $this->info = db('xy_index_msg')->where('id', $id)->find();


        return $this->fetch();
    }


    /**
     * 换一个客服
     */
    public function other_cs()
    {
        $data = db('xy_cs')->where('status', 1)->where('id', '<>', $id)->find();
        if ($data) return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data]);
        return json(['code' => 1, 'info' => lang('zwsj')]);
    }
}