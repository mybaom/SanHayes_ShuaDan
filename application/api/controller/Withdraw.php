<?php

namespace app\api\controller;

use app\admin\model\Convey;
use think\Db;

class Withdraw extends Base
{
    // 提现接口
    public function withdraw()
    {
        $uid = $this->_uid;

        $res = check_time(config('tixian_time_1'), config('tixian_time_2'));
        $str = config('tixian_time_1') . ":00  - " . config('tixian_time_2') . ":00";


        if ($res) return json(['code' => 1, 'info' => lang('task_worktime') . $str]);

        //交易密码
        $pwd2 = input('post.paypassword/s', '');
        $type = input('post.type/s', 'bank');
        $type = strtolower($type);


        $info = Db::name('xy_users')->field('pwd,salt,address')->find($uid);
        if ($info['pwd'] == '') {
            // return json(['code' => 1, 'info' => 'Incorrect withdrawal password']);
        }

        $bankinfo = [];

        if (request()->isPost()) {

            if ($info['pwd'] != sha1($pwd2 . $info['salt'] . config('pwd_str'))) {
//                return json(['code' => 1, 'info' => lang('Password error')]);
                // return json(['code' => 1, 'info' => 'Incorrect withdrawal password']);
            }

            $num = input('post.num', 0);
            $bkid = input('post.bk_id/d', 0);
            $USDT_code = input('post.address/s', '');

            // if (!$USDT_code && $type == 'USDT') {
            //     return json(['code' => 1, 'info' => lang('with_q_usdt')]);
            // }

            if ($num <= 0) return json(['code' => 1, 'info' => lang('cscw')]);

            $uinfo = Db::name('xy_users')->find($uid);
            $level = !empty($uinfo['level']) ? intval($uinfo['level']) : 0;
            $ulevel = Db::name('xy_level')->where('level', $level)->find();

            //叠加组必须做完最后一单才行
            if ($uinfo['group_id'] > 0) {
                $max_order_num = Db::name('xy_group_rule')
                    ->where('group_id', $uinfo['group_id'])
                    ->order('order_num desc')
                    ->value('order_num');
                //如果规则组没有规则
                if (empty($max_order_num)) {
                    return ['code' => 1, 'info' => lang('hyddjycsbz')];
                }
                $u_order_num = Db::name('xy_convey')
                    ->where('group_id', $uinfo['group_id'])
                    ->where('uid', $uinfo['id'])
                    ->order('addtime desc')
                    ->limit(1)
                    ->value('group_rule_num');
                //如果是最后一单
                if ($u_order_num < $max_order_num) {
                    return [
                        'code' => 1,
                        'info' => sprintf(lang('selfLevel_err'), $max_order_num),
                        'url' => url('index/start/index')
                    ];
                }
            } else {


                //获取用户信息
                $onum = $uinfo['deal_count'];
                if ($onum < $ulevel['order_num']) {
                    return json([
                        'code' => 1,
                        'info' => sprintf(lang('selfLevel_err'), $ulevel['tixian_nim_order']),
                        'url' => url('index/start/index'),
                        'min' => 60
                    ]);
                }
            }

            //提现金额限制
            //查询最小提现金额
            $depoist_min_num = config('deposit_num');
            if($depoist_min_num>0){
                if($depoist_min_num>$num) return json(['code' => 1, 'info' => lang('with_q_minmoney').$depoist_min_num]);
            }else{//等级提现限制
            }
            if ($num > $uinfo['balance']) return json(['code' => 1, 'info' => lang('money_not')]);

//            if ($uinfo['deal_time'] == strtotime(date('Y-m-d'))) {
//                //提现次数限制
//                $tixianCi = Db::name('xy_deposit')
//                    ->where('uid', $uid)
//                    ->where('addtime', 'between', [strtotime(date('Y-m-d 00:00:00')), time()])->count();
//                if ($tixianCi + 1 > $ulevel['tixian_ci']) {
//                    return json(['code' => 1, 'info' => lang('selfLevel_today_error')]);
//                }
//
//            } else {
//
//                //重置最后交易时间
//                Db::name('xy_users')->where('id', $uid)->update([
//                    'deal_time' => strtotime(date('Y-m-d')),
//                    'deal_count' => 0,
//                    'recharge_num' => 0,
//                    'deposit_num' => 0
//                ]);
//            }
               // 获取银行卡id
                $bankinfo = Db::name('xy_bankinfo')->where('id', $bkid)->find();
                if (!$bankinfo) {
                    return json(['code' => 1, 'info' => lang('not_put_bank')]);
                }
            //提现类型
            if ($type == 'bank') {
                $pric = $num;

            } else {
                //提现USDT 需要转换
                
                $pric = $num;
                $bankinfo['id'] = '';
                //获取信息 user_wallet
                $ka = Db::name('user_wallet')->where('uid', $uid)->find();
                if($ka['address'] != $USDT_code){
                    return json(['code' => 1, 'info' => lang('czsb')]);
                }
            }
            $usdt_pay_info = Db::name('xy_pay')->where('name2', 'bit')->find();
            
            $id = getSn('CO');
            //手续费
            $fees = sysconf('fees')/100;
            try {
                Db::startTrans();
                $ddd = [
                    'id' => $id,
                    'uid' => $uid,
                    'bk_id' => $bkid,
                    'num' => $num,//真实提现金额 没有转USDT的数额
                    'addtime' => time(),
                    'usdt' => '',
                    'type' => $type,
                    'shouxu' => $pric * $fees,
                    'real_num' => $pric - ($pric * $fees),//转完的账户
                    'bankname' => $bankinfo['bankname'],
                    'cardnum' => $bankinfo['cardnum'],
                    'username' => $bankinfo['username'],
                ];
                if (!empty($usdt_pay_info) && $type == 'usdt') {
                    $ddd['num2'] = $ddd['real_num'];
                }
                $res = Db::name('xy_deposit')->insert($ddd);
                //提现日志
                $res2 = Db::name('xy_balance_log')
                    ->insert([
                        'uid' => $uid,
                        'oid' => $id,
                        'num' => $num,
                        'type' => 7, //TODO 7提现
                        'status' => 2,
                        'addtime' => time(),
                    ]);
                $res1 = Db::name('xy_users')->where('id', $uid)->setDec('balance', $num);
                if ($res && $res1) {
                    Db::commit();
                    return json(['code' => 0, 'info' => 'Withdrawal success']);
                } else {
                    Db::rollback();
                    return json(['code' => 1, 'info' => lang('czsb')]);
                }
            } catch (\Exception $e) {
                Db::rollback();
                // dump($e->getMessage());
                return json(['code' => 1, 'info' => lang('czsb_jczhye'), 'msg' => $e->getMessage()]);
            }
        }
        return json(['code' => 0, 'info' => lang('czcg'), 'data' => $bankinfo]);
    }

    // 提现记录
    public function withdrawLog()
    {
        $uid = $this->_uid;
        $page = input('post.page/d', 1);
        $num = input('post.num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $where[] = ['uid', '=', $uid];
        /*if (input('post.status')) {
            $where[] = ['status', '=', input('post.status')];
        }*/
//        $where[] = ['status', '=', 2];
        $list = Db::table("xy_deposit")
            ->where($where)
            ->order('id desc')
            ->paginate($num)
            ->each(function($item, $key){
                $item['addtime'] = date('Y/m/d H:i:s', $item['addtime']);
                return $item;
            });
        if($list){
            return json(['code' => 0, 'data' => $list]);
        }
        return $this->error(lang('zwsj'));
    }
}