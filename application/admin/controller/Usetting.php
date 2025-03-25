<?php

namespace app\admin\controller;

use library\Controller;
use library\tools\Data;
use think\Db;

//tp5.1用法
use PHPExcel_IOFactory;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\exception\PDOException;

///**
// * 用户做单情况管理
// * Class Users
// * @package app\admin\controller
// */
class Usetting extends Base
{
    /**
     * 指定当前数据表
     * @var string
     */
    protected $table = 'xy_users_setting';

//    /**
//     * 做单设置
//     * @auth true
//     * @throws \think\Exception
//     * @throws DataNotFoundException
//     * @throws ModelNotFoundException
//     * @throws DbException
//     * @throws PDOException
//     */
    public function index()
    {

        $query = $this->_query($this->table);
        $uid = $this->request->get('uid/d', 0);
        if ($uid < 1) {
            return $this->error('用户不存在');
        }
        $this->title = 'UID:' . $uid . ' 做单设置';
        $this->uid = $uid;
        $query->where('uid', $uid);
        $query->page();
    }

//    /**
//     * 表单数据处理
//     * @param array $data
//     * @throws DataNotFoundException
//     * @throws ModelNotFoundException
//     * @throws DbException
//     */
    public function _index_page_filter(&$data)
    {
        //$data = Data::arr2table($data);
    }

    /**
     * 添加用户设置
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $uid = $this->request->get('uid/d', 0);
        if ($uid < 1) {
            return $this->error('用户不存在');
        }
        $this->uid = $uid;
        $this->applyCsrfToken();
        $this->_form($this->table, 'form');
    }

    protected function _add_form_result($result, $data)
    {
        sysoplog('添加用户设置', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 编辑用户设置
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->applyCsrfToken();
        $this->_form($this->table, 'form');
    }

    protected function _edit_form_result($result, $data)
    {
        sysoplog('编辑用户设置', json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}