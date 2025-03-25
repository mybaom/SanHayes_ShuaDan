<?php

namespace app\index\controller;

use think\App;
use think\Controller;
use think\Exception;
use think\Request;
use think\Db;
use think\View;

class Message extends Base
{
    protected $msg = ['__token__' => 'post error'];

// You Have successfully recharged 5000USDT to your account!
    //  /index/message/index.html
    public function index()
    {
        $id = session('user_id');
        $day = input('get.day/s', '');
        $where = [];
        if ($day) {
            $start = strtotime("-$day days");
            $where[] = ['addtime', 'between', [$start, time()]];
        }

        $start = input('get.start/s', '');
        $end = input('get.end/s', '');
        if ($start || $end) {
            $start ? $start = strtotime($start) : $start = strtotime('2020-01-01');
            $end ? $end = strtotime($end . ' 23:59:59') : $end = time();
            $where[] = ['addtime', 'between', [$start, $end]];
        }


        $this->start = $start ? date('Y-m-d', $start) : '';
        $this->end = $end ? date('Y-m-d', $end) : '';

        $this->type = $type = input('get.type/d', 0);

        if ($type == 1) {
            $where['type'] = 7;
        } elseif ($type == 2) {
            $where['type'] = 1;
        }


        $this->_query('xy_recharge')
            ->where('uid', $id)->where($where)->order('id desc')->page();
        //var_dump($_REQUEST);die;
    }
}