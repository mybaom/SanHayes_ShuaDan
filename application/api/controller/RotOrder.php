<?php

namespace app\api\controller;

use app\admin\model\Convey;
use think\Db;

/**
 * 下单控制器
 */
class RotOrder extends Base
{
    /**
     * 首页
     */
    public function index()
    {
        $info = Db::name('xy_users')
            ->field('deal_count,gender')
            ->find($this->_uid);
        $addtime = strtotime(date('Y-m-d 00:00:00'));
        if($info['deal_count']<=60&& $info['deal_count']>0){
            $conveyInfo = Db::name('xy_convey')->field('addtime')
                ->where('uid', $this->_uid)
                ->where('deal_count', 1)
                ->where('status', 'in', [1, 3, 5])
                ->order('oid desc')
                ->find();
            if($conveyInfo) {
                $addtime = $conveyInfo['addtime'];
            }
        }
        //今日佣金
        $data['today_comiss'] = Db::name('xy_balance_log')->where('uid', $this->_uid)->where('type', 'in', [3, 6])->where('addtime', '>=', $addtime)->sum('num');
        $uinfo = Db::name('xy_users')->find($this->_uid);
        
        //账户金额
        $data['balance'] = $uinfo['balance'];
        //等级订单数
        if ($uinfo['group_id'] > 0) {
            $group = Db::name('xy_group')->find($uinfo['group_id']);
            list($day_d_count, $groupRule, $all_order_num) = Convey::instance()->get_user_group_rule($uinfo['id'], $uinfo['group_id']);
            if($day_d_count == 1){
                $lastOrder = Db::name('xy_convey')
                ->where('uid', $this->_uid)
                ->where('group_id', $uinfo['group_id'])
                ->order('oid desc')
                ->find();
                if($lastOrder['group_rule_num'] == $group['order_num']){
                    $day_d_count = $lastOrder['group_rule_num'] + 1;
                }
            }
            $data['allOrderNum'] = $all_order_num;
            $data['completeOrder'] = $day_d_count - 1;
            $data['orderbili'] = $group['bili']; //百分比
        } else {
            $orderSetting = Convey::instance()->get_user_order_setting($this->_uid, $uinfo['level']);
            //普通模式
            $where = [
                ['uid', '=', $this->_uid],
                ['level_id', '=', $uinfo['level']],
                ['addtime', 'between', strtotime(date('Y-m-d')) . ',' . time()],
            ];
            $day_deal = Db::name('xy_convey')
                ->where($where)
                ->where('status', 'in', [1, 3, 5])
                ->count('id');
            $data['allOrderNum'] = $orderSetting['order_num'];
            $data['completeOrder'] = $uinfo['deal_count'];
            $data['orderbili'] = $orderSetting['bili'] * 100; //百分比
        }
        
        return $this->success('', $data);
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
        sleep(2);
        $cid = input('get.cid/d', 1);

        $res = check_time(config('order_time_1'), config('order_time_2'));
        $str = config('order_time_1') . ":00  - " . config('order_time_2') . ":00";

        if ($res) return json(['code' => 1, 'info' => lang('task_worktime') . $str]);

        $uid = $this->_uid;
        $user = Db::name('xy_users')->find($uid);
        $orderSetting = model('admin/Convey')->get_user_order_setting($uid, $user['level']);
        
        //获取收款地址信息
        // $add_id = Db::name('xy_member_address')->where('uid', $uid)->value('id');
        // if (!$add_id) return json([
        //     'code' => 1,
        //     'info' => lang('not_address'),
        //     'url' => url('/index/my/edit_address')
        // ]);
        //判断商品组
        $count = Db::name('xy_goods_list')->where('cid', '=', $cid)->count();
        if ($count < 1) return json(['code' => 1, 'info' => lang('qd_error_kucun')]);
        //检查交易状态
        // $sleep = mt_rand(config('min_time'),config('max_time'));
        $res = Db::name('xy_users')->where('id', $uid)
            ->update(['deal_status' => 2]);//将账户状态改为等待交易

        // $res = model('admin/Convey')->create_pt_order($uid, $cid);
        // return json($res);

        if ($user['group_id'] > 0) {
            $groupInfo = Db::name('xy_group')->where('id', $user['group_id'])->find();
            if ($user['deal_count'] >= $groupInfo['order_num']) {
                return json(['code' => 1, 'info' => lang('order_error_level_num')]);
            }
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
            if ($user['deal_count'] >= $orderSetting['order_num']) {
                return json(['code' => 1, 'info' => lang('order_error_level_num')]);
            }
            // $res = model('admin/Convey')->create_order($uid, $cid);
            $res = model('admin/Convey')->create_pt_order($uid, $cid);
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