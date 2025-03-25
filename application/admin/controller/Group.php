<?php

namespace app\admin\controller;

use library\tools\Data;
use think\Db;

//tp5.1用法
use PHPExcel_IOFactory;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\exception\PDOException;

///**
// * 叠加组
// * Class Users
// * @package app\admin\controller
// */
class Group extends Base
{
    /**
     * 指定当前数据表
     * @var string
     */
    protected $table = 'xy_group';
    protected $table_rule = 'xy_group_rule';

    /**
     * 叠加组列表
     * @menu true
     * @throws \think\Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    public function index()
    {
        
        $this->title = '叠加组列表';

        $agentList = Db::name('system_user')->column('username', 'id');
       
        $this->agentList = $agentList;
        $this->agentList[0] = 'system';

        $query = $this->_query($this->table);
        //  dump($this->table);die;
        if ($this->agent_id > 0) $query->where('agent_id', $this->agent_id);
        $query->order('agent_id asc,id desc')->page(true, true, false, 0, 'aaa');
    }

    /**
     * 表单数据处理
     * @param array $data
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    protected function _index_page_filter(&$data)
    {
        foreach ($data as &$vo) {
            $vo['rule_count'] = Db::name('xy_group_rule')
                ->where('group_id', $vo['id'])
                ->count('id');
            $vo['user_count'] = Db::name('xy_users')
                ->where('group_id', $vo['id'])
                ->count('id');
        }
        $data = Data::arr2table($data);
    }

    /**
     * 添加叠加组
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->applyCsrfToken();
        $this->_form($this->table, 'form');
    }

    protected function _add_form_result($result, $data)
    {
        sysoplog('添加叠加组', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 编辑叠加组
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
        sysoplog('编辑叠加组', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 删除叠加组
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }

    /**
     * 删除结果处理
     * @param boolean $result
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    protected function _remove_delete_result($result)
    {
        if ($result) {
            $id = $this->request->post('id/d');
            Db::name('xy_users')
                ->where('group_id', $id)
                ->update(['group_id' => 0]);
            sysoplog('删除叠加组', "ID {$id}");
        }
    }

    /**
     * 规则配置
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function rule()
    {
        $group_id = $this->request->get('group_id/d', 0);
        if (!$group_id) {
            $this->error('数据不存在');
        }
        $this->title = '叠加规则列表';
        $this->group_id = $group_id;
        $this->com_types = [0 => '百分比', 1 => "固定值"];
        $this->order_types = [0 => '默认模式', 1 => "叠加模式"];
        $query = Db::name($this->table_rule)
            ->where('group_id', $group_id);
        $this->list = $query->select();
        $this->fetch();
    }

    /**
     * 添加规则
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function rule_add()
    {
        $this->group_id = $this->request->get('group_id/d', 0);
        if (!$this->group_id) {
            $this->error('数据不存在');
        }
        $this->applyCsrfToken();
        $this->_form($this->table_rule, 'rule_form');
    }

    /**
     * 编辑规则
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function rule_edit()
    {
        $this->applyCsrfToken();
        $this->_form($this->table_rule, 'rule_form');
    }

    /**
     * 删除规则
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function rule_remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table_rule);
    }
}
