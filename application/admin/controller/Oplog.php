<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | www.xydai.cn 新源代网 
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 

// +----------------------------------------------------------------------

namespace app\admin\controller;

use library\Controller;
use think\Db;

/**
 * 系统日志管理
 * Class Oplog
 * @package app\admin\controller
 */
class Oplog extends Base
{

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'SystemLog';

    /**
     * 系统操作日志
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '系统操作日志';
        $query = $this->_query($this->table)->like('action,node,content,username,geoip');
        $query->dateBetween('create_at')->order('id desc')->page();
    }

    /**
     * 列表数据处理
     * @auth true
     * @param array $data
     * @throws \Exception
     */
    protected function _index_page_filter(&$data)
    {
        $ip = new \Ip2Region();
        foreach ($data as &$vo) {
            $result = $ip->btreeSearch($vo['geoip']);
            $vo['isp'] = isset($result['region']) ? $result['region'] : '';
            $vo['isp'] = str_replace(['内网IP', '0', '|'], '', $vo['isp']);
        }
    }

    /**
     * 清理系统日志
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function clear()
    {
        if (Db::name($this->table)->whereRaw('1=1')->delete() !== false) {
            sysoplog('清理系统日志', '');
            $this->success('日志清理成功！');
        } else {
            $this->error('日志清理失败，请稍候再试！');
        }
    }

    /**
     * 删除系统日志
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
            sysoplog('删除系统日志', "ID {$id}");
        }
    }
}
