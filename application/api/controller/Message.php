<?php

namespace app\api\controller;

use think\App;
use think\Controller;
use think\Exception;
use think\Request;
use think\Db;
use think\View;

class Message extends Base
{
    /**
     * 消息列表
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $uid = $this->_uid;
        $page = input('post.page/d', 1);
        $num = input('post.num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $this->info = db('xy_message')->alias('m')
            // ->leftJoin('xy_users u','u.id=m.sid')
            ->leftJoin('xy_reads r', 'r.mid=m.id and r.uid=' . $uid)
            ->field('m.*,r.id rid')
            ->where('m.uid', 'in', [0, $uid])
            ->where("find_in_set(" . $uid . ",m.uids)  or m.uids=-1 ")
            ->paginate($num)
            ->each(function($item, $key){
                $item['addtime'] = date('Y-m-d H:i:s', $item['addtime']);
                $item['read_time'] = date('Y-m-d H:i:s', $item['read_time']);
                return $item;
            });
        return $this->success('success', $this->info);
    }

    /**
     * 消息详情
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function detail()
    {
        $id = input('post.id/d', 0);
        $this->msg = Db::name('xy_message')->field('id,title,content,addtime')->where('id', $id)->find();
        $this->msg['addtime'] =date('Y-m-d H:i:s', $this->msg['addtime']);
        return $this->success('success', $this->msg);
    }
}