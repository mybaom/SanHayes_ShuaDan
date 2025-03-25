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


class Inyectar extends Base
{
    /**
     * 指定当前数据表
     * @var string
     */
    protected $table = 'xy_inyectar';

    /**
    * 打针计划
    * @auth true
    * @throws \think\Exception
    * @throws DataNotFoundException
    * @throws ModelNotFoundException
    * @throws DbException
    * @throws PDOException
    */
    public function index()
    {

        $query = $this->_query($this->table);
        $uid = $this->request->get('uid/d', 0);
        if ($uid < 1) {
            return $this->error('用户不存在');
        }
        $this->title = 'UID:' . $uid . ' 打针计划';
        $this->uid = $uid;
        $query->where('uid', $uid);
        $query->page();
    }

    /**
     * 表单数据处理
     * @param array $data
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function _index_page_filter(&$data)
    {
        //$data = Data::arr2table($data);
    }

    /**
    * 添加打针
    * @auth true
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
        sysoplog('添加打针', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
    * 编辑打针
    * @auth true
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
        sysoplog('编辑打针', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
    * 删除打针
    * @auth true
    * @throws \think\Exception
    * @throws \think\exception\PDOException
    */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }

    protected function _remove_delete_result($result)
    {
        if ($result) {
            $id = $this->request->post('id/d');
            sysoplog('删除打针', "ID {$id}");
        }
    }

    /**
    * 批量打针
    * @auth true
    */
    public function batch_inyectar()
    {
        $uids = input('uids');
        $scale = input('scale');
        if (!is_array($uids)) {
            $this->error('选择要打针的用户');
        }
        $data = [
            'order_num' => 0,
            'date' => date('Y-m-d'),
            'scale' => $scale
        ];
        foreach ($uids as $uid) {
            $uid = intval($uid);
            if ($uid) {
                Db::name($this->table)->insert(array_merge($data, ['uid' => $uid]));
            }
        }
        sysoplog('批量打针', "ID " . json_encode($uids, JSON_UNESCAPED_UNICODE) . " DATA " . json_encode($data, JSON_UNESCAPED_UNICODE));
        $this->success('打针成功', $data);
    }
}