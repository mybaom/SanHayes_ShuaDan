<?php

namespace app\admin\controller;

use library\tools\Data;
use think\Db;
use PHPExcel;

//tp5.1用法
use PHPExcel_IOFactory;

/**
 * 会员管理
 * Class Users
 * @package app\admin\controller
 */
class Users extends Base
{

    /**
     * 指定当前数据表
     * @var string
     */
    protected $table = 'xy_users';

    /**
     * 会员列表
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
        $this->assign('admin_id',$this->adminId);
        $this->title = '会员列表';
        $query = $this->_query($this->table)->alias('u');
        $where = [];
        // $where[] = ['u.is_jia', '=', 0];
        if (input('tel/s', '')) $where[] = ['u.tel', '=', input('tel/s', '')];
        if (input('invite_code/s', '')) $where[] = ['u.invite_code', '=', input('invite_code/s')];
        if (input('username/s', '')) $where[] = ['u.username', 'like', '%' . input('username/s', '') . '%'];
        if (input('is_jia/s', '')) {
            $isjia = input('is_jia/s', '');
            $isjia == -1 ? $isjia = 0 : '';
            $where[] = ['u.is_jia', '=', $isjia];
        }
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['u.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1] . ' 23:59:59')]];
        }
        $this->order = input('order/s', '');
        switch ($this->order) {
            case "recharge":
                $order = 'u.all_recharge_num desc';
                break;
            case "recharge_count":
                $order = 'u.all_recharge_count desc';
                break;
            case "deposit":
                $order = 'u.all_deposit_num desc';
                break;
            case "deposit_count":
                $order = 'u.all_deposit_count desc';
                break;
            default:
                $order = 'u.id desc';
                break;
        }
        $this->level = input('level', -1);

        $this->group_id = input('group_id', -1);
        if ($this->level != -1) $where[] = ['u.level', '=', $this->level];
        if ($this->group_id != -1) $where[] = ['u.group_id', '=', $this->group_id];
        $this->level_list = Db::name('xy_level')->field('level,name')->select();
        $this->groupList = Db::table('xy_group')
            ->where('agent_id', 'in', [$this->agent_id, 0])
            ->field('id,title')
            ->column('title', 'id');

        $this->groupAllList = Db::table('xy_group')
            ->field('id,title')
            ->column('title', 'id');

        $this->agent_service_id = input('agent_service_id/d', 0);
        if ($this->agent_id) {
            $this->agent_uid = model('admin/Users')->get_admin_agent_uid();
            if ($this->agent_uid) {
                $where[] = ['u.agent_service_id', '=', $this->agent_id];
            } else {
                $where[] = ['u.agent_id', '=', $this->agent_id];
            }
            $this->agent_list = [];
            $this->agent_id = $this->agent_id;
            $this->agent_service_list = Db::name('system_user')
                ->where('parent_id', $this->agent_id)
                ->where('authorize', "2")
                ->field('id,username')
                ->where('is_deleted', 0)
                ->column('username', 'id');
            if ($this->agent_service_list &&
                $this->agent_service_id &&
                !array_key_exists($this->agent_service_id, $this->agent_service_list)) {
                $this->agent_service_id = 0;
            }
        } else {
            $this->agent_list = Db::name('system_user')
                ->where('user_id', 0)
                ->where('authorize', "2")
                ->field('id,username')
                ->where('is_deleted', 0)
                ->column('username', 'id');
            $this->agent_service_list = Db::name('system_user')
                ->where('user_id', '>', 0)
                ->where('authorize', "2")
                ->field('id,username')
                ->where('is_deleted', 0)
                ->column('username', 'id');
            $this->agent_id = input('agent_id/d', 0);
            if ($this->agent_id) {
                $query->where('u.agent_id', $this->agent_id);
            }
        }
        if ($this->agent_service_id) {
            $query->where('u.agent_service_id', $this->agent_service_id);
        }

        $query->field('u.id,u.level,u.deal_count,u.agent_id,u.tel,u.username,u.group_id,u.remarks,
        u.lixibao_balance,u.id_status,u.ip,u.country,u.is_jia,u.addtime,u.invite_code,u.lottery_money,
        u.all_recharge_num,u.all_deposit_num,u.all_recharge_count,u.all_deposit_count,u.agent_service_id,u.parent_id,u.login_time,
        u.freeze_balance,u.status,u.balance,u.xyf,u1.tel as parent_tel,u1.username as parent_name,sum(im.scale) as scale,lve.name as level_name,lve.order_num as level_num')
            ->leftJoin('xy_inyectar im', 'u.id = im.uid ')
            ->leftJoin('xy_users u1', 'u.relation_id=u1.id')
            // ->leftJoin('xy_users u1', 'u.parent_id=u1.id')
            ->leftJoin('xy_level lve', 'u.level=lve.level')
            ->where($where)
            ->order($order)
            ->group('u.id')
            ->page();

    }

    /**
     * 会员列表(假人)
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function indexjia()
    {
        $this->title = '会员列表(假人)';
        $query = $this->_query($this->table)->alias('u');
        $where = [];
        $where[] = ['u.is_jia', '=', 1];
        if (input('tel/s', '')) $where[] = ['u.tel', '=', input('tel/s', '')];
        if (input('invite_code/s', '')) $where[] = ['u.invite_code', '=', input('invite_code/s')];
        if (input('username/s', '')) $where[] = ['u.username', 'like',input('username/s', '') . '%'];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['u.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1] . ' 23:59:59')]];
        }
        $this->order = input('order/s', '');
        switch ($this->order) {
            case "recharge":
                $order = 'u.all_recharge_num desc';
                break;
            case "recharge_count":
                $order = 'u.all_recharge_count desc';
                break;
            case "deposit":
                $order = 'u.all_deposit_num desc';
                break;
            case "deposit_count":
                $order = 'u.all_deposit_count desc';
                break;
            default:
                $order = 'u.id desc';
                break;
        }
        $this->level = input('level', -1);

        $this->group_id = input('group_id', -1);
        if ($this->level != -1) $where[] = ['u.level', '=', $this->level];
        if ($this->group_id != -1) $where[] = ['u.group_id', '=', $this->group_id];
        $this->level_list = Db::name('xy_level')->field('level,name')->select();
        $this->groupList = Db::table('xy_group')
            ->where('agent_id', 'in', [$this->agent_id, 0])
            ->field('id,title')
            ->column('title', 'id');

        $this->groupAllList = Db::table('xy_group')
            ->field('id,title')
            ->column('title', 'id');

        $this->agent_service_id = input('agent_service_id/d', 0);
        if ($this->agent_id) {
            $this->agent_uid = model('admin/Users')->get_admin_agent_uid();
            if ($this->agent_uid) {
                $where[] = ['u.agent_service_id', '=', $this->agent_id];
            } else {
                $where[] = ['u.agent_id', '=', $this->agent_id];
            }
            $this->agent_list = [];
            $this->agent_id = $this->agent_id;
            $this->agent_service_list = Db::name('system_user')
                ->where('parent_id', $this->agent_id)
                ->where('authorize', "2")
                ->field('id,username')
                ->where('is_deleted', 0)
                ->column('username', 'id');
            if ($this->agent_service_list &&
                $this->agent_service_id &&
                !array_key_exists($this->agent_service_id, $this->agent_service_list)) {
                $this->agent_service_id = 0;
            }
        } else {
            $this->agent_list = Db::name('system_user')
                ->where('user_id', 0)
                ->where('authorize', "2")
                ->field('id,username')
                ->where('is_deleted', 0)
                ->column('username', 'id');
            $this->agent_service_list = Db::name('system_user')
                ->where('user_id', '>', 0)
                ->where('authorize', "2")
                ->field('id,username')
                ->where('is_deleted', 0)
                ->column('username', 'id');
            $this->agent_id = input('agent_id/d', 0);
            if ($this->agent_id) {
                $query->where('u.agent_id', $this->agent_id);
            }
        }
        if ($this->agent_service_id) {
            $query->where('u.agent_service_id', $this->agent_service_id);
        }

         $query->field('u.id,u.level,u.deal_count,u.agent_id,u.tel,u.username,u.group_id,u.remarks,
        u.lixibao_balance,u.id_status,u.ip,u.country,u.is_jia,u.addtime,u.invite_code,u.agent_service_id,
        u.all_recharge_num,u.all_deposit_num,u.all_recharge_count,u.all_deposit_count,
        u.freeze_balance,u.status,u.balance,u.xyf,u1.username as parent_name,sum(im.scale) as scale,lve.name as level_name,lve.order_num as level_num')
            ->leftJoin('xy_inyectar im', 'u.id = im.uid ')
            ->leftJoin('xy_users u1', 'u.parent_id=u1.id')
            ->leftJoin('xy_level lve', 'u.level=lve.level')
            ->where($where)
            ->order($order)
            ->group('u.id')
            ->page();
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
        $admins = Db::name('system_user')->field('id,username')->column('username', 'id');
        // dump($data);die;
        $parent_commission = 0;
        foreach ($data as &$vo) {
            if (isset($vo['parent_id'])&&$vo['parent_id']>0) {
                $data2 = Db::name('xy_users')->where('parent_id', $vo['id'])->field('id,username,deal_count')->select();
                foreach ($data2 as $k=> $v){
                    $data[$k]['commission'] = Db::name('xy_convey')->where('uid',$v['id'])->where('p_status',1)->sum('parent_commission');
                }
                $result = Db::name('xy_convey')->field('uid,addtime')->where('parent_uid',$vo['id'])->where('p_status',1)->where('deal_count',60)->select();
                $parent_commission = 0;
                foreach ($result as  $vv){
                    $r = Db::name('xy_convey')->where('uid',$vv['uid'])->where('addtime','<=',$vv['addtime'])->sum('parent_commission');
                    $parent_commission = $parent_commission+$r;
                }
            }
            $vo['parent_commission'] = $parent_commission;
            //判断ip是否重复
            if (isset($vo['ip'])&&$vo['ip']) {
                $vo['cip'] = Db::name('xy_users')->where('ip', $vo['ip'])->count('id');//获取重复数量
            } else {
                $vo['cip'] = 0;
            }

            $vo['agent'] = isset($admins[$vo['agent_id']])&&$vo['agent_id'] ? $admins[$vo['agent_id']] : '';
            $vo['service'] = isset($admins[$vo['agent_service_id']])&&$vo['agent_service_id'] ? $admins[$vo['agent_service_id']] : '';
            // $vo['service'] = '';
            // //查询等级
            $par = Db::name('system_user')->find($vo['agent_id']);
            if ($par) {
                $vo['par'] = $par['parent_id'];
            } else {
                $vo['par'] = '';
            }

            // $s = model('Users')->get_user_service_id($vo['id']);
            // if ($s) $vo['service'] = $s['username'];
            $vo['com'] = Db::name('xy_balance_log')->where('uid', $vo['id'])
                ->where('type', 3)->where('status', 1)->sum('num');
            $vo['tj_com'] = Db::name('xy_balance_log')->where('uid', $vo['id'])
                ->where('type', 6)->where('status', 1)->sum('num');

            $vo['day_d_count'] = Db::name('xy_convey')->whereTime('addtime', 'today')->where('uid', $vo['id'])->where('status', 'in', [0, 1, 3, 5])->count('id');
            // $vo['day_d_count'] = Db::name('xy_convey')->where('uid',$vo['id'])->where('status','in',[0,1,3,5])->count('id');
            $order_num = Db::name('xy_level')->where('level', $vo['level'])->value('order_num');
            $vo['zu'] = $order_num > 0 ? $vo['day_d_count'] / $order_num : $vo['day_d_count'];
            $vo['zu'] = floor($vo['zu']);
            $vo['order_num'] = Db::name('xy_convey')->where('uid', $vo['id'])->whereTime('addtime', 'today')->count('id');
            $vo['order_incomplete_num'] = Db::name('xy_convey')->where('uid', $vo['id'])->where('status', 'in', [0, 2, 4, 5])->count('id');
        }
        $data = Data::arr2table($data);
    }

    /**
     * 表单数据处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
      public function _indexjia_page_filter(&$data)
    {
        $admins = Db::name('system_user')->field('id,username')->column('username', 'id');
        
        foreach ($data as &$vo) {

            //判断ip是否重复
            if ($vo['ip']) {
                $vo['cip'] = Db::name('xy_users')->where('ip', $vo['ip'])->count('id');//获取重复数量
            } else {
                $vo['cip'] = 0;
            }

            $vo['agent'] = $vo['agent_id'] ? $admins[$vo['agent_id']] : '';
            $vo['service'] = '';
            //查询等级
            $par = Db::name('system_user')->find($vo['agent_id']);
            if ($par) {
                $vo['par'] = $par['parent_id'];
            } else {
                $vo['par'] = '';
            }

            $s = model('Users')->get_user_service_id($vo['id']);
            if ($s) $vo['service'] = $s['username'];
            $vo['com'] = Db::name('xy_balance_log')->where('uid', $vo['id'])
                ->where('type', 3)->where('status', 1)->sum('num');
            $vo['tj_com'] = Db::name('xy_balance_log')->where('uid', $vo['id'])
                ->where('type', 6)->where('status', 1)->sum('num');

            $vo['day_d_count'] = Db::name('xy_convey')->whereTime('addtime', 'today')->where('uid', $vo['id'])->where('status', 'in', [0, 1, 3, 5])->count('id');
            $order_num = Db::name('xy_level')->where('level', $vo['level'])->value('order_num');
            $vo['zu'] = $order_num > 0 ? $vo['day_d_count'] / $order_num : $vo['day_d_count'];
            $vo['zu'] = floor($vo['zu']);
            $vo['order_num'] = Db::name('xy_convey')->where('uid', $vo['id'])->whereTime('addtime', 'today')->count('id');
            $vo['order_incomplete_num'] = Db::name('xy_convey')->where('uid', $vo['id'])->where('status', 'in', [0, 2, 4, 5])->count('id');
        }
        $data = Data::arr2table($data);
    }
    public function _indexjia_page_filter2(&$data)
    {
        $admins = Db::name('system_user')->field('id,username')->column('username', 'id');
        foreach ($data as &$vo) {

            //判断ip是否重复
            if ($vo['ip']) {
                $vo['cip'] = Db::name('xy_users')->where('ip', $vo['ip'])->count('id');//获取重复数量
            } else {
                $vo['cip'] = 0;
            }
            $vo['agent'] = $vo['agent_id'] ? $admins[$vo['agent_id']] : '';
            $vo['service'] = '';
            //查询等级
            $par = Db::name('system_user')->find($vo['agent_id']);
            if ($par) {
                $vo['par'] = $par['parent_id'];
            } else {
                $vo['par'] = '';
            }

            $s = model('Users')->get_user_service_id($vo['id']);
            if ($s) $vo['service'] = $s['username'];
            $vo['com'] = Db::name('xy_balance_log')->where('uid', $vo['id'])
                ->where('type', 3)->where('status', 1)->sum('num');
            $vo['tj_com'] = Db::name('xy_balance_log')->where('uid', $vo['id'])
                ->where('type', 6)->where('status', 1)->sum('num');

            $vo['day_d_count'] = Db::name('xy_convey')->whereTime('addtime', 'today')->where('uid', $vo['id'])->where('status', 'in', [0, 1, 3, 5])->count('id');
            // $vo['day_d_count'] = Db::name('xy_convey')->where('uid',$vo['id'])->where('status','in',[0,1,3,5])->count('id');
            $order_num = Db::name('xy_level')->where('level', $vo['level'])->value('order_num');
            $vo['zu'] = $order_num > 0 ? $vo['day_d_count'] / $order_num : $vo['day_d_count'];
            $vo['zu'] = floor($vo['zu']);
            $vo['order_num'] = Db::name('xy_convey')->where('uid', $vo['id'])->whereTime('addtime', 'today')->count('id');
            $vo['order_incomplete_num'] = Db::name('xy_convey')->where('uid', $vo['id'])->where('status', 'in', [0, 2, 4, 5])->count('id');
        }
        $data = Data::arr2table($data);
    }

    //批量设置规格
    public function setRule()
    {

        $uids = input('uids');
        $group_id = input('rule');
        $res = db('xy_users')->where('id', 'in', implode(',', $uids))->update(['group_id' => $group_id]);
        if (!$res) $this->error('设置失败');
        if ($res) $this->success('设置成功');
    }


    /**
     * 会员等级列表
     * @menu true
     */
    public function level()
    {
        $this->title = '用户等级';
        $this->_query('xy_level')->page();
    }


    /**
     * 账变
     * @auth true
     */
    public function caiwu()
    {
        $uid = input('get.id/d', 1);
        $this->uid = $uid;
        $this->uinfo = db('xy_users')->find($uid);
        //
        if (isset($_REQUEST['iasjax'])) {
            $page = input('get.page/d', 1);
            $num = input('get.num/d', 20);
            $level = input('get.level/d', 1);
            $limit = ((($page - 1) * $num) . ',' . $num);
            $where = [];
            if ($level == 1) {
                $where[] = ['uid', '=', $uid];
            }
            $type = input('type', 0);
            if ($type != 0) {
                $where[] = ['type', '=', $type != -1 ? $type : 0];
            }
            if (input('addtime/s', '')) {
                $arr = explode(' - ', input('addtime/s', ''));
                $where[] = ['addtime', 'between', [strtotime($arr[0]), strtotime($arr[1])]];
            }
            $count = $data = db('xy_balance_log')->where($where)->count('id');
            $data = db('xy_balance_log')
                ->where($where)
                ->order('id desc')
                ->limit($limit)
                ->select();

            if ($data) {
                foreach ($data as &$datum) {
                    $datum['tel'] = $this->uinfo['tel'];
                    $datum['addtime'] = date('Y/m/d H:i', $datum['addtime']);;
                    switch ($datum['type']) {
                        case 0:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-danger">系统</span>';
                            break;
                        case 1:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-warm">充值</span>';
                            break;
                        case 2:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-danger">交易</span>';
                            break;
                        case 3:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-normal">返佣</span>';
                            break;
                        case 4:
                            $text = '<span class="layui-btn layui-btn-sm ">强制交易</span>';
                            break;
                        case 5:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-danger">推广返佣</span>';
                            break;
                        case 6:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-normal">下级交易返佣</span>';
                            break;
                        case 7:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-danger">提现</span>';
                            break;
                        case 8:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-danger">提现驳回</span>';
                            break;
                        case 21:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-danger">利息宝入</span>';
                            break;
                        case 22:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-danger">利息宝出</span>';
                            break;
                        case 23:
                            $text = '<span class="layui-btn layui-btn-sm layui-btn-danger">利息宝返佣</span>';
                            break;
                        default:
                            $text = '其他';
                    }
                    $datum['type'] = $text;
                    if ($datum['status'] == 1) $datum['status'] = '收入';
                    elseif ($datum['status'] == 2) $datum['status'] = '支出';
                    else $datum['status'] = '未知';

                }
            }

            if (!$data) json(['code' => 1, 'info' => '暂无数据']);
            return json(['code' => 0, 'count' => $count, 'info' => '请求成功', 'data' => $data, 'other' => $limit]);
        }


        $this->rechagreCount = Db::name('xy_recharge')
            ->where('uid', $uid)
            ->where('status', 2)
            ->sum('num');
        $this->depositCount = Db::name('xy_deposit')
            ->where('uid', $uid)
            ->where('status', 2)
            ->sum('num');
        return $this->fetch();
    }

    /**
     * 添加会员
     * @auth true
     * @menu true
     */
    public function add_users()
    {
        $this->assign('admin_id',$this->adminId);
        if (request()->isPost()) {
            $tel = input('post.tel/s', '');
            $user_name = input('post.user_name/s', '');
            $pwd = input('post.pwd/s', '');
            $pwd2 = input('post.pwd2/s', '');
            $parent_id = input('post.parent_id/d', 0);
            $yqm = input('post.yqm');
            $token = input('__token__', 1);
            $agent_id = input('post.agent_id');
            
            if(!$agent_id){
                $agent_id = $this->agent_id;
            }
            
            $res = model('Users')->add_users($tel, $user_name, $pwd, $parent_id, $token, $pwd2, $agent_id, '', '', $yqm,'1');
            if ($res['code'] !== 0) {
                return $this->error($res['info']);
            }
            //如果是二级
            if ($this->agent_uid) {
                $sys = Db::name('system_user')->where('id', $this->agent_id)->find();
                Db::name($this->table)
                    ->where('id', $res['id'])
                    ->update([
                        'agent_id' => $sys['parent_id'],
                        'agent_service_id' => $sys['id']
                    ]);
            }
            sysoplog('添加新用户', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            return $this->success($res['info']);
        }
        $this->agent_list = Db::name('system_user')
            ->where('user_id', 0)
            ->where('authorize', "2")
            ->field('id,username')
            ->where('is_deleted', 0)
            ->select();
            
        return $this->fetch();
    }

    /**
     * 编辑会员
     * @auth true
     * @menu true
     */
    public function edit_users()
    {
        $id = input('get.id', 0);
        if (!$id) $this->error('参数错误');
        $this->info = Db::table($this->table)->find($id);
        $this->assign('admin_id',$this->adminId);
        if (request()->isPost()) {
            $id = input('post.id/d', 0);
            $tel = input('post.tel/s', '');
            $user_name = input('post.user_name/s', '');
            $pwd = input('post.pwd/s', '');
            $pwd2 = input('post.pwd2/s', '');
            $parent_id = input('post.parent_id/d', 0);
            $level = input('post.level/d', 0);
            $agent_id = input('post.agent_id/d', 0);
            $agent_service_id = input('post.agent_service_id/d', 0);
            if ($this->adminId !=10000){
                $agent_id = $this->info['agent_id'];
                $agent_service_id = $this->info['agent_service_id'];
            }
            $group_id = input('post.group_id/d', 0);
            $freeze_balance = input('post.freeze_balance/d', 0);
            $balance = input('post.balance/j', 0);
            $deal_status = input('post.deal_status/d', 1);
            $token = input('__token__');
            $xyf = input('post.xyf/d');
            $deal_min_num = input('post.deal_min_num/f',0);
            $deal_max_num = input('post.deal_max_num/f',0);
            $remarks = input('post.remarks/s', '');
            $address = input('post.address/s', '');
            $relation_id = input('post.relation_id/d', 0);
            $res = model('Users')
                ->edit_users($id, $tel, $user_name, $pwd, $parent_id, $balance, $freeze_balance, $token, $pwd2);
            $res2 = Db::table($this->table)->where('id', $id)->update([
                'deal_status' => $deal_status,
                'level' => $level,
                'group_id' => $group_id,
                'agent_id' => $agent_id,
                'agent_service_id' => $agent_service_id,
                'xyf' => $xyf,
                'deal_min_num' => $deal_min_num,
                'deal_max_num' => $deal_max_num,
                'remarks' => $remarks,
                'address' => $address,
                'relation_id' => $relation_id,
            ]);
            if($relation_id>0){
                $res2 = Db::table($this->table)->where('id', $relation_id)->update([
                'relation_id' => $id,
            ]);
            }else if($relation_id==0&& $this->info['relation_id']>0){
                 $res2 = Db::table($this->table)->where('id', $this->info['relation_id'])->update([
                'relation_id' => 0,
            ]);
            }
            sysoplog('编辑用户', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            return $this->success(lang('czcg'));
        }
        $this->agent_list = Db::name('system_user')
            ->where('user_id', 0)
            ->where('authorize', "2")
            ->field('id,username')
            ->where('is_deleted', 0);
        $this->agent_list = $this->agent_list->select();
        $this->level = Db::table('xy_level')->select();
        $this->groupList = Db::table('xy_group')->where('agent_id', 'in', [$this->agent_id, 0])->select();
        $t = strtotime(date('Y-m-d'));
        if ($this->info['group_id'] > 0) {
            $this->converNumber = Db::name('xy_convey')
                ->where('uid', $id)
                ->where('group_id', $this->info['group_id'])
                ->order('addtime desc')
                ->limit(1)
                ->value('group_rule_num');
        } else {
            $this->converNumber = Db::name('xy_convey')
                ->where('uid', $id)
                ->where('level_id', $this->info['level'])
                ->where('addtime', 'between', [$t, $t + 86400])
                ->count('id');
        }
        $this->converNumber = $this->converNumber ? $this->converNumber : 0;
        return $this->fetch();
    }

    /**
     * 更改用户等级
     * @auth true
     */
    public function edit_level()
    {
        $id = input('get.id', 0);
        if (request()->isPost()) {
            $id = input('post.id/d', 0);
            $level = input('post.level/d', 0);
            $group_id = input('post.group_id/d', 0);
            $token = input('__token__');
            $res2 = Db::table($this->table)->where('id', $id)->update([
                'level' => $level,
                'group_id' => $group_id,
            ]);
            sysoplog('更改用户等级', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            return $this->success(lang('czcg'));
        }
        $this->agent_list = Db::name('system_user')
            ->where('user_id', 0)
            ->where('authorize', "2")
            ->field('id,username')
            ->where('is_deleted', 0)
            ->select();
        if (!$id) $this->error('参数错误');
        $this->info = Db::table($this->table)->find($id);
        $this->level = Db::table('xy_level')->select();
        //$this->level = Db::table('xy_level')->where("status=1")->select();

        $this->groupList = Db::table('xy_group')->where('agent_id', 'in', [$this->agent_id, 0])->select();
        $t = strtotime(date('Y-m-d'));
        if ($this->info['group_id'] > 0) {
            $this->converNumber = Db::name('xy_convey')
                ->where('uid', $id)
                ->where('group_id', $this->info['group_id'])
                ->order('addtime desc')
                ->limit(1)
                ->value('group_rule_num');
        } else {
            $this->converNumber = Db::name('xy_convey')
                ->where('uid', $id)
                ->where('level_id', $this->info['level'])
                ->where('addtime', 'between', [$t, $t + 86400])
                ->count('id');
        }
        $this->converNumber = $this->converNumber ? $this->converNumber : 0;
        return $this->fetch();
    }

    /**
     * 调整余额
     * @auth true
     */
    public function edit_money()
    {
        $uid = input('get.id', 0);
        if (!$uid) $this->error('参数错误');
        if (request()->isPost()) {
            $uid = input('post.id/d', 0);
            $money = input('post.money/f', 0);
            if ($money > 0) {
                Db::table($this->table)
                    ->where('id', $uid)
                    ->inc('balance', $money)->inc('lottery_money', $money)
                    ->update();
                    
                $id = getSn('SY');
                $uinfo = Db::name('xy_users')->field('pwd2,salt2,tel')->find($uid);
                $res = db('xy_recharge')
                    ->insert([
                        'id' => $id,
                        'uid' => $uid,
                        'tel' => $uinfo['tel'],
                        'real_name' => '',
                        'pic' => '',
                        'num' => $money,
                        'addtime' => time(),
                        'pay_status' =>1,
                        'status' =>2,
                        'type'  =>1
                    ]);
                /*$res2 = Db::name('xy_balance_log')->insert([
                    'uid' => $id,
                    'sid' => 0,
                    'oid' => 0,
                    'num' => $money,
                    'type' => 1,
                    'status' => 1,
                    'addtime' => time()
                ]);*/
            } else {
                $money = floatval(substr($money, 1));
                Db::table($this->table)
                    ->where('id', $uid)
                    ->dec('balance', $money)
                    // ->dec('balance', $money)->inc('all_deposit_num', $money)->inc('all_deposit_count', 1)
                    ->update();
                /*$res2 = Db::name('xy_balance_log')->insert([
                    'uid' => $id,
                    'sid' => 0,
                    'oid' => 0,
                    'num' => $money,
                    'type' => 7,
                    'status' => 2,
                    'addtime' => time()
                ]);*/
            }
//编辑用户彩金
            sysoplog('调整余额', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            return $this->success(lang('czcg'));
        }

        $this->info = Db::table($this->table)->find($uid);
        return $this->fetch();
    }

    /**
     * 
     */
    public function liandan()
    {
        $id = input('get.uid', 0);
        if (!$id) $this->error('参数错误');
        if (request()->isPost()) {
            $start = input('post.start/d', 0);
            $goods_id_arr = input('post.goods_id_arr2', 0);
            Db::table($this->table)
                ->where('id', $id)
                ->update(['start' => $start, 'goods_id_arr' => $goods_id_arr]);
            // if ($money > 0) {
            //     Db::table($this->table)
            //         ->where('id', $id)
            //         ->inc('balance', $money)
            //         ->update();
            // } else {
            //     $money = floatval(substr($money, 1));
            //     Db::table($this->table)
            //         ->where('id', $id)
            //         ->dec('balance', $money)
            //         ->update();
            // }
            // sysoplog('编辑用户彩金', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            return $this->success(lang('czcg'));
        }
        // $this->order_num = Db::name('xy_convey')->where('uid',$id)->count('id'); 
        $this->info = Db::table($this->table)->find($id);
        $this->order_num = $this->info['deal_count'];
        $this->level = db('xy_level')->where('level', $this->info['level'])->find();
        return $this->fetch();
    }

    public function change_next_goods()
    {
        $id = input('post.uid', 0);
        $goods_id = input('post.goods_id', 0);
        if (!$id) $this->error('参数错误');
        $info = Db::table($this->table)->find($id);
        if ($info['goods_id_arr'] != '') {
            $info['goods_id_arr'] = explode(',', $info['goods_id_arr']);
            $info['goods_id_arr'][0] = $goods_id;
            $info['goods_id_arr'] = implode(',', $info['goods_id_arr']);
            Db::table($this->table)
                ->where('id', $id)
                ->update(['goods_id_arr' => $info['goods_id_arr']]);
        } else {
            Db::table($this->table)
                ->where('id', $id)
                ->update(['goods_id_arr' => $goods_id]);
        }
        return $this->success(lang('czcg'));
    }

    /**
     * 商品管理
     * @menu true
     */
    public function goods_list()
    {
        $this->title = '派单管理';
        $uid = input('get.uid/d', '0');
        $data = Db::table('xy_send_order')->where('uid',$uid)->order('addtime desc')->select();
        if($data){
            foreach ($data as $k=>$v){
                $data[$k]['addtime'] = date('Y-m-d H:i:s',$v['addtime']);
            }
        }
        $this->success($data);
    }
    /**
     * 删除派单
     * @auth true
     */
    public function delect_order(){
        $id = input('post.id/d', '');
        $uid = input('post.uid/d', '');
        $res = false;
        if ($id){
            $res = Db::table('xy_send_order')->where('id', $id)->delete();
        }
        if($uid){
            $res = Db::table('xy_send_order')->where('uid',$uid)->where('status', 2)->delete();
        }
        if ($res) {
            sysoplog('删除派单', 'ID ' . $id.' UID '.$uid);
            $this->success('删除成功!');
        } else $this->error('删除失败!');
    }

    /**
     * 删除会员
     * @auth true
     */
    public function delete_user()
    {
        $this->applyCsrfToken();
        $id = input('post.id/d', '');
        $res = Db::table('xy_users')->where('id', $id)->delete();
        if ($res) {
            Db::table('xy_users_invites')->where('uid', $id)->delete();
            sysoplog('删除用户', 'ID ' . $id);
            $this->success('删除成功!');
        } else $this->error('删除失败!');
    }

    public function del_level()
    {
        $this->applyCsrfToken();
        //$id = input('post.id/d', 0);
        $id = input('get.id');
        $res = Db::table('xy_level')->where(['id' => $id])->delete();
        if ($res) {
            $this->success('删除成功!');
        } else $this->error('删除失败!');
    }

//    /**
//     * 编辑会员_暗扣
//     * @auth true
//     */
    public function edit_users_ankou()
    {
        $id = input('get.id', 0);
        if (request()->isPost()) {
            $id = input('post.id/d', 0);
            $kouchu_balance_uid = input('post.kouchu_balance_uid/d', 0); //扣除人
            $kouchu_balance = input('post.kouchu_balance/f', 0); //扣除金额
            $show_td = (isset($_REQUEST['show_td']) && $_REQUEST['show_td'] == 'on') ? 1 : 0;//显示我的团队
            $show_cz = (isset($_REQUEST['show_cz']) && $_REQUEST['show_cz'] == 'on') ? 1 : 0;//显示充值
            $show_tx = (isset($_REQUEST['show_tx']) && $_REQUEST['show_tx'] == 'on') ? 1 : 0;//显示提现
            $show_num = (isset($_REQUEST['show_num']) && $_REQUEST['show_num'] == 'on') ? 1 : 0;//显示推荐人数
            $show_tel = (isset($_REQUEST['show_tel']) && $_REQUEST['show_tel'] == 'on') ? 1 : 0;//显示电话
            $show_tel2 = (isset($_REQUEST['show_tel2']) && $_REQUEST['show_tel2'] == 'on') ? 1 : 0;//显示电话隐藏
            $token = input('__token__');
            $data = [
                '__token__' => $token,
                'show_td' => $show_td,
                'show_cz' => $show_cz,
                'show_tx' => $show_tx,
                'show_num' => $show_num,
                'show_tel' => $show_tel,
                'show_tel2' => $show_tel2,
                'kouchu_balance_uid' => $kouchu_balance_uid,
                'kouchu_balance' => $kouchu_balance,
            ];
            //var_dump($data,$_REQUEST);die;
            unset($data['__token__']);
            $res = Db::table($this->table)->where('id', $id)->update($data);
            if (!$res) {
                return $this->error('编辑失败!');
            }
            sysoplog('编辑会员暗扣', json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->success('编辑成功!');
        }

        if (!$id) $this->error('参数错误');
        $this->info = Db::table($this->table)->find($id);

        //
        $uid = $id;
        $data = db('xy_users')->where('parent_id', $uid)
            ->field('id,username,headpic,addtime,childs,tel')
            ->order('addtime desc')
            ->select();

        foreach ($data as &$datum) {
            //充值
            $datum['chongzhi'] = db('xy_recharge')->where('uid', $datum['id'])->where('status', 2)->sum('num');
            //提现
            $datum['tixian'] = db('xy_deposit')->where('uid', $datum['id'])->where('status', 1)->sum('num');
        }

        //var_dump($data,$uid);die;

        //$this->cate = db('xy_goods_cate')->order('addtime asc')->select();
        $this->assign('cate', $data);

        return $this->fetch();
    }

    /**
     * 编辑会员登录状态
     * @auth true
     */
    public function edit_users_status()
    {
        $id = input('id/d', 0);
        $status = input('status/d', 0);
        if (!$id || !$status) return $this->error('参数错误');
        $res = model('Users')->edit_users_status($id, $status);
        if ($res['code'] !== 0) {
            return $this->error($res['info']);
        }
        sysoplog('编辑会员登录状态', "ID {$id} status {$status}");
        return $this->success($res['info']);
    }

    /**
     * 编辑银行卡信息
     */
    public function edit_users_address()
    {
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $id = input('post.id/d', 0);
            $tel = input('post.tel/s', '');
            $name = input('post.name/s', '');
            $address = input('post.address/s', '');

            $res = db('xy_member_address')->where('id', $id)->update(
                ['tel' => $tel,
                    'name' => $name,
                    'address' => $address
                ]);
            if ($res !== false) {
                sysoplog('编辑银行卡信息', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success('操作成功');
            } else {
                return $this->error('操作失败');
            }
        }

        //$data = db('xy_member_address')->where('uid',$id)->select();
        $uid = input('id/d', 0);
        $this->bk_info = Db::name('xy_member_address')->where('uid', input('id/d', 0))->select();
        if (!$this->bk_info) {
            //$this->error('没有数据');
            $data = [
                'uid' => input('id/d', 0),
                'name' => '',
                'tel' => '',
                'area' => '',
                'address' => '',
                'is_default' => 1,
                'addtime' => time()
            ];
            $tmp = db('xy_member_address')->where('uid', $uid)->find();
            if (!$tmp) $data['is_default'] = 1;
            $res = db('xy_member_address')->insert($data);

            $this->bk_info = Db::name('xy_member_address')->where('uid', input('id/d', 0))->select();

        }
        return $this->fetch();
    }

    /**
     * 编辑会员登录状态
     * @auth true
     */
    public function edit_users_status2()
    {
        $id = input('id/d', 0);
        $status = input('status/d', 0);
        if (!$id || !$status) return $this->error('参数错误');
        $status == -1 ? $status = 0 : '';
        $res = Db::table($this->table)->where('id', $id)->update(['is_jia' => $status]);
        if (!$res) {
            sysoplog('编辑会员真假人', "ID {$id} status {$status}");
            return $this->error('更新失败!');
        }
        return $this->success('更新成功');
    }

    /**
     * 编辑会员二维码
     */
    public function edit_users_ewm()
    {
        $id = input('id/d', 0);
        $invite_code = input('status/s', '');
        if (!$id || !$invite_code) return $this->error('参数错误');

        $n = ($id % 20);

        $dir = './upload/qrcode/user/' . $n . '/' . $id . '.png';
        if (file_exists($dir)) {
            unlink($dir);
        }

        $res = model('Users')->create_qrcode($invite_code, $id);
        if (0 && $res['code'] !== 0) {
            return $this->error('失败');
        }
        return $this->success('成功');
    }


    /**
     * 查看团队
     * @auth true
     */
    public function tuandui()
    {
        $uid = input('get.id/d', 1);
        if (isset($_REQUEST['iasjax'])) {
            $page = input('get.page/d', 1);
            $num = input('get.num/d', 10);
            $level = input('get.level/d', 1);
            $limit = ((($page - 1) * $num) . ',' . $num);
            $where = [];
            if ($level == -1) {
                $uids = model('Users')->child_user($uid, 5);
                $uids ? $where[] = ['u.id', 'in', $uids] : $where[] = ['u.id', 'in', [-1]];
            } else if ($level == 1) {
                $uids1 = model('Users')->child_user($uid, 1, 0);
                $uids1 ? $where[] = ['u.id', 'in', $uids1] : $where[] = ['u.id', 'in', [-1]];
            } else if ($level == 2) {
                $uids2 = model('Users')->child_user($uid, 2, 0);
                $uids2 ? $where[] = ['u.id', 'in', $uids2] : $where[] = ['u.id', 'in', [-1]];
            } else if ($level == 3) {
                $uids3 = model('Users')->child_user($uid, 3, 0);
                $uids3 ? $where[] = ['u.id', 'in', $uids3] : $where[] = ['u.id', 'in', [-1]];
            } else if ($level == 4) {
                $uids4 = model('Users')->child_user($uid, 4, 0);
                $uids4 ? $where[] = ['u.id', 'in', $uids4] : $where[] = ['u.id', 'in', [-1]];
            } else if ($level == 5) {
                $uids5 = model('Users')->child_user($uid, 5, 0);
                $uids5 ? $where[] = ['u.id', 'in', $uids5] : $where[] = ['u.id', 'in', [-1]];
            }

            if (input('tel/s', '')) $where[] = ['u.tel', 'like', '%' . input('tel/s', '') . '%'];
            if (input('username/s', '')) $where[] = ['u.username', 'like', '%' . input('username/s', '') . '%'];
            if (input('addtime/s', '')) {
                $arr = explode(' - ', input('addtime/s', ''));
                $where[] = ['u.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1])]];
            }

            $count = $data = db('xy_users')->alias('u')->where($where)->count('id');
            $query = db('xy_users')->alias('u');
            $data = $query->field('u.id,u.tel,u.username,u.id_status,u.childs,u.ip,u.is_jia,u.addtime,u.invite_code,u.freeze_balance,u.status,u.balance,u1.username as parent_name')
                ->leftJoin('xy_users u1', 'u.parent_id=u1.id')
                ->where($where)
                ->order('u.id desc')
                ->limit($limit)
                ->select();

            if ($data) {
                //
                $uid1s = model('Users')->child_user($uid, 1, 0);
                $uid2s = model('Users')->child_user($uid, 2, 0);
                $uid3s = model('Users')->child_user($uid, 3, 0);
                $uid4s = model('Users')->child_user($uid, 4, 0);
                $uid5s = model('Users')->child_user($uid, 5, 0);

                foreach ($data as &$datum) {
                    //佣金
                    $datum['yj'] = db('xy_balance_log')
                        ->where('status', 1)
                        ->where('type', 3)
                        ->where('uid', $datum['id'])
                        ->sum('num');
                    $datum['tj_yj'] = db('xy_balance_log')
                        ->where('status', 1)
                        ->where('type', 6)
                        ->where('uid', $datum['id'])
                        ->sum('num');
                    $datum['cz'] = db('xy_recharge')->where('status', 2)->where('uid', $datum['id'])->sum('num');
                    $datum['tx'] = db('xy_deposit')->where('status', 2)->where('uid', $datum['id'])->sum('num');
                    $datum['addtime'] = date('Y/m/d H:i', $datum['addtime']);;
                    $datum['jb'] = '三级';
                    $color = '#92c7ef';


                    if (in_array($datum['id'], $uid1s)) {
                        $datum['jb'] = '一级';
                        $color = '#1E9FFF';
                    }
                    if (in_array($datum['id'], $uid2s)) {
                        $datum['jb'] = '二级';
                        $color = '#2b9aec';
                    }
                    if (in_array($datum['id'], $uid3s)) {
                        $datum['jb'] = '三级';
                        $color = '#1E9FFF';
                    }
                    if (in_array($datum['id'], $uid4s)) {
                        $datum['jb'] = '四级';
                        $color = '#76c0f7';
                    }
                    if (in_array($datum['id'], $uid5s)) {
                        $datum['jb'] = '五级';
                        $color = '#92c7ef';
                    }

                    $datum['jb'] = '<span class="layui-btn layui-btn-xs layui-btn-danger" style="background: ' . $color . '">' . $datum['jb'] . '</span>';
                }
            }
            if (!$data) json(['code' => 1, 'info' => '暂无数据']);

            $tj_com = 0;
            switch ($level) {
                case -1:
                    $tj_com = Db::name('xy_balance_log')->where('uid', $uid)
                        ->where('type', 6)->where('status', 1)->sum('num');
                    break;
                case 1:
                    $tj_com = Db::name('xy_balance_log')
                        ->where('uid', $uid)
                        ->where('sid', 'in', $uids1 ?: [-1])
                        ->where('type', 6)
                        ->where('status', 1)
                        ->sum('num');
                    break;
                case 2:
                    $tj_com = Db::name('xy_balance_log')
                        ->where('uid', $uid)
                        ->where('sid', 'in', $uids2 ?: [-1])
                        ->where('type', 6)
                        ->where('status', 1)
                        ->sum('num');
                    break;
                case 3:
                    $tj_com = Db::name('xy_balance_log')
                        ->where('uid', $uid)
                        ->where('sid', 'in', $uids3 ?: [-1])
                        ->where('type', 6)
                        ->where('status', 1)
                        ->sum('num');
                    break;
            }
            return json([
                'code' => 0,
                'count' => $count,
                'info' => '请求成功',
                'data' => $data,
                'other' => $limit,
                'tj_com' => $tj_com
            ]);
        } else {
            //
            $this->uid = $uid;
            $this->uinfo = db('xy_users')->find($uid);

        }


        return $this->fetch();
    }

    /**
     * 封禁/解封会员
     * @auth true
     */
    public function open()
    {
        $uid = input('post.id/d', 0);
        $status = input('post.status/d', 0);
        $type = input('post.type/d', 0);
        $info = [];
        if ($uid) {
            if (!$type) {
                $status2 = $status ? 0 : 1;
                $res = db('xy_users')->where('id', $uid)->update(['status' => $status2]);
                return json(['code' => 1, 'info' => '请求成功', 'data' => $info]);
            } else {
                //

                $wher = [];
                $wher2 = [];


                $ids1 = db('xy_users')->where('parent_id', $uid)->field('id')->column('id');
                $ids1 ? $wher[] = ['parent_id', 'in', $ids1] : '';

                $ids2 = db('xy_users')->where($wher)->field('id')->column('id');
                $ids2 ? $wher2[] = ['parent_id', 'in', $ids2] : '';

                $ids3 = db('xy_users')->where($wher2)->field('id')->column('id');

                $idsAll = array_merge([$uid], $ids1, $ids2, $ids3);  //所有ids
                $idsAll = array_filter($idsAll);

                $wherAll[] = ['id', 'in', $idsAll];
                $users = db('xy_users')->where($wherAll)->field('id')->select();

                //var_dump($users);die;
                $status2 = $status ? 0 : 1;
                foreach ($users as $item) {
                    $res = db('xy_users')->where('id', $item['id'])->update(['status' => $status2]);
                }

                return json(['code' => 1, 'info' => '请求成功', 'data' => $info]);
            }


        }

        return json(['code' => 1, 'info' => '暂无数据']);
    }


    //查看图片
    public function picinfo()
    {
        $this->pic = input('get.pic/s', '');
        if (!$this->pic) return;
        $this->fetch();
    }
    /**
     * 客服管理
     * @auth true
     */
    public function cs_list()
    {
        $this->title = '客服列表';
        $where = [];
        if ($this->agent_id != 0) {
            $where['uid'] = $this->agent_id;
        }


//        if (input('tel/s', '')) $where[] = ['tel', 'like', '%' . input('tel/s', '') . '%'];
        if (input('username/s', '')) $where[] = ['username', 'like', '%' . input('username/s', '') . '%'];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['addtime', 'between', [strtotime($arr[0]), strtotime($arr[1])]];
        }
        $this->_query('xy_cs')
            ->where($where)
            ->page();
    }

    /**
     * 添加客服
     * @auth true
     */
    public function add_cs()
    {
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $username = input('post.username/s', '');
            $uid = input('post.uid/d', 0);
            $url = input('post.url/s', '');
            $time = input('post.time');
            $arr = explode('-', $time);
            $btime = substr($arr[0], 0, 5);
            $etime = substr($arr[1], 1, 5);
            $data = [
                'username' => $username,
                'uid' => $uid,
                'url' => $url,
                'btime' => $btime,
                'etime' => $etime,
                'addtime' => time(),
            ];
            $res = db('xy_cs')->insert($data);
            if ($res) {
                sysoplog('添加客服', json_encode($data, JSON_UNESCAPED_UNICODE));
                return $this->success('添加成功');
            }
            return $this->error('添加失败，请刷新再试');
        }
        return $this->fetch();
    }

    /**
     * 客服登录状态
     */
    public function edit_cs_status()
    {
        $this->applyCsrfToken();
        sysoplog('编辑客服状态', json_encode($_POST, JSON_UNESCAPED_UNICODE));
        $this->_save('xy_cs', ['status' => input('post.status/d', 1)]);
    }

    /**
     * 编辑客服信息
     * @auth true
     */
    public function edit_cs()
    {
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $id = input('post.id/d', 0);
            $username = input('post.username/s', '');
            $pwd = input('post.pwd/s', '');
            $uid = input('post.uid/d', 0);
            $url = input('post.url/s', '');
            $time = input('post.time');
            $arr = explode('-', $time);
            $btime = substr($arr[0], 0, 5);
            $etime = substr($arr[1], 1, 5);
            $data = [
                'username' => $username,
                'uid' => $uid,
                'url' => $url,
                'btime' => $btime,
                'etime' => $etime,
            ];
            if ($pwd) $data['pwd'] = $pwd;
            $res = db('xy_cs')->where('id', $id)->update($data);
            if ($res !== false) {
                sysoplog('编辑客服信息', json_encode($data, JSON_UNESCAPED_UNICODE));
                return $this->success('编辑成功');
            }
            return $this->error('编辑失败，请刷新再试');
        }
        $id = input('id/d', 0);
        $this->list = db('xy_cs')->find($id);
        return $this->fetch();
    }

    /**
     * 客服调用代码
     */
    public function cs_code()
    {
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $code = input('post.code');
            $res = db('xy_script')->where('id', 1)->update(['script' => $code]);
            if ($res !== false) {
                sysoplog('客服调用代码', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                $this->success('操作成功!');
            }
            $this->error('操作失败!');
        }
        $this->code = db('xy_script')->where('id', 1)->value('script');
        return $this->fetch();
    }

    /**
     * 银行卡信息
     * @auth true
     */
    public function edit_users_bk()
    {
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $id = input('post.id/d', 0);
            $res = db('xy_bankinfo')->where('id', $id)->update([
                'tel' => input('post.tel/s', ''),
                'site' => input('post.site/s', ''),
                'cardnum' => input('post.cardnum/s', ''),
                'username' => input('post.username/s', ''),
                'bankname' => input('post.bankname/s', ''),
                'bank_code' => input('post.bank_code/s', ''),
                'bank_branch' => input('post.bank_branch/s', ''),
                'document_id' => input('post.document_id/s', ''),
                'account_digit' => input('post.account_digit/s', ''),
                'wallet_tel' => input('post.wallet_tel/s', ''),
                'wallet_document_id' => input('post.wallet_document_id/s', ''),
            ]);
            if ($res !== false) {
                sysoplog('编辑银行卡信息', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success('操作成功');
            } else {
                return $this->error('操作失败');
            }
        }
        $this->bk_info = Db::name('xy_bankinfo')->where('uid', input('id/d', 0))->select();
        if (!$this->bk_info) $this->error('没有数据');
        return $this->fetch();
    }

    /**
     * USDT信息
     * @auth true
     */
    public function edit_user_wallet()
    {
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $id = input('post.id/d', 0);
            $res = db('user_wallet')->where('id', $id)->update([
                'address' => input('post.address/s', ''),
            ]);
            if ($res !== false) {
                sysoplog('编辑银行卡信息', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success('操作成功');
            } else {
                return $this->error('操作失败');
            }
        }
        $this->bk_info = Db::name('user_wallet')->where('uid', input('id/d', 0))->select();
        if (!$this->bk_info) $this->error('没有数据');
        return $this->fetch();
    }

    public function reset_task()
    {
        $id = input('post.uid', 0);
        Db::name('xy_users')->where('id', $id)->update(['goods_id_arr' => '', 'start' => 0, 'deal_count' => 0]);
        return $this->success('操作成功');
    }

    /**
     * 编辑会员等级
     * @auth true
     */
    public function edit_users_level()
    {
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $id = input('post.id/d', 0);
            $name = input('post.name/s', '');
            $level = input('post.level/d', 0);
            $num = input('post.num/s', '');
            $order_num = input('post.order_num/s', '');
            $bili = input('post.bili/s', '');
            $liandan = input('post.liandan/s', '');
            $tj_bili = input('post.tj_bili/s', '');
            $tixian_ci = input('post.tixian_ci/s', '');
            $tixian_min = input('post.tixian_min/s', '');
            $tixian_max = input('post.tixian_max/s', '');
            $auto_vip_xu_num = input('post.auto_vip_xu_num/s', '');
            $num_min = input('post.num_min/s', '');
            $tixian_nim_order = input('post.tixian_nim_order/d', 0);
            $tixian_shouxu = input('post.tixian_shouxu/f', 0);
            $is_invite = input('post.is_invite/d', 1);
            $task_num = input('post.task_num/d', 1);
            $zu_commiss = input('post.zu_commiss/s', '');
            $cate = Db::name('xy_goods_cate')->select();
            $cids = [];
            foreach ($cate as $item) {
                $k = 'cids' . $item['id'];
                if (isset($_REQUEST[$k]) && $_REQUEST[$k] == 'on') {
                    $cids[] = $item['id'];
                }
            }
            $cidsstr = implode(',', $cids);
            $res = db('xy_level')->where('id', $id)->update(
                [
                    'name' => $name,
                    'level' => $level,
                    'num' => $num,
                    'order_num' => $order_num,
                    'bili' => $bili,
                    'liandan' => $liandan,
                    'tj_bili' => $tj_bili,
                    'tixian_ci' => $tixian_ci,
                    'tixian_min' => $tixian_min,
                    'tixian_max' => $tixian_max,
                    'num_min' => $num_min,
                    'cids' => $cidsstr,
                    'tixian_nim_order' => $tixian_nim_order,
                    'auto_vip_xu_num' => $auto_vip_xu_num,
                    'tixian_shouxu' => $tixian_shouxu,
                    'is_invite' => $is_invite,
                    'task_num' => $task_num,
                    'zu_commiss' => $zu_commiss
                ]);
            if ($res !== false) {
                sysoplog('编辑会员等级', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success('操作成功');
            } else {
                return $this->error('操作失败');
            }
        }
        $this->bk_info = Db::name('xy_level')->where('id', input('id/d', 0))->select();
        $this->cate = Db::name('xy_goods_cate')->select();
        if (!$this->bk_info) $this->error('没有数据');
        return $this->fetch();
    }


    /**
     * 导出xls
     */
    public function daochu()
    {
        $map = array();
        //搜索时间
        if (!empty($start_date) && !empty($end_date)) {
            $start_date = strtotime($start_date . "00:00:00");
            $end_date = strtotime($end_date . "23:59:59");
            $map['_string'] = "( a.create_time >= {$start_date} and a.create_time < {$end_date} )";
        }
        $list = Db::name('xy_users u')->field('u.id,u.tel,u.username,u.lixibao_balance,u.id_status,u.ip,u.is_jia,u.addtime,u.invite_code,u.freeze_balance,u.status,u.balance,u1.username as parent_name')
            ->leftJoin('xy_users u1', 'u.parent_id=u1.id')
            ->where($map)
            ->order('u.id desc')
            ->select();
        foreach ($list as $k => &$_list) {
            //var_dump($_list);die;
            $_list['addtime'] ? $_list['addtime'] = date('m/d H:i', $_list['addtime']) : '';
        }
        //3.实例化PHPExcel类
        $objPHPExcel = new PHPExcel();
        //4.激活当前的sheet表
        $objPHPExcel->setActiveSheetIndex(0);
        //5.设置表格头（即excel表格的第一行）
        //$objPHPExcel
        $objPHPExcel->getActiveSheet()->setCellValue('A1', 'ID');
        $objPHPExcel->getActiveSheet()->setCellValue('B1', '账号');
        $objPHPExcel->getActiveSheet()->setCellValue('C1', '用户名');
        $objPHPExcel->getActiveSheet()->setCellValue('D1', '账号余额');
        $objPHPExcel->getActiveSheet()->setCellValue('E1', '冻结金额');
        $objPHPExcel->getActiveSheet()->setCellValue('F1', '利息宝余额');
        $objPHPExcel->getActiveSheet()->setCellValue('G1', '上级用户');
        $objPHPExcel->getActiveSheet()->setCellValue('H1', '邀请码');
        $objPHPExcel->getActiveSheet()->setCellValue('I1', '注册时间');
        $objPHPExcel->getActiveSheet()->setCellValue('J1', '最后登录IP');

        //设置A列水平居中
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('A')->getAlignment()
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        //设置单元格宽度
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('B')->setWidth(30);


        //6.循环刚取出来的数组，将数据逐一添加到excel表格。
        for ($i = 0; $i < count($list); $i++) {
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($i + 2), $list[$i]['id']);//ID
            $objPHPExcel->getActiveSheet()->setCellValue('B' . ($i + 2), $list[$i]['tel']);//标签码
            $objPHPExcel->getActiveSheet()->setCellValue('C' . ($i + 2), $list[$i]['username']);//防伪码
            $objPHPExcel->getActiveSheet()->setCellValue('D' . ($i + 2), $list[$i]['balance']);//防伪码
            $objPHPExcel->getActiveSheet()->setCellValue('E' . ($i + 2), $list[$i]['freeze_balance']);//防伪码
            $objPHPExcel->getActiveSheet()->setCellValue('F' . ($i + 2), $list[$i]['lixibao_balance']);//防伪码
            $objPHPExcel->getActiveSheet()->setCellValue('G' . ($i + 2), $list[$i]['parent_name']);//防伪码
            $objPHPExcel->getActiveSheet()->setCellValue('H' . ($i + 2), $list[$i]['invite_code']);//防伪码
            $objPHPExcel->getActiveSheet()->setCellValue('I' . ($i + 2), $list[$i]['addtime']);//防伪码
            $objPHPExcel->getActiveSheet()->setCellValue('J' . ($i + 2), $list[$i]['ip']);//防伪码
        }

        //7.设置保存的Excel表格名称
        $filename = 'user' . date('ymd', time()) . '.xls';
        //8.设置当前激活的sheet表格名称；

        $objPHPExcel->getActiveSheet()->setTitle('sheet'); // 设置工作表名

        //8.设置当前激活的sheet表格名称；
        $objPHPExcel->getActiveSheet()->setTitle('防伪码');
        //9.设置浏览器窗口下载表格
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition:inline;filename="' . $filename . '"');
        //生成excel文件
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        //下载文件在浏览器窗口
        sysoplog('导出会员', '');
        $objWriter->save('php://output');
        exit;
    }

    /**
     * 派单
     * @auth true
     * @return null
     */
    public function edit_paidan()
    {
        $id = input('get.id', 0);
        $info = Db::table($this->table)->find($id);
        $num = $info['deal_count'] + 1;
        $this->assign('num', $num);
        if (request()->isPost()) {
            $numb = input('post.num', 0);
            $row = Db::table('xy_send_order')->where('uid',$id)->where('num',$numb)->where('status',1)->find();
            if ($row){
                return $this->error('该任务已存在');
            }
            $uid = input('post.id/d', 0);
            if ($uid) {
                $data['num'] = input('post.num/d', 0);
                $data['multiple'] = input('post.multiple/d', 0);
                $data['min'] = input('post.min');
                $data['max'] = input('post.max');
                $data['addtime'] = time();
                $data['uid'] = $id;
                if($data['max']<=$data['min']){
                    return $this->error('最小值不得大于等于最大值');
                }

                $res = Db::table('xy_send_order')->insert($data);
                if ($res !== false) {
                    return $this->success('操作成功');
                } else {
                    return $this->error('操作失败');
                }
            } else {
                return $this->error('用户id参数错误');
            }
        }
        if (!$id) $this->error('参数错误');

        $this->assign('uid', $id);
        return $this->fetch();
    }



}