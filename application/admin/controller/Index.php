<?php
namespace app\admin\controller;

use app\admin\service\NodeService;
use library\Controller;
use library\tools\Data;
use think\Console;
use think\Db;
use think\facade\Cache;
use think\exception\HttpResponseException;

/**
 * 系统公共操作
 * Class Index
 * @package app\admin\controller
 */
class Index extends Base
{
    protected $timezone = [
        'Asia/Shanghai' => '中国上海',
        'Europe/London' => '英国',
        'America/Sao_Paulo' => '巴西',
        'America/Mexico_City' => '墨西哥',
        'Asia/Jakarta' => '印度尼西亚',
        'Asia/Ho_Chi_Minh' => '越南',
        'Europe/Istanbul' => '土耳其',
        'Australia/Sydney' => '澳大利亚',
        'Asia/Bangkok' => '泰国',
        'Europe/Moscow' => '俄罗斯',
        'Europe/Warsaw' => '波兰',
        'Asia/Tokyo' => '日本',
        'Europe/Madrid' => '西班牙',
        'America/Toronto'=>'加拿大'
        
    ];
    /**
     * 显示后台首页
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '系统管理后台';
        NodeService::applyUserAuth(true);
        $this->menus = NodeService::getMenuNodeTree();
        $this->current_timezone = $this->timezone[config('default_timezone')];
        // foreach ($this->menus as $v){
        //     foreach ($v as $vv){
        //         if($vv){
        //             print_r($vv);
        //         }
        //     }
        // }die;
        //$this->depositjia = Db::name('xy_deposit')->count();
        if (empty($this->menus) && !NodeService::islogin()) {
            $this->redirect('@admin/login');
        } else {
            $this->fetch();
        }
    }

    public function test()
    {
        NodeService::applyUserAuth(true);
        $this->menus = NodeService::getMenuNodeTree();
        echo json_encode($this->menus);
    }

    /**
     * 后台首页
     * @auth true
     * @menu true
     */
    public function main()
    {
        $type = input('type/s', '');
        if ($type == 'shop') {
            return $this->index_shop();
        } elseif ($type == 'agent') {
            return $this->index_agent();
        }
        $this->fetch();
    }

    private function getAgentWhere($pix = '')
    {
        $where = [];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = [$pix . 'addtime', 'between', [strtotime($arr[0]), strtotime($arr[1]) + 86400]];
        }
        return $where;
    }

    private function index_agent()
    {
        $agent_id = model('admin/Users')->get_admin_agent_id();
        if ($agent_id) {
            return $this->index_agent_service($agent_id);
        }
        $this->list = Db::name('system_user')->field('id,username')
            ->where('user_id', 0)
            ->where('authorize', 2)
            ->where('is_deleted', 0)
            ->select();
        $today = strtotime(date('Y-m-d'));
        foreach ($this->list as $k => $v) {
            $this->list[$k]['service_count'] = Db::name('system_user')->alias('su')
                ->join('xy_users u', 'su.user_id=u.id')
                ->where('u.agent_id', $v['id'])
                ->where('u.is_jia', '=', 0)
                ->where($this->getAgentWhere('u.'))
                ->count('u.id');
            $this->list[$k]['user_count'] = Db::name('xy_users')
                ->where('agent_id', $v['id'])
                ->where('is_jia', '=', 0)
                ->where($this->getAgentWhere())
                ->count('id');
            $this->list[$k]['user_balance'] = Db::name('xy_users')
                ->where('agent_id', $v['id'])
                ->where('is_jia', '=', 0)
                ->where('level', '>', 0)
                ->where($this->getAgentWhere())
                ->sum('balance');
            $this->list[$k]['recharge_count'] = Db::name('xy_recharge c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('u.agent_id', $v['id'])
                ->where('u.is_jia', '=', 0)
                ->where($this->getAgentWhere('c.'))
                ->where('c.status', 2)
                // ->where('c.type', 1)
                ->sum('c.num');
            $this->list[$k]['today_recharge_count'] = Db::name('xy_recharge c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('u.agent_id', $v['id'])
                ->where('u.is_jia', '=', 0)
                ->where('c.status', 2)
                // ->where('c.type', 1)
                ->where('c.addtime', '>', $today)
                ->sum('c.num');
            $this->list[$k]['deposit_count'] = Db::name('xy_deposit c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('u.agent_id', $v['id'])
                ->where('u.is_jia', '=', 0)
                ->where('c.status', 2)
                ->where($this->getAgentWhere('c.'))
                ->sum('c.num');
            $this->list[$k]['today_deposit_count'] = Db::name('xy_deposit c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('u.agent_id', $v['id'])
                ->where('u.is_jia', '=', 0)
                ->where('c.status', 2)
                ->where('c.addtime', '>', $today)
                ->sum('c.num');
        }
        return $this->fetch('index_agent');
    }

    private function index_agent_service($agent_id)
    {
        $agent_user_id = model('admin/Users')->get_admin_agent_uid();
        if ($agent_user_id) return '暂无数据';
        $this->list = Db::name('system_user')
            ->alias('su')
            ->join('xy_users u', 'su.user_id=u.id')
            ->where('u.agent_id', $agent_id)
            ->field('su.id,u.username')->select();
        $today = strtotime(date('Y-m-d'));
        foreach ($this->list as $k => $v) {
            $this->list[$k]['user_count'] = Db::name('xy_users')
                ->where('agent_service_id', $v['id'])
                ->where('is_jia', '=', 0)
                ->where($this->getAgentWhere())
                ->count('id');
            $this->list[$k]['user_balance'] = Db::name('xy_users')
                ->where('agent_service_id', $v['id'])
                ->where('is_jia', '=', 0)
                ->where('level', '>', 0)
                ->where($this->getAgentWhere())
                ->sum('balance');
            $this->list[$k]['recharge_count'] = Db::name('xy_recharge c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('u.agent_service_id', $v['id'])
                ->where('u.is_jia', '=', 0)
                ->where($this->getAgentWhere('c.'))
                ->where('c.status', 2)
                // ->where('c.type', 1)
                ->sum('c.num');
            $this->list[$k]['today_recharge_count'] = Db::name('xy_recharge c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('u.agent_service_id', $v['id'])
                ->where('u.is_jia', '=', 0)
                ->where('c.status', 2)
                // ->where('c.type', 1)
                ->where('c.addtime', '>', $today)
                ->sum('c.num');
            $this->list[$k]['deposit_count'] = Db::name('xy_deposit c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('u.agent_service_id', $v['id'])
                ->where('u.is_jia', '=', 0)
                ->where($this->getAgentWhere('c.'))
                ->where('c.status', 2)
                ->sum('c.num');
            $this->list[$k]['today_deposit_count'] = Db::name('xy_deposit c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('u.agent_service_id', $v['id'])
                ->where('c.status', 2)
                ->where('u.is_jia', '=', 0)
                ->where('c.addtime', '>', $today)
                ->sum('c.num');
        }
        return $this->fetch('index_agent_service');
    }

    private function index_shop()
    {
        $this->think_ver = \think\App::VERSION;
        $this->mysql_ver = Db::query('select version() as ver')[0]['ver'];
        //昨天
        $yes1 = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        $yes2 = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));

        //$this->goods_num = Db::name('xy_goods_list')->count('id');
        //$this->today_goods_num = Db::name('xy_goods_list')->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->count('id');
        //$this->yes_goods_num = Db::name('xy_goods_list')->where('addtime', 'between', [$yes1, $yes2])->count('id');
        //首冲人数
        $this->today_first_recharge_people = 0;
        $this->yes_first_recharge_people = 0;
        //用户
        $agent_id = model('admin/Users')->get_admin_agent_id();
        if ($agent_id) {
            $agent_user_id = model('admin/Users')->get_admin_agent_uid();
            if ($agent_user_id) {
                $this->today_first_recharge_people = Db::name('xy_users')
                    ->where('all_recharge_num', '>', 0)
                    ->where('is_jia', '=', 0)
                    ->where('agent_service_id', $agent_id)
                    ->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('id');
                $this->yes_first_recharge_people = Db::name('xy_users')
                    ->where('all_recharge_num', '>', 0)
                    ->where('is_jia', '=', 0)
                    ->where('agent_service_id', $agent_id)
                    ->where('addtime', 'between', [$yes1, $yes2])
                    ->count('id');

                $this->users_num = Db::name('xy_users')
                    ->where('is_jia', '=', 0)
                    ->where('agent_service_id', $agent_id)
                    ->where('is_jia', '=', 0)
                    ->count('id');
                $this->today_users_num = Db::name('xy_users')
                    ->where('is_jia', '=', 0)
                    ->where('agent_service_id', $agent_id)
                    ->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('id');
                $this->yes_users_num = Db::name('xy_users')
                    ->where('agent_service_id', $agent_id)
                    ->where('is_jia', '=', 0)
                    ->where('addtime', 'between', [$yes1, $yes2])
                    ->count('id');

                //订单数量
                $this->order_num = Db::name('xy_convey')->alias('c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->count('c.id');
                $this->today_order_num = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('c.id');
                $this->yes_order_num = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->count('c.id');

                //订单总额
                $this->order_sum = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->sum('c.num');
                $this->today_order_sum = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_order_sum = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                //充值
                $this->user_recharge = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    // ->where('c.type', 1)
                    ->where('c.status', 2)->sum('c.num');
                $this->today_user_recharge = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    // ->where('c.type', 1)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_user_recharge = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    // ->where('c.type', 1)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                $this->user_recharge_people = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    // ->where('c.type', 1)
                    ->where('u.is_jia', '=', 0)
                    ->count('distinct c.uid');
                $this->today_user_recharge_people = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    // ->where('c.type', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('distinct c.uid');
                $this->yes_user_recharge_people = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    // ->where('c.type', 1)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->count('distinct c.uid');
                $this->user_deposit_people = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->count('distinct c.uid');
                $this->today_user_deposit_people = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('distinct c.uid');
                $this->yes_user_deposit_people = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->count('distinct c.uid');

                //提现
                $this->user_deposit = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.status', 2)->sum('c.num');
                $this->today_user_deposit = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_user_deposit = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                //抢单佣金
                $this->user_yongjin = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->sum('c.commission');
                $this->today_user_yongjin = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.commission');
                $this->yes_user_yongjin = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.commission');

                //利息宝
                $this->user_lixibao = Db::name('xy_lixibao c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.type', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.is_sy', 0)->sum('c.num');
                $this->today_user_lixibao = Db::name('xy_lixibao c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.type', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.is_sy', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_user_lixibao = Db::name('xy_lixibao c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.type', 1)
                    ->where('c.is_sy', 0)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                //下级返佣
                $this->user_fanyong = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.type', 6)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.status', 1)
                    ->sum('c.num');
                $this->today_user_fanyong = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.type', 6)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.status', 1)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_user_fanyong = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.type', 6)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                //用户余额
                $this->user_yue = Db::name('xy_users')
                    ->where('level', '>', 0)
                    ->where('is_jia', '=', 0)
                    ->where('agent_service_id', $agent_id)
                    ->sum('balance');
                $this->user_djyue = Db::name('xy_users')
                    ->where('level', '>', 0)
                    ->where('is_jia', '=', 0)
                    ->where('agent_service_id', $agent_id)
                    ->sum('freeze_balance');
                $this->today_lxbsy = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.type', 23)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->today_lxbzc = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.type', 22)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
            } //
            else {
                $this->today_first_recharge_people = Db::name('xy_users')
                    ->where('all_recharge_num', '>', 0)
                    ->where('is_jia', '=', 0)
                    ->where('agent_id', $agent_id)
                    ->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('id');
                $this->yes_first_recharge_people = Db::name('xy_users')
                    ->where('all_recharge_num', '>', 0)
                    ->where('agent_id', $agent_id)
                    ->where('is_jia', '=', 0)
                    ->where('addtime', 'between', [$yes1, $yes2])
                    ->count('id');


                $this->users_num = Db::name('xy_users')
                    ->where('agent_id', $agent_id)
                    ->count('id');
                $this->today_users_num = Db::name('xy_users')
                    ->where('agent_id', $agent_id)
                    ->where('is_jia', '=', 0)
                    ->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('id');
                $this->yes_users_num = Db::name('xy_users')->where('agent_id', $agent_id)
                ->where('is_jia', '=', 0)
                    ->where('addtime', 'between', [$yes1, $yes2])->count('id');

                //订单数量
                $this->order_num = Db::name('xy_convey')->alias('c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->count('c.id');
                $this->today_order_num = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('c.id');
                $this->yes_order_num = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->count('c.id');

                //订单总额
                $this->order_sum = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->sum('c.num');
                $this->today_order_sum = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_order_sum = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                $this->order_sum_people = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_id', $agent_id)
                    ->count('distinct uid');
                $this->today_order_sum_people = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('distinct uid');
                $this->yes_order_sum_people = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->count('distinct uid');
                $this->user_recharge_people = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    // ->where('c.type', 1)
                    ->where('u.is_jia', '=', 0)
                    ->count('distinct uid');
                $this->today_user_recharge_people = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.status', 2)
                    // ->where('c.type', 1)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('distinct uid');
                $this->yes_user_recharge_people = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    // ->where('c.type', 1)
                    ->where('c.status', 2)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->count('distinct uid');

                //充值
                $this->user_recharge = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_id', $agent_id)
                    // ->where('c.type', 1)
                    ->where('c.status', 2)
                    ->sum('c.num');
                $this->today_user_recharge = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    // ->where('c.type', 1)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_user_recharge = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    // ->where('c.type', 1)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                $this->user_recharge_people = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    // ->where('c.type', 1)
                    ->count('distinct uid');
                $this->today_user_recharge_people = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.is_jia', '=', 0)
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    // ->where('c.type', 1)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('distinct c.uid');
                $this->yes_user_recharge_people = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.status', 2)
                    // ->where('c.type', 1)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->count('distinct c.uid');
                $this->user_deposit_people = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.status', 2)
                    ->count('distinct c.uid');
                $this->today_user_deposit_people = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->count('distinct c.uid');
                $this->yes_user_deposit_people = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->count('distinct c.uid');

                //提现
                $this->user_deposit = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->sum('c.num');
                $this->today_user_deposit = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_user_deposit = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 2)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                //抢单佣金
                $this->user_yongjin = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->sum('c.commission');
                $this->today_user_yongjin = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.commission');
                $this->yes_user_yongjin = Db::name('xy_convey c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.commission');

                //利息宝
                $this->user_lixibao = Db::name('xy_lixibao c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.type', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.is_sy', 0)->sum('c.num');
                $this->today_user_lixibao = Db::name('xy_lixibao c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.type', 1)
                    ->where('c.is_sy', 0)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_user_lixibao = Db::name('xy_lixibao c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.type', 1)
                    ->where('c.is_sy', 0)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                //下级返佣
                $this->user_fanyong = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.type', 6)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.status', 1)
                    ->sum('c.num');
                $this->today_user_fanyong = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.type', 6)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.status', 1)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->yes_user_fanyong = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.type', 6)
                    ->where('c.status', 1)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.addtime', 'between', [$yes1, $yes2])
                    ->sum('c.num');

                //用户余额
                $this->user_yue = Db::name('xy_users')
                    ->where('level', '>', 0)
                    ->where('is_jia', '=', 0)
                    ->where('agent_id', $agent_id)
                    ->sum('balance');
                $this->user_djyue = Db::name('xy_users')
                    ->where('level', '>', 0)
                    ->where('is_jia', '=', 0)
                    ->where('agent_id', $agent_id)
                    ->sum('freeze_balance');
                $this->today_lxbsy = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.type', 23)
                    ->where('c.status', 1)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
                $this->today_lxbzc = Db::name('xy_balance_log c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.type', 22)
                    ->where('u.is_jia', '=', 0)
                    ->where('c.status', 1)
                    ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
                    ->sum('c.num');
            }
        } //
        else {
              // $this->today_first_recharge_people = Db::name('xy_users')
            //     ->where('all_recharge_num', '>', 0)
            //     ->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])
            //     ->count('id');
            // $this->yes_first_recharge_people = Db::name('xy_users')
            //     ->where('all_recharge_num', '>', 0)
            //     ->where('addtime', 'between', [$yes1, $yes2])
            //     ->count('id');


            // $this->users_num = Db::name('xy_users')->count('id');
            // $this->today_users_num = Db::name('xy_users')
            //     ->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->count('id');
            // $this->yes_users_num = Db::name('xy_users')
            //     ->where('addtime', 'between', [$yes1, $yes2])->count('id');
            $sql = " select 
                    count(case when all_recharge_num>0 and addtime between ".strtotime(date('Y-m-d'))." and ".time()." then id end) as today_first_recharge_people,
                    count(case when all_recharge_num>0 and addtime between $yes1 and $yes2 then id end) as yes_first_recharge_people,
                    count(id) as users_num,
                    count(case when addtime between ".strtotime(date('Y-m-d'))." and ".time()." then id end) as today_users_num,
                    count(case when addtime between $yes1 and $yes2 then id end) as yes_users_num,
                    sum(case when level >0 then balance end) as user_yue
                from xy_users   where is_jia = 0";
            $user_numb = Cache::store('redis')->get('r_user_numb');
            if(!$user_numb){
                $user_numb = Db::query($sql);
                Cache::store('redis')->set('r_user_numb',$user_numb,300);
            }
            
            $this->today_first_recharge_people = $user_numb[0]['today_first_recharge_people'];
            $this->yes_first_recharge_people = $user_numb[0]['yes_first_recharge_people'];
            $this->users_num = $user_numb[0]['users_num'];
            $this->today_users_num = $user_numb[0]['today_users_num'];
            $this->yes_users_num = $user_numb[0]['yes_users_num'];
            

            //订单数量
            // $this->order_num = Db::name('xy_convey')->count('id');
            // $this->today_order_num = Db::name('xy_convey')->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->count('id');
            // $this->yes_order_num = Db::name('xy_convey')->where('addtime', 'between', [$yes1, $yes2])->count('id');

            // //订单总额
            // $this->order_sum = Db::name('xy_convey')->sum('num');
            // $this->today_order_sum = Db::name('xy_convey')->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->sum('num');
            // $this->yes_order_sum = Db::name('xy_convey')->where('addtime', 'between', [$yes1, $yes2])->sum('num');
            
            $sql1 = "select 
                count(c.id) as order_num,
                count(case when c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then c.id end) as today_order_num,
                count(case when c.addtime between $yes1 and $yes2 then c.id end) as yes_order_num,
                sum(c.num) as order_sum,
                sum(case when c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then c.num end) as today_order_sum,
                sum(case when c.addtime between $yes1 and $yes2 then c.num end) as yes_order_sum,
                sum(case when c.status =1 then c.commission end) as user_yongjin,
                sum(case when c.status =1 and c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then c.commission end) as today_user_yongjin,
                sum(case when c.status =1 and c.addtime between $yes1 and $yes2 then c.commission end) as yes_user_yongjin
            from xy_convey c left join xy_users u on u.id=c.uid where u.is_jia = 0";
            
             $user_order_num = Cache::store('redis')->get('r_user_order_num');
            if(!$user_order_num){
                $user_order_num = Db::query($sql1);
                Cache::store('redis')->set('r_user_order_num',$user_order_num,300);
            }
            $this->order_num = $user_order_num[0]['order_num'];
            $this->today_order_num = $user_order_num[0]['today_order_num'];
            $this->yes_order_num = $user_order_num[0]['yes_order_num'];
            $this->order_sum = $user_order_num[0]['order_sum']??0;
            $this->today_order_sum = $user_order_num[0]['today_order_sum'];
            $this->yes_order_sum = $user_order_num[0]['yes_order_sum'];

            //充值
            // $this->user_recharge = Db::name('xy_recharge')->where('status', 2)->sum('num');
            // $this->today_user_recharge = Db::name('xy_recharge')->where('status', 2)->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->sum('num');
            // $this->yes_user_recharge = Db::name('xy_recharge')->where('status', 2)->where('addtime', 'between', [$yes1, $yes2])->sum('num');

            // $this->user_recharge_people = Db::name('xy_recharge')
            //     ->where('status', 2)
            //     ->count('distinct uid');
            // $this->today_user_recharge_people = Db::name('xy_recharge')
            //     ->where('status', 2)
            //     ->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])
            //     ->count('distinct uid');
            // $this->yes_user_recharge_people = Db::name('xy_recharge')
            //     ->where('status', 2)
            //     ->where('addtime', 'between', [$yes1, $yes2])
            //     ->count('distinct uid');
                
            $sql2 = "select 
                sum(case when c.status=2 and c.type=1 then c.num end) as user_recharge,
                sum(case when c.status=2 and c.type=1 and  c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then num end) as today_user_recharge,
                sum(case when c.status=2 and c.type=1 and  c.addtime between $yes1 and $yes2 then num end) as yes_user_recharge,
                count(distinct case when c.status=2 and c.type=1 then c.uid end) as user_recharge_people,
                count(distinct case when c.status=2 and c.type=1 and c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then c.uid end) as today_user_recharge_people,
                count(distinct case when c.status=2 and c.type=1 and c.addtime between $yes1 and $yes2 then c.uid end) as yes_user_recharge_people
            from xy_recharge c left join xy_users u on u.id=c.uid where u.is_jia = 0";
             $user_recharge = Cache::store('redis')->get('r_user_recharge');
            if(!$user_recharge){
                $user_recharge = Db::query($sql1);
                Cache::store('redis')->set('r_user_recharge',$user_recharge,300);
            }
            
            $user_recharge = Db::query($sql2);
            $this->user_recharge = $user_recharge[0]['user_recharge']??0;
            $this->today_user_recharge = $user_recharge[0]['today_user_recharge']??0;
            $this->yes_user_recharge = $user_recharge[0]['yes_user_recharge']??0;
            $this->user_recharge_people = $user_recharge[0]['user_recharge_people']??0;
            $this->today_user_recharge_people = $user_recharge[0]['today_user_recharge_people']??0;
            $this->yes_user_recharge_people = $user_recharge[0]['yes_user_recharge_people']??0;
            
            // $this->user_deposit_people = Db::name('xy_deposit')
            //     ->where('status', 2)
            //     ->count('distinct uid');
            // $this->today_user_deposit_people = Db::name('xy_deposit')
            //     ->where('status', 2)
            //     ->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])
            //     ->count('distinct uid');
            // $this->yes_user_deposit_people = Db::name('xy_deposit')
            //     ->where('status', 2)
            //     ->where('addtime', 'between', [$yes1, $yes2])
            //     ->count('distinct uid');

            // //提现
            // $this->user_deposit = Db::name('xy_deposit')->where('status', 2)->sum('num');
            // $this->today_user_deposit = Db::name('xy_deposit')->where('status', 2)->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->sum('num');
            // $this->yes_user_deposit = Db::name('xy_deposit')->where('status', 2)->where('addtime', 'between', [$yes1, $yes2])->sum('num');
            $sql3 = "select 
                count(distinct case when c.status=2 then c.uid end) as user_deposit_people,
                count(distinct case when c.status=2 and  c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then c.uid end) as today_user_deposit_people,
                count(distinct case when c.status=2 and c.addtime between $yes1 and $yes2 then c.uid end) as yes_user_deposit_people,
                sum(case when c.status=2 then c.num end) as user_deposit,              
                sum(case when c.status=2 and  c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then c.num end) as today_user_deposit,
                sum(case when c.status=2 and  c.addtime between $yes1 and $yes2 then num end) as yes_user_deposit         
            from xy_deposit c left join xy_users u on u.id=c.uid where u.is_jia = 0";
             $user_deposit = Cache::store('redis')->get('r_user_deposit');
            if(!$user_deposit){
                 $user_deposit  = Db::query($sql3);
                Cache::store('redis')->set('r_user_deposit',$user_deposit,300);
            }
           
            $this->user_deposit_people = $user_deposit[0]['user_deposit_people']??0;
            $this->today_user_deposit_people = $user_deposit[0]['today_user_deposit_people']??0;
            $this->yes_user_deposit_people = $user_deposit[0]['yes_user_deposit_people']??0;
            $this->user_deposit = $user_deposit[0]['user_deposit']??0;
            $this->today_user_deposit = $user_deposit[0]['today_user_deposit']??0;
            $this->yes_user_deposit = $user_deposit[0]['yes_user_deposit']??0;
            

            //抢单佣金
            // $this->user_yongjin = Db::name('xy_convey')->where('status', 1)->sum('commission');
            // $this->today_user_yongjin = Db::name('xy_convey')->where('status', 1)->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->sum('commission');
            // $this->yes_user_yongjin = Db::name('xy_convey')->where('status', 1)->where('addtime', 'between', [$yes1, $yes2])->sum('commission');
            $this->user_yongjin = $user_order_num[0]['user_yongjin']??0;
            $this->today_user_yongjin = $user_order_num[0]['today_user_yongjin']??0;
            $this->yes_user_yongjin = $user_order_num[0]['yes_user_yongjin']??0;
            

            //利息宝
            // $this->user_lixibao = Db::name('xy_lixibao')->where('type', 1)->where('is_sy', 0)->sum('num');
            // $this->today_user_lixibao = Db::name('xy_lixibao')->where('type', 1)->where('is_sy', 0)->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->sum('num');
            // $this->yes_user_lixibao = Db::name('xy_lixibao')->where('type', 1)->where('is_sy', 0)->where('addtime', 'between', [$yes1, $yes2])->sum('num');
            $sql4 = "select 
                sum(case when type=1 and is_sy=0 then num end) as user_lixibao,              
                sum(case when type=1 and is_sy=0 and  addtime between ".strtotime(date('Y-m-d'))." and ".time()." then num end) as today_user_lixibao,
                sum(case when type=1 and is_sy=0 and  addtime between $yes1 and $yes2 then num end) as yes_user_lixibao         
            from xy_lixibao";
            
             $user_lixibao = Cache::store('redis')->get('r_user_lixibao');
            if(!$user_lixibao){
                $user_lixibao  = Db::query($sql4);
                Cache::store('redis')->set('r_user_lixibao',$user_lixibao,300);
            }
            
            $this->user_lixibao = $user_lixibao[0]['user_lixibao']??0;
            $this->today_user_lixibao = $user_lixibao[0]['today_user_lixibao']??0;
            $this->yes_user_lixibao = $user_lixibao[0]['yes_user_lixibao']??0;
            

            //下级返佣
            // $this->user_fanyong = Db::name('xy_balance_log')->where('type', 6)->where('status', 1)->sum('num');
            // $this->today_user_fanyong = Db::name('xy_balance_log')->where('type', 6)->where('status', 1)->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->sum('num');
            // $this->yes_user_fanyong = Db::name('xy_balance_log')->where('type', 6)->where('status', 1)->where('addtime', 'between', [$yes1, $yes2])->sum('num');
            // $this->today_lxbsy = Db::name('xy_balance_log')->where('type', 23)->where('status', 1)->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->sum('num');
            // $this->today_lxbzc = Db::name('xy_balance_log')->where('type', 22)->where('status', 1)->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->sum('num');
            $sql5 = "select 
                sum(case when c.type=6 and c.status=1 then c.num end) as user_fanyong,              
                sum(case when c.type=6 and c.status=1 and  c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then c.num end) as today_user_fanyong,
                sum(case when c.type=6 and c.status=1 and  c.addtime between $yes1 and $yes2 then c.num end) as yes_user_fanyong,
                sum(case when c.type=23 and c.status=1 and  c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then num end) as today_lxbsy,
                sum(case when type=22 and c.status=1 and  c.addtime between ".strtotime(date('Y-m-d'))." and ".time()." then c.num end) as today_lxbzc
            from xy_balance_log  c left join xy_users u on u.id=c.uid where u.is_jia = 0";
            
            
             $user_fanyong_arr = Cache::store('redis')->get('r_user_fanyong_arr');
            if(!$user_fanyong_arr){
                $user_fanyong_arr  = Db::query($sql5);
                Cache::store('redis')->set('r_user_fanyong_arr',$user_fanyong_arr,300);
            }
            
            $this->user_fanyong = $user_fanyong_arr[0]['user_fanyong']??0;
            $this->today_user_fanyong = $user_fanyong_arr[0]['today_user_fanyong']??0;
            $this->yes_user_fanyong = $user_fanyong_arr[0]['yes_user_fanyong'];
            $this->today_lxbsy = $user_fanyong_arr[0]['today_lxbsy']??0;
            $this->today_lxbzc = $user_fanyong_arr[0]['today_lxbzc']??0;
            
            //用户余额
            // $this->user_yue = Db::name('xy_users')
            //     ->where('level', '>', 0)
            //     ->sum('balance');
            // $this->user_djyue = Db::name('xy_users')
            //     ->where('level', '>', 0)
            //     ->sum('freeze_balance');
            //     echo $this->user_yue."/".$this->user_djyue;print_r($user_numb);die;
            $this->user_yue = $user_numb[0]['user_yue']??0;
            $this->user_djyue = $user_numb[0]['user_djyue']??0;
            
        }
        $isVersion = '';
        if (!session('check_update_version')) {
            $isVersion = $this->Update(1);
        }
        $this->assign('has_version', $isVersion);
        return $this->fetch('index_shop');
    }

    /**
     * 修改密码
     * @param integer $id
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function pass($id)
    {
        $this->applyCsrfToken();
        if (intval($id) !== intval(session('admin_user.id'))) {
            $this->error('只能修改当前用户的密码！');
        }
        if (!NodeService::islogin()) {
            $this->error('需要登录才能操作哦！');
        }
        if ($this->request->isGet()) {
            $this->verify = true;
            $this->_form('SystemUser', 'admin@user/pass', 'id', [], ['id' => $id]);
        } else {
            $data = $this->_input([
                'password' => $this->request->post('password'),
                'repassword' => $this->request->post('repassword'),
                'oldpassword' => $this->request->post('oldpassword'),
            ], [
                'oldpassword' => 'require',
                'password' => 'require|min:4',
                'repassword' => 'require|confirm:password',
            ], [
                'oldpassword.require' => '旧密码不能为空！',
                'password.require' => '登录密码不能为空！',
                'password.min' => '登录密码长度不能少于4位有效字符！',
                'repassword.require' => '重复密码不能为空！',
                'repassword.confirm' => '重复密码与登录密码不匹配，请重新输入！',
            ]);
            $user = Db::name('SystemUser')->where(['id' => $id])->find();
            if (md5($data['oldpassword']) !== $user['password']) {
                $this->error('旧密码验证失败，请重新输入！');
            }
            $result = NodeService::checkpwd($data['password']);
            if (empty($result['code'])) $this->error($result['msg']);
            if (Data::save('SystemUser', ['id' => $user['id'], 'password' => md5($data['password'])])) {
                $this->success('密码修改成功，下次请使用新密码登录！', '');
            } else {
                $this->error('密码修改失败，请稍候再试！');
            }
        }
    }

    /**
     * 修改用户资料
     * @param integer $id 会员ID
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function info($id = 0)
    {
        if (!NodeService::islogin()) {
            $this->error('需要登录才能操作哦！');
        }
        $this->applyCsrfToken();
        if (intval($id) === intval(session('admin_user.id'))) {
            $this->_form('SystemUser', 'admin@user/form', 'id', [], ['id' => $id]);
        } else {
            $this->error('只能修改登录用户的资料！');
        }
    }

    /**
     * 清理运行缓存
     * @auth true
     */
    public function clearRuntime()
    {
        if (!NodeService::islogin()) {
            $this->error('需要登录才能操作哦！');
        }
        try {
            Console::call('clear');
            Console::call('xclean:session');
            $this->success('清理运行缓存成功！');
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            $this->error("清理运行缓存失败，{$e->getMessage()}");
        }
    }

    /**
     * 压缩发布系统
     * @auth true
     */
    public function buildOptimize()
    {
        if (!NodeService::islogin()) {
            $this->error('需要登录才能操作哦！');
        }
        try {
            Console::call('optimize:route');
            Console::call('optimize:schema');
            Console::call('optimize:autoload');
            Console::call('optimize:config');
            $this->success('压缩发布成功！');
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            $this->error("压缩发布失败，{$e->getMessage()}");
        }
    }

    /**
     * 检查更新
     * @auth true
     */
    public function Update($isreturn)
    {
        $version = config("version");
        $isHtml = $isreturn ? 0 : 1;
        $con = '已经是最新版';
        session('check_update_version', 1);
        if ($isreturn) return $con;

        echo $con;
        die;
    }

    /**
     * 获取充值与提现数量
     * @auth true
     */
    public function order_info()
    {
        if (!NodeService::islogin()) {
            $this->error('需要登录才能操作哦！');
        }

        $agent_id = model('admin/Users')->get_admin_agent_id();
        if ($agent_id) {
            $agent_user_id = model('admin/Users')->get_admin_agent_uid();
            if ($agent_user_id) {
                $deposit = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia',0)
                    ->count('c.id');
                $deposit_jia = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia',1)
                    ->count('c.id');   
                $recharge = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia',0)
                    // ->where('c.type', 1)
                    ->count('c.id');
                $recharge_jia = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_service_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia',1)
                    // ->where('c.type', 1)
                    ->count('c.id');
            } else {
                $deposit = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia',0)
                    ->count('c.id');
                $deposit_jia = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia',1)
                    ->count('c.id');   
                $recharge = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia',0)
                    ->count('c.id');
                $recharge_jia = Db::name('xy_recharge c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('u.agent_id', $agent_id)
                    ->where('c.status', 1)
                    ->where('u.is_jia',1)
                    ->count('c.id');
            }
        } else {
            $deposit = Db::name('xy_deposit c')
                    ->leftJoin('xy_users u', 'u.id=c.uid')
                    ->where('c.status', 1)
                    ->where('u.is_jia',0)
                    ->count('c.id');
            $deposit_jia = Db::name('xy_deposit c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('c.status', 1)
                ->where('u.is_jia',1)
                ->count('c.id');   
            $recharge = Db::name('xy_recharge c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('c.status', 1)
                ->where('u.is_jia',0)
                ->count('c.id');
            $recharge_jia = Db::name('xy_recharge c')
                ->leftJoin('xy_users u', 'u.id=c.uid')
                ->where('c.status', 1)
                ->where('u.is_jia',1)
                ->count('c.id');
                    
                    
            //$deposit = Db::name('xy_deposit')->where('status', 1)->count('id');
            //$recharge = Db::name('xy_recharge')->where('status', 1)->count('id');
        }
        echo json_encode(['deposit' => $deposit, 'recharge' => $recharge, 'deposit_jia' =>$deposit_jia,'recharge_jia' => $recharge_jia, 'date' => date('Y-m-d H:i:s')]);

    }

    public function clear()
    {
        $isVersion = $this->Update(0);
    }


    /*public function piccc(){

        $url = 'http://ripead.jinbiao678.vip/api/order/product/page?pageNum=1&pageSize=2000&productId=&title=&minPrice=&maxPrice=&isAsc=&startTime=&endTime=';
        $file = file_get_contents($url);
        $domain = 'http://ripe.jinbiao678.vip';
        $datalist = json_decode($file,true)['data']['list'];
        foreach ($datalist as $k=>$val){
            $goods_pic = $val['imgUrl'];
            $goods_price = $val['price'];
            $shop_name = $val['title'];
            $goods_name = $val['title'];
            //插入数据库命令行
            Db::name('xy_goods_list')->insert([
                'shop_name' => $shop_name,
                'goods_name' => $shop_name,
                'goods_price' => $goods_price,
                'goods_pic' => $goods_pic,
                'addtime' => strtotime($val['createTime']),
                'status'=>1
            ]);
            $pic_file = $domain.$val['imgUrl'];
        }
    }*/


}
