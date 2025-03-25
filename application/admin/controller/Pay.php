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

use app\admin\service\NodeService;
use library\Controller;
use library\tools\Data;
use think\Db;

/**
 * 支付方式管理
 * Class Pay
 * @package app\admin\controller
 */
class Pay extends Base
{

    /**
     * 指定当前数据表
     * @var string
     */
    protected $table = 'xy_pay';

    /**
     * 支付方式
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        $this->title = '支付方式';

        $query = $this->_query($this->table)->alias('u');
        $where = [];
        if (input('tel/s', '')) $where[] = ['u.tel', 'like', '%' . input('tel/s', '') . '%'];
        if (input('username/s', '')) $where[] = ['u.username', 'like', '%' . input('username/s', '') . '%'];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['u.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1])]];
        }
        $query->field('*')
            ->where($where)
            ->order('u.sort desc,u.id asc')
            ->page(false);
    }


    /**
     * 编辑支付
     * @auth true
     * @menu true
     */
    public function edit()
    {
        $id = input('get.id', 0);

        if (request()->isPost()) {
            $id = input('post.id/d', 0);
            $token = input('__token__');
            $data = array(
                'name' => input('post.name/s', ''),
                'min' => input('post.min/f', 0),
                'max' => input('post.max/f', 0),
                'ewm' => input('post.ewm/s', ''),
                'usercode' => input('post.usercode/s', ''),
                'username' => input('post.username/s', ''),
                'secret' => input('post.secret/s', ''),
                'mch_id' => input('post.mch_id/s', ''),
                'pay_commission' => input('post.pay_commission/f', 0),
            );
            
            $res = Db::table($this->table)->where('id', $id)->update($data);
            if (!$res) {
                return $this->error('保存失败');
            }
            sysoplog('编辑支付', json_encode($data, JSON_UNESCAPED_UNICODE));
            $this->success('编辑成功');
        }
        if (!$id) $this->error('参数错误');
        $this->info = Db::table($this->table)->find($id);

        //var_dump($this->info);die;
        return $this->fetch();
    }


    /**
     * 禁用代收状态
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        //$this->applyCsrfToken();
        $this->_save($this->table, ['status' => '0']);
    }

    protected function _forbid_save_result($result, $data)
    {
        sysoplog('禁用代收状态', json_encode($_POST, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 启用代收状态
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        //$this->applyCsrfToken();
        $this->_save($this->table, ['status' => '1']);
    }

    protected function _resume_save_result($result, $data)
    {
        sysoplog('启用代收状态', json_encode($_POST, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 设置为代付
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function set_payout()
    {
        Db::name($this->table)->where('id', '>', 0)->update([
            'is_payout' => 0
        ]);
        //$this->applyCsrfToken();
        $this->_save($this->table, ['is_payout' => '1']);
    }

    protected function _set_payout_save_result($result, $data)
    {
        sysoplog('设置为代付', json_encode($_POST, JSON_UNESCAPED_UNICODE));
    }
}