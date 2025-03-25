<?php
namespace app\index\controller;

use app\admin\model\Convey;
use think\Controller;
use think\Request;
use think\Db;

class Start extends Base
{
    public function index()
    {
        $uid = intval(session('user_id'));
        $uinfo = Db::name('xy_users')->find($uid);
        $uinfo['level'] = $uinfo['level'] > 0 ? intval($uinfo['level']) : 0;

        if (empty($lang)||$lang=="es-mx"){
            $this->lang="es-mx";
        }else{
            $this->lang="zh-cn";
        }



        $this->accountNo="";
        if (empty($uinfo["tel"])){
            $this->accountNo=$uinfo["username"];
        }else{
            $this->accountNo=$uinfo["tel"];
        }




        $this->lock_deal = $uinfo['freeze_balance'];
        $this->level_info = Db::name('xy_level')->where('level', $uinfo['level'])->find();
        $this->level_name = $this->level_info['name']; //级别名称
        if ($uinfo['group_id'] > 0) {
            //必须做完了一轮 才能进入下一轮
            //信息
            $groupInfo = Db::name('xy_group')->where('id', $uinfo['group_id'])->find();
            if (empty($groupInfo)) exit();
            $day_deal = 0;
            list($day_d_count, $groupRule, $all_order_num) = Convey::instance()->get_user_group_rule($uinfo['id'], $uinfo['group_id']);
            $day_d_count = $day_d_count - 1;
            if ($day_d_count > 0) $day_deal = $day_d_count - 1;

            $this->day_deal = $day_deal; //已做单数量
            $this->day_d_count = $day_d_count; //已接单数量
            $this->order_num = $all_order_num; //级别 订单数量
            $this->level_nums = $groupInfo['money']; //级别 最低金额
            $this->level_bili = $groupInfo['bili'] / 100; //级别 佣金比例
        } else {
            //普通模式
            $where = [
                ['uid', '=', $uid],
                ['level_id', '=', $uinfo['level']],
                ['addtime', 'between', strtotime(date('Y-m-d')) . ',' . time()],
            ];
            //已做单数量
            $this->day_deal = Db::name('xy_convey')
                ->where($where)
                ->where('status', 'in', [1, 3, 5])
                ->sum('commission');
            //已接单数量
            // $this->day_d_count = Db::name('xy_convey')
            //     ->where($where)
            //     ->where('status', 'in', [0, 1, 3, 5])
            //     ->count('id');
            $this->day_d_count=$uinfo['deal_count'];
            $orderSetting = Convey::instance()->get_user_order_setting($uid, $uinfo['level']);
            $this->order_num = $orderSetting['order_num']; //级别 订单数量
            $this->level_nums = $orderSetting['min_money']; //级别 最低金额
            $this->level_bili = $orderSetting['bili']; //级别 佣金比例
        }
        
        $this->jinri=Db::name('xy_balance_log')->where('uid',$uid)->where('type','in',[3,6])->where('addtime','>=',strtotime(date('Y-m-d 00:00:00')))->sum('num');
        $this->order_incomplete_num = Db::name('xy_convey')
            ->where('uid', $uid)
            ->where('status', 'in', [0, 5])
            ->count('id');
        $this->uinfo = $uinfo;
        $this->price = $uinfo['balance']; //余额
        $this->level_list = Db::table('xy_level')->select(); //级别列表
        $this->assign('rule_msg', Db::name('xy_index_msg')->where('id', 9)->value('content'));
        $this->fetch();
    }

    /**
     *提交抢单
     */
    public function submit_order()
    {
        $tmp = $this->check_deal();
        if ($tmp) return json($tmp);
        //$res = check_time(9, 22);
        //if($res) return json(['code'=>1,'info'=>'禁止在9:00~22:00以外的时间段执行当前操作!']);
        $cid = input('get.cid/d', 1);

        $res = check_time(config('order_time_1'), config('order_time_2'));
        $str = config('order_time_1') . ":00  - " . config('order_time_2') . ":00";
        if ($res) return json(['code' => 1, 'info' => lang('task_worktime') . $str]);
        $uid = session('user_id');
        $user = Db::name('xy_users')->find($uid);
        if ($user['level'] == 0) {
            if ($user['addtime'] + 86400 < time()) {
                //return json(['code' => 1, 'info' => lang('free_end_time')]);
            }
        }
        //获取收款地址信息
        $add_id = Db::name('xy_member_address')->where('uid', $uid)->value('id');
        if (!$add_id) return json([
            'code' => 1,
            'info' => lang('not_address'),
            'url' => url('/index/my/edit_address')
        ]);
        //判断商品组
        $count = Db::name('xy_goods_list')->where('cid', '=', $cid)->count();
        if ($count < 1) return json(['code' => 1, 'info' => lang('qd_error_kucun')]);
        //检查交易状态
        // $sleep = mt_rand(config('min_time'),config('max_time'));
        $res = Db::name('xy_users')->where('id', $uid)
            ->update(['deal_status' => 2]);//将账户状态改为等待交易
        //if ($res === false) return json(['code' => 1, 'info' => lang('qd_error')]);
        // session_write_close();//解决sleep造成的进程阻塞问题
        // sleep($sleep);
        if ($user['group_id'] > 0) {
            //判断是否要出图片
            $real = input('get.real/d', 0);
            if (!$real) {
                list($orderNum, $groupRule) = model('admin/Convey')->get_user_group_rule($user['id'], $user['group_id']);
                if ($groupRule['image']) {
                    return json(['code' => 1, 'info' => '', 'image' => $groupRule['image'], 'real' => $real]);
                }
            }
            $res = model('admin/Convey')->create_order_group($uid, $cid);
        } else {
            $res = model('admin/Convey')->create_order($uid, $cid);
        }

        return json($res);
    }

    /**
     * 停止抢单
     */
    public function stop_submit_order()
    {
        $uid = session('user_id');
        $res = Db::name('xy_users')->where('id', $uid)->where('deal_status', 2)->update(['deal_status' => 1]);
        if ($res) {
            return json(['code' => 0, 'info' => lang('czcg')]);
        } else {
            return json(['code' => 1, 'info' => lang('czsb')]);
        }
    }


}