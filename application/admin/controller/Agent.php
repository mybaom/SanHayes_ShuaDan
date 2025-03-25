<?php

namespace app\admin\controller;

use app\admin\service\NodeService;
use library\tools\Data;
use think\Db;
use PHPExcel;

/**
 * 代理管理
 * Class Agent
 * @package app\admin\controller
 */
class Agent extends Base
{
    /**
     * 指定当前数据表
     * @var string
     */
    protected $table = 'system_user';
    protected $table_user = 'xy_users';

    /**
     * 代理列表
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
  
        if ($this->agent_id > 0 && $this->agent_uid > 0) return '<h1>无权限</h1>';
        $this->title = '代理列表';
        $this->is_admin = $this->agent_id == 0;
        $query = $this->_query($this->table)->where('authorize', '2');
        if ($this->agent_id > 0) {
            $query->where('parent_id', $this->agent_id);
        } else {
            $parent_id = input('parent_id/d', 0);
            if ($parent_id > 0) {
            $query->where('parent_id', $parent_id);
                $aname = Db::name($this->table)->where('id', $parent_id)->value('username');
                $this->title =  $this->title ."({$aname})";
            }
        }
        $query->where('is_deleted', 0);
        return $query->like('username,phone')->order('id DESC')->page();
    }
    /**
     * 表单数据处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _index_page_filter(&$data)
    {
        foreach ($data as &$vo) {
            $vo['invite_code'] = '';
            if($vo['user_id']>0){
                $vo['invite_code'] = Db::name('xy_users')->where('id',$vo['user_id'])->value('invite_code');
            }
        }
        $data = Data::arr2table($data);
    }

    /**
     * 添加代理
     * @auth true
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

    /**
     * 编辑代理
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

    /**
     * 表单数据处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _form_filter(&$data)
    {
        if ($this->request->isPost()) {
            if (isset($data['username'])) $data['username'] = strtolower($data['username']);
            // 用户账号重复检查
            if (isset($data['id'])) unset($data['username']);
            elseif (Db::name($this->table)->where(['username' => $data['username'], 'is_deleted' => '0'])->count() > 0) {
                $this->error("账号{$data['username']}已经存在，请使用其它账号！");
            }
            if ($this->agent_id == 0) {
                //$data['parent_id'] = 0;
            } else {
                $data['parent_id'] = $this->agent_id;
            }
            if (!isset($data['id']) && $data['parent_id'] > 0) {
                if (!$data['phone']) $this->error('手机号必填');
                if (Db::name($this->table_user)->where(['tel' => $data['phone']])->count('id') > 0) {
                    $this->error("手机号 {$data['phone']} 已经存在，请使用其它手机号！");
                }
                if (Db::name($this->table_user)->where(['username' => $data['username']])->count('id') > 0) {
                    $this->error("账号 {$data['username']} 已经存在，请使用其它账号！");
                }
            }
            //用户权限处理
            $data['authorize'] = 2;
            /*if (!empty($data['user_id'])) {
                $isAgentSon = Db::name('xy_users')->where('id', $data['user_id'])->value('agent_id');
                if (empty($isAgentSon)) {
                    $this->error("业务员ID {$data['user_id']} 未绑定代理！");
                }
            }*/
        } else {
            $data['user_id'] = !empty($data['user_id']) ? $data['user_id'] : 0;
            $this->agent_list = Db::name('system_user')
                ->where('parent_id', 0)
                ->where('user_id', 0)
                ->where('authorize', "2")
                ->field('id,username')
                ->where('is_deleted', 0);
            if ($this->agent_id) $this->agent_list->where('id', $this->agent_id);
            $this->agent_list = $this->agent_list->select();

            $this->is_admin = $this->agent_id == 0;
        }
    }

    public function _form_result(&$result, &$data)
    {
        if ($this->request->isPost()) {
            if ($result !== false) {
                //开户
                if (!isset($data['id']) && $data['parent_id'] > 0) {
                    $data['id'] = $result;
                    //添加用户
                    $res = model('Users')->add_users(
                        $data['phone'], $data['username'], '123456', 0,
                        '', '123456', $data['parent_id']);
                    if ($res['code'] == 0) {
                        //添加成功了
                        Db::name($this->table_user)
                            ->where('id', $res['id'])
                            ->update(['agent_service_id' => $data['id']]);
                        Db::name($this->table)
                            ->where('id', $data['id'])
                            ->update(['user_id' => $res['id']]);
                    }
                    sysoplog('添加代理', '新代理ID ' . $data['id']);
                } else {
                    sysoplog('编辑代理', '新数据包 ' . json_encode($data, JSON_UNESCAPED_UNICODE));
                }
            }
        }
        return true;
    }

    /**
     * 修改代理用户密码
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function pass()
    {
        $this->applyCsrfToken();
        if ($this->request->isGet()) {
            $this->verify = false;
            $this->_form($this->table, 'pass');
        } else {
            $post = $this->request->post();
            if ($post['password'] !== $post['repassword']) {
                $this->error('两次输入的密码不一致！');
            }
            $result = NodeService::checkpwd($post['password']);
            if (empty($result['code'])) $this->error($result['msg']);
            if (Data::save($this->table, ['id' => $post['id'], 'password' => md5($post['password'])], 'id')) {
                sysoplog('修改代理用户密码', 'ID ' . $post['id']);
                $this->success('密码修改成功，下次请使用新密码登录！', '');
            } else {
                $this->error('密码修改失败，请稍候再试！');
            }
        }
    }

    /**
     * 禁用代理户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (in_array('10000', explode(',', $this->request->post('id')))) {
            $this->error('error！');
        }
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '0']);
    }

    protected function _forbid_save_result($result, $data)
    {
        sysoplog('禁用代理户', json_encode($_POST, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 启用代理用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '1']);
    }

    protected function _resume_save_result($result, $data)
    {
        sysoplog('启用代理用户', json_encode($_POST, JSON_UNESCAPED_UNICODE));
    }
}