<?php

namespace app\admin\controller;

use app\admin\service\NodeService;
use library\Controller;
use library\tools\Data;
use think\Db;

/**
 * 定时任务
 * Class Users
 * @package app\admin\controller
 */
class Crontab extends Controller
{
    /**
     * 测试
     */
    public function index()
    {
        //
        exit;
    }

    private $_is_sync = false;

    public function limit20()
    {
        $this->_is_sync = true;
        $this->clean_recharge();
        $this->sync_recharge();
        $this->sync_deposit();
        $this->success('suc');
    }

    //======自动清理 一个小时内没支付对充值
    public function clean_recharge()
    {
        $t = 3600 * config('clean_recharge_hour');
        $result = Db::name('xy_recharge')
            ->where('status', 1)
            ->where('addtime', '<', time() - $t)
            ->update([
                'status' => 3,
                'endtime' => time()
            ]);
        if (!$this->_is_sync) $this->success('suc', ['res' => $result]);
    }


    //======同步 充值数据
    public function sync_recharge()
    {
        $list = Db::query('select uid,count(id) as c,sum(num) as `num` from xy_recharge where `status`=2 group by uid');
        foreach ($list as $val) {
            Db::name('xy_users')
                ->where('id', $val['uid'])
                ->update([
                    'all_recharge_num' => $val['num'],
                    'all_recharge_count' => $val['c'],
                ]);
        }
        if (!$this->_is_sync) $this->success('suc', ['res' => count($list)]);
    }

    //======同步 提现数据
    public function sync_deposit()
    {
        $list = Db::query('select uid,count(id) as c,sum(num) as `num` from xy_deposit where `status`=2 group by uid');
        foreach ($list as $val) {
            Db::name('xy_users')
                ->where('id', $val['uid'])
                ->update([
                    'all_deposit_num' => $val['num'],
                    'all_deposit_count' => $val['c'],
                ]);
        }
        if (!$this->_is_sync) $this->success('suc', ['res' => count($list)]);
    }

    //=======同步 用户 service_id
    public function sync_service_id()
    {
        $list = Db::name('xy_users')->column('id');
        $n = 0;
        foreach ($list as $v) {
            //更新用户service_id
            $s = model('Users')->get_user_service_id($v);
            if (!empty($s['id'])) {
                Db::table('xy_users')
                    ->where('id', $v)
                    ->update(['agent_service_id' => $s['id']]);
                $n++;
            }
        }
        $this->success('suc', ['res' => $n]);
    }

    //======利息宝结算  结算到期对利息宝======
    public function lxb_jiesuan()
    {
        $now = time();
        $now = strtotime(date('Y-m-d 00:00:00', time()));; //小于今天的 12点
        $lxb = Db::name('xy_lixibao')->where('endtime', '<', $now)
            ->where('is_qu', 0)
            ->where('is_sy', 0)
            ->where('type', 1)->select();  //利息宝月

        if ($lxb) {
            foreach ($lxb as $item) {
                //----------------------------------
                $lixibao = Db::name('xy_lixibao_list')->find($item['sid']);
                $price = $item['num'];
                $uid = $item['uid'];
                $id = $item['id'];
                $sy = $price * $lixibao['bili'] * $lixibao['day'];

                Db::name('xy_users')->where('id', $uid)->setDec('lixibao_balance', $price);  //利息宝余额 -
                Db::name('xy_users')->where('id', $uid)->setInc('balance', $price + $sy);  //余额 +  没有手续费

                $res = Db::name('xy_lixibao')->where('id', $id)->update([
                    'is_qu' => 1,
                    'is_sy' => 1,
                    'real_num' => $sy
                ]);
                $res1 = Db::name('xy_balance_log')->insert([
                    //记录返佣信息
                    'uid' => $uid,
                    'oid' => $id,
                    'num' => $sy,
                    'type' => 23,
                    'addtime' => time()
                ]);
                $res2 = Db::name('xy_balance_log')->insert([
                    //记录返佣信息
                    'uid' => $uid,
                    'oid' => $id,
                    'num' => $price,
                    'type' => 22,
                    'addtime' => time()
                ]);

                //自动结算 并自取出利息宝的 到用户余额

                //----------------------------------
            }
        }
        $this->success('suc', ['res' => count($lxb)]);
    }
}