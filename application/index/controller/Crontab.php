<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 

// +----------------------------------------------------------------------

namespace app\index\controller;

use library\Controller;
use think\Db;

/**
 * 定时器
 */
class Crontab extends Controller
{
    //冻结订单
    public function freeze_order()
    {
        $timeout = time() - config('deal_timeout');//超时订单
        $oinfo = Db::name('xy_convey')
            ->where('status', 0)
            ->where('addtime', '<=', $timeout)
            ->field('id')
            ->select();
        if ($oinfo) {
            foreach ($oinfo as $v) {
                Db::name('xy_convey')
                    ->where('id', $v['id'])
                    ->update(['status' => 5]);
            }
        }
        //$this->cancel_order();
        //$this->reset_deal();
    }

    //强制取消订单并冻结账户 
    public function cancel_order()
    {
        exit();
        $timeout = time() - config('deal_timeout');//超时订单
        //$oinfo = Db::name('xy_convey')->field('id oid,uid')->where('status',5)->where('endtime','<=',$timeout)->select();
        $oinfo = Db::name('xy_convey')->field('id oid,uid')->where('status', 0)->where('endtime', '<=', $timeout)->select();
        if ($oinfo) {
            foreach ($oinfo as $v) {
                Db::name('xy_convey')->where('id', $v['oid'])->update(['status' => 4, 'endtime' => time()]);
                $tmp = Db::name('xy_users')->field('deal_error,deal_status')->find($v['uid']);
                //记录违规信息
                if ($tmp['deal_status'] != 0) {
                    if ($tmp['deal_error'] < (int)config('deal_error')) {
                        Db::name('xy_users')->where('id', $v['uid'])->update(['deal_status' => 1, 'deal_error' => Db::raw('deal_error+1')]);
                        Db::name('xy_user_error')->insert(['uid' => $v['uid'], 'oid' => $v['oid'], 'addtime' => time(), 'type' => 2]);
                    } elseif ($tmp['deal_error'] >= (int)config('deal_error')) {
                        Db::name('xy_users')->where('id', $v['uid'])->update(['deal_status' => 1, 'deal_error' => 0]);
                        Db::name('xy_user_error')->insert(['uid' => $v['uid'], 'oid' => $v['oid'], 'addtime' => time(), 'type' => 3]);
                        //记录交易冻结信息
                    }
                }
            }
        }
    }

    //解冻账号
    public function reset_deal()
    {
        exit();
        $uinfo = Db::name('xy_users')->where('deal_status', 0)->field('id')->select();
        if ($uinfo) {
            foreach ($uinfo as $v) {
                $time = Db::name('xy_user_error')->where('uid', $v['id'])->where('type', 3)->order('addtime desc')->limit(1)->value('addtime');
                if ($time || $time <= time() - config('deal_feedze')) {
                    //解封账号
                    Db::name('xy_users')->where('id', $v['id'])->update(['deal_status' => 1]);
                    Db::name('xy_user_error')->insert(['uid' => $v['id'], 'oid' => '-', 'addtime' => time(), 'type' => 1]);
                }
            }
        }
    }

    //发放佣金
    public function do_reward()
    {
        try {
            $time = strtotime(date('Y-m-d', time()));//获取当天凌晨0点的时间戳
            $data = Db::name('xy_reward_log')->where('addtime', 'between', time() - 3600 * 24 . ',' . time())->where('status', 1)->select();//获取当天佣金
            if ($data) {
                foreach ($data as $k => $v) {
                    Db::name('xy_users')->where('id', $v['uid'])->setInc('balance', $v['num']);
                    Db::name('xy_reward_log')->where('id', $v['id'])->update(['status' => 2, 'endtime' => time()]);
                }
            }
            echo 1;
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    //定时器 解除冻结 反还佣金和本金
    public function start333()
    {
        $oinfo = Db::name('xy_convey')->where('status', 5)->where('endtime', '<=', time())->select();
        if ($oinfo) {
            //
            foreach ($oinfo as $v) {
                //
                Db::name('xy_convey')->where('id', $v['id'])->update(['status' => 1]);

                //
                $res1 = Db::name('xy_users')
                    ->where('id', $v['uid'])
                    //->dec('balance',$info['num'])//
                    ->inc('balance', $v['num'] + $v['commission'])
                    //->inc('freeze_balance',$info['num']+$info['commission']) //冻结商品金额 + 佣金//
                    ->dec('freeze_balance', $v['num'] + $v['commission']) //冻结商品金额 + 佣金
                    ->update(['deal_status' => 1]);
                model('admin/Convey')->deal_reward($v['uid'], $v['id'], $v['num'], $v['commission']);

                //
            }
        }
        $this->cancel_order();
        $this->reset_deal();
        //$this->lixibao_chu();
        //var_dump($oinfo,time(),date('Y-m-d H:i:s', 1577812622));die;
        return json(['code' => 1, 'info' => '执行成功！']);
    }

    //强制取消订单并冻结账户
    public function start()
    {
        $timeout = time() - config('deal_timeout');//超时订单
        $timeout = time();//超时订单
        //$oinfo = Db::name('xy_convey')->field('id oid,uid')->where('status',5)->where('endtime','<=',$timeout)->select();
        $oinfo = Db::name('xy_convey')->where('status', 0)->where('endtime', '<=', $timeout)->select();
        if ($oinfo) {
            $djsc = config('deal_feedze'); //冻结时长 单位小时
            foreach ($oinfo as $v) {
                Db::name('xy_convey')->where('id', $v['id'])->update(['status' => 5, 'endtime' => time() + $djsc * 60 * 60]);
                //$res = Db::name('xy_convey')->where('id',$oid)->update($tmp);
                $res1 = Db::name('xy_users')
                    ->where('id', $v['uid'])
                    ->dec('balance', $v['num'])
                    ->inc('freeze_balance', $v['num'] + $v['commission']) //冻结商品金额 + 佣金
                    ->update(['deal_status' => 1, 'status' => 1]);

                $res2 = Db::name('xy_balance_log')->insert([
                    'uid' => $v['uid'],
                    'oid' => $v['id'],
                    'num' => $v['num'],
                    'type' => 2,
                    'status' => 2,
                    'addtime' => time()
                ]);
            }
        }

        //解冻
        $this->jiedong();
    }

    //----------------------------利息宝---------------------------------
    //1 转入 2转出  3每日收益
    public function lixibao_chu()
    {
        //处理从余额里转出的到账时间
        $addMax = time() - ((config('lxb_time')) * 60 * 60); //向前退一个小时
        $res = Db::name('xy_lixibao')->where('status', 0)->where('addtime', '<=', $addMax)->where('type', 2)->select();
        if ($res) {
            foreach ($res as $re) {
                $uid = $re['uid'];
                $num = $re['num'];

                Db::name('xy_users')->where('id', $re['id'])->setDec('lixibao_dj_balance', $num);  //利息宝月 -
                Db::name('xy_users')->where('id', $uid)->setInc('balance', $num);  //余额 +
                Db::name('xy_lixibao')->where('id', $re['id'])->update(['status' => 1]);  //利息宝月 -
            }
        }
    }

    //自动过期 资金
    public function dec_free_balance()
    {
        //  ALTER TABLE `xy_users` ADD `is_clean_free` int  DEFAULT '0'  COMMENT '是否取消冻结金额'  AFTER `agent_service_id`;
        $time = config('free_balance_time');
        if ($time == 0) return;
        //->setDec('balance', config('free_balance'));
        $ba = config('free_balance');
        $ba = intval($ba);
        $list = Db::name('xy_users')
            ->field('id,is_clean_free')
            ->where('balance', '>', $ba)
            ->where('is_clean_free', '<', time())
            ->where('is_clean_free', '<>', 0)
            ->select();
        foreach ($list as $v) {
            Db::name('xy_users')
                ->where('id', $v['id'])
                ->update([
                    'is_clean_free' => 0,
                    'balance' => Db::raw('balance-' . $ba)
                ]);
        }
        exit('success');
    }
}