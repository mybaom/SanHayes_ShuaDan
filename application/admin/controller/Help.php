<?php

namespace app\admin\controller;

use app\admin\service\NodeService;
use library\Controller;
use library\tools\Data;
use think\Db;

/**
 * 帮助中心
 * Class Users
 * @package app\admin\controller
 */
class Help extends Base
{

    /**
     * 公告管理
     * @auth true
     * @menu true
     */
    public function message_ctrl()
    {
        $this->title = '公告管理';
        $list=$this->_query('xy_message')->page();


    }

    /**
     * 添加公告
     * @auth true
     * @menu true
     */
    public function add_message()
    {
        $this->title = '添加公告';
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $title = input('post.title/s', '');
            $content = input('post.content/s', '');
             $uids = input('post.uids/s', '');

            if (!$title) $this->error('标题为必填项');
             if (!$uids) $this->error('用户为必填项');
            if (mb_strlen($title) > 50) $this->error('标题长度限制为50个字符');
            if (!$content) $this->error('公告内容为必填项');

            $res = Db::table('xy_message')->insert(['addtime' => time(), 'sid' => 0, 'type' => 3, 'title' => $title, 'content' => $content,'uids'=>$uids]);
            if ($res) {
                sysoplog('添加公告', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                $this->success('发送公告成功', '#' . url('message_ctrl'));
            } else
                $this->error('发送公告失败');
        }
        return $this->fetch();
    }

    /**
     * 编辑公告
     * @auth true
     * @menu true
     */
    public function edit_message($id)
    {
        $this->title = '编辑公告';
        $id = intval($id);
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $title = input('post.title/s', '');
            $content = input('post.content/s', '');
            $id = input('post.id/d', 0);
             $uids = input('post.uids/s', '');
            if (!$title) $this->error('标题为必填项');
             if (!$uids) $this->error('用户为必填项');
            if (mb_strlen($title) > 50) $this->error('标题长度限制为50个字符');
            if (!$content) $this->error('公告内容为必填项');

            $res = Db::table('xy_message')->where('id', $id)->update(['addtime' => time(), 'type' => 3, 'title' => $title, 'content' => $content,'uids'=>$uids]);
            if ($res) {
                sysoplog('编辑公告', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                $this->success('编辑成功', '#' . url('message_ctrl'));
            } else
                $this->error('编辑失败');
        }

        $info = Db::table('xy_message')->find($id);
        $this->assign('info', $info);
        $this->fetch();
    }

    /**
     * 删除公告
     * @auth true
     * @menu true
     */
    public function del_message()
    {
        $this->applyCsrfToken();
        $id = input('post.id/d', 0);
        $res = Db::table('xy_message')->where('id', $id)->delete();
        if ($res) {
            sysoplog('删除公告', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            $this->success('删除成功!');
        } else
            $this->error('删除失败!');
    }

    /**
     * 前台首页文本
     * @auth true
     * @menu true
     */
    public function home_msg()
    {
        $this->title = '前台首页文本';
        //$this->_query('xy_index_msg')->page();
        $this->_query('xy_index_msg')->where("status",1)->page();
    }

    /**
     * 编辑前台首页文本
     * @auth true
     * @menu true
     */
    public function edit_home_msg($id)
    {
        $this->title = '编辑前台首页文本';
        $id = intval($id);
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $content = input('post.content/s', '');
            $id = input('post.id/d', 0);
            $title = input('post.title/s', '');

            if (!$content) $this->error('正文内容为必填项');

            $res = Db::table('xy_index_msg')->where('id', $id)->update(['addtime' => time(), 'content' => $content, 'title' => $title]);
            if ($res) {
                unset($_POST['content']);
                sysoplog('编辑前台首页文本', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                $this->success('编辑成功', '#' . url('home_msg'));
            } else
                $this->error('编辑失败');
        }

        $info = Db::table('xy_index_msg')->find($id);
        $this->assign('info', $info);
        $this->fetch();
    }

    /**
     * 首页轮播图
     * @auth true
     * @menu true
     */
    public function banner()
    {
//        if(request()->isPost()){
//            $image = input('post.image/s','');
//            if($image=='') $this->error('请上传图片');
//            $res = Db::name('xy_banner')->where('id',1)->update(['image'=>$image]);
//            if($res!==false)
//                $this->success('操作成功');
//            else
//                $this->error('操作失败');
//        }
//        $this->title = '轮播图设置';
//        $this->info = Db::name('xy_banner')->find(1);
//        $this->fetch();

        $this->title = '首页轮播图';
        $this->_query('xy_banner')->page();
    }

    /**
     * 编辑首页轮播图
     * @auth true
     * @menu true
     */
    public function edit_banner($id)
    {
        $this->title = '编辑首页轮播图';
        $id = intval($id);
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $id = input('post.id/d', 0);
            $url = input('post.url/s', '');
            $image = input('post.image/s', '');

            if (!$image) $this->error('图片为必填项');

            $res = Db::table('xy_banner')->where('id', $id)->update(['image' => $image, 'url' => $url]);
            if ($res) {
                sysoplog('编辑首页轮播图', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                $this->success('编辑成功', '#' . url('banner'));
            } else
                $this->error('编辑失败');
        }

        $info = Db::table('xy_banner')->find($id);
        $this->assign('info', $info);
        $this->fetch();
    }

    /**
     * 添加banner
     * @auth true
     * @menu true
     */
    public function add_banner()
    {
        $this->title = '添加首页轮播图';
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $url = input('post.url/s', '');
            $image = input('post.image/s', '');

            //if(!$title)$this->error('标题为必填项');
            //if(mb_strlen($title) > 50)$this->error('标题长度限制为50个字符');
            if (!$url) $this->error('图片为必填项');

            $res = Db::table('xy_banner')->insert(['url' => $url, 'image' => $image]);
            if ($res) {
                sysoplog('添加首页轮播图', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                $this->success('提交成功', '#' . url('banner'));
            } else
                $this->error('提交失败');
        }
        return $this->fetch();
    }

    public function del_banner()
    {
        $this->applyCsrfToken();
        $id = input('post.id/d', 0);
        $res = Db::table('xy_banner')->where('id', $id)->delete();
        if ($res) {
            sysoplog('删除首页轮播图', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            $this->success('删除成功!');
        } else
            $this->error('删除失败!');
    }


}