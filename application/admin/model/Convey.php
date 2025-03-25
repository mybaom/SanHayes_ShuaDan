<?php

namespace app\admin\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\ThrowableError;
use think\Model;
use think\Db;

class Convey extends Model
{

    protected $table = 'xy_convey';

    public static function instance(): Convey
    {
        return new self();
    }
    /**
     * 创建派单
     */

    public function create_pt_order($uid,$cid){
        $add_id = 0;
        $uinfo = Db::name('xy_users')->field('deal_status,balance,level,deal_min_num,deal_max_num,pipei_type,pipei_dan,pipei_grouping,freeze_balance,parent_id,deal_count,relation_id')->find($uid);
        //调用系统总区间信息
        // $min = round($uinfo['balance'] * config('deal_min_num') / 100, 2);
        // $max = round($uinfo['balance'] * config('deal_max_num') / 100, 2);
        // $goods = $this->rand_order($min, $max, $cid);

        // //获取是否设置派单(新加的)
        // $send_order = Db::name('xy_send_order')->where('num',$uinfo['deal_count']+1)->where('uid','=',$uid)->where('status','=',1)->order('id','desc')->find();
        // $need_money = 0;
        // $multiple = 1;//需要充值的金额和佣金倍数
        // if ($send_order && $send_order['num'] - $uinfo['deal_count'] ==1){
        //     $need_money = round(mt_rand($send_order['min'] * 100, $send_order['max'] * 100) / 100, 2);//随机需要充值交易额
        //     $multiple = $send_order['multiple']>0?$send_order['multiple']:1;
        // }
        //获取是否设置派单(新加的)
        $send_order = $this->get_inyectar($uid,$uinfo['deal_count']);

        $need_money = 0;//需要充值的金额和佣金倍数
        $orderSetting = Convey::instance()->get_user_order_setting($uid, $uinfo['level']);
        $multiple = $orderSetting['bili'];
        // if ($send_order && $send_order['order_num'] - $uinfo['deal_count'] == 1){
            //$need_money = round(mt_rand($send_order['min'] * 100, $send_order['max'] * 100) / 100, 2);//随机需要充值交易额
            // $min = $uinfo['balance'] + $send_order['min'];
            // $max = $uinfo['balance'] + $send_order['max'];
        //     $min = $uinfo['balance'];
        //     $max = $uinfo['balance'] + $send_order['scale'];
            
        // }else{
            //调用系统总区间信息
            $min = round($uinfo['balance'] * config('deal_min_num') / 100, 2);
            $max = round($uinfo['balance'] * config('deal_max_num') / 100, 2);
        // }
        
        $goods = $this->rand_order($min, $max, $cid);
        if ($send_order){
            $multiple = $send_order['multiple'];
            $goods['num'] = $uinfo['balance'] + $send_order['scale'];
            $goods['numb'] = 1;
        }
        
        $need_money = $goods['num'] * $goods['numb'] - $uinfo['balance']>0?round($goods['num'] * $goods['numb'] - $uinfo['balance'],2):0;

        $id = getSn('UB');
        Db::startTrans();
        $res = Db::name('xy_users')->where('id', $uid)->update(['deal_status' => 3, 'deal_time' => strtotime(date('Y-m-d'))]);//将账户状态改为交易中
        
        //插入佣金记录
        $c_data = [
            'id' => $id,
            'uid' => $uid,
            'level_id' => $uinfo['level'],
            'num' => $goods['num'],
            'addtime' => time(),
            'endtime' => time() + config('deal_timeout'),
            'add_id' => $add_id,
            'goods_id' => $goods['id'],
            'goods_count' => $goods['count'],
            'commission' => round($goods['num'] * $goods['numb'] * $multiple, 2),  //交易佣金按照会员等级
            'user_balance' => $uinfo['balance'],
            'user_freeze_balance' => $uinfo['freeze_balance'],
            'lian' => 0,
            'numb' =>$goods['numb'],
            'need_money' => $need_money,
            'soid' => $send_order?$send_order['id']:0,
            'deal_count' => $uinfo['deal_count'] + 1
        ];
        //查出用户推荐人 发放推荐人佣金
        if ($uinfo['relation_id'] > 0) {
            $c_data['parent_commission'] = $c_data['commission'] ;
            $c_data['parent_uid'] = $uinfo['relation_id'];
        }
        $res1 = Db::name($this->table)->insert($c_data);

        if ($res && $res1) {
            Db::commit();
            return ['code' => 0, 'info' => lang('qd_ok'), 'oid' => $id, 'orderNum' => 0, 'lian' => 0];
        } else {
            Db::rollback();
            return ['code' => 1, 'info' => lang('qd_sb')];
        }
    }

    /**
     * 创建订单
     * @param int $uid 用户编号
     * @param int $cid 商品组
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @throws \think\exception\PDOException
     */
    public function create_order($uid, $cid = 1)
    {
        $add_id = 0;
        $uinfo = Db::name('xy_users')->field('deal_status,balance,level,deal_min_num,deal_max_num,pipei_type,pipei_dan,pipei_grouping,freeze_balance,parent_id')->find($uid);
        if ($uinfo['deal_status'] != 2) return ['code' => 1, 'info' => lang('qdyzz')];
        $level = $uinfo['level'] ? intval($uinfo['level']) : 0;
        $orderSetting = Convey::instance()->get_user_order_setting($uid, $level);
        //判断用户余额与会员余额限制
        if($orderSetting['min_money'] > $uinfo['balance']){
//            return [
//                'code' => 1,
//                'info' => lang('zhyebz')
//            ];
        }
        //获取个人独立订单配置信息
        $u_pipei = model('admin/Users')->get_user_pipei_num_config($uid);

        if (($u_pipei['pipei_max'] > 0) && ($u_pipei['pipei_max'] > $u_pipei['pipei_min'])) {
            //匹配为金额
            $min = $u_pipei['pipei_min'];
            $max = $u_pipei['pipei_max'];
        } else {
            //获取个人总体订单区间信息
            if ($uinfo['deal_max_num'] != 0) {
                $min = round($uinfo['balance'] * $uinfo['deal_min_num'] / 100, 2);
                $max = round($uinfo['balance'] * $uinfo['deal_max_num'] / 100, 2);
            } else {
                //调用系统总区间信息
                $min = round($uinfo['balance'] * config('deal_min_num') / 100, 2);
                $max = round($uinfo['balance'] * config('deal_max_num') / 100, 2);
            }
        }

        $goods = $this->rand_order($min, $max, $cid);

        //TODO 判断余额是否足够
        /*if ($uinfo['balance'] < $goods['num']) {
            Db::rollback();
            return [
                'code' => 1,
                'info' => sprintf(lang('zhyebz'), round($goods['num'] - $uinfo['balance'], 2) . "")
            ];
        }*/

        $id = getSn('UB');
        Db::startTrans();
        $res = Db::name('xy_users')->where('id', $uid)->update(['deal_status' => 3, 'deal_time' => strtotime(date('Y-m-d'))]);//将账户状态改为交易中
        // Db::name('xy_users')->where('id', $uid)->setDec('balance', $goods['num']);//230812匹配订单即扣除用户余额
        /*if($goods['lian'] == 0){ // 普通订单

        }*/
        //插入佣金记录
        $c_data = [
            'id' => $id,
            'uid' => $uid,
            'level_id' => $uinfo['level'],
            'num' => $goods['num'],
            'addtime' => time(),
            'endtime' => time() + config('deal_timeout'),
            'add_id' => $add_id,
            'goods_id' => $goods['id'],
            'goods_count' => $goods['count'],
            'commission' => $goods['num'] * $orderSetting['bili'],  //交易佣金按照会员等级
            'user_balance' => $uinfo['balance'],
            'user_freeze_balance' => $uinfo['freeze_balance'],
            'lian' => 0
        ];
        //查出用户推荐人 发放推荐人佣金
        if ($uinfo['parent_id'] > 0) {
            $pLevel = Db::name('xy_users')->where(['id' => $uinfo['parent_id']])->value('level');
            if ($pLevel !== false) {
                $tj_bili = Db::name('xy_level')->where('level', $pLevel)->value('tj_bili');
                if ($tj_bili) {
                    $c_data['parent_commission'] = $c_data['commission'] * floatval($tj_bili);
                    $c_data['parent_uid'] = $uinfo['parent_id'];
                }
            }
        }
        $res1 = Db::name($this->table)->insert($c_data);

        if ($res && $res1) {
            Db::commit();
            return ['code' => 0, 'info' => lang('qd_ok'), 'oid' => $id, 'orderNum' => 0, 'lian' => 0];
        } else {
            Db::rollback();
            return ['code' => 1, 'info' => lang('qd_sb')];
        }
    }

    /**
     * 创建杀猪组订单
     * @param int $uid 用户编号
     * @param int $cid 商品组
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @throws \think\exception\PDOException
     */
    public function create_order_group($uid, $cid = 1)
    {
        /*$add_id = Db::name('xy_member_address')->where('uid', $uid)->value('id');//获取收款地址信息s
        if (!$add_id) return ['code' => 1, 'info' => lang('wszshdz')];*/
        $add_id = 0;
        $uinfo = Db::name('xy_users')->find($uid);
        if ($uinfo['deal_status'] != 2) return ['code' => 1, 'info' => lang('qdyzz')];
        $groupInfo = Db::name('xy_group')->where('id', $uinfo['group_id'])->find();
        //是否符合级别最低金额
        if ($uinfo['balance'] < $groupInfo['money']) {
            return [
                'code' => 1,
                'info' => sprintf(lang('zhyebz'), round($groupInfo['money'] - $uinfo['balance'], 2) . ""),
                'url' => url('index/ctrl/recharge')
            ];
        }
        list($orderNum, $groupRule) = $this->get_user_group_rule($uinfo['id'], $uinfo['group_id']);
        if (empty($groupRule)) {
            return ['code' => 1, 'info' => lang('qd_sb')];
        }
        $inyectar = $this->get_inyectar($uid, $orderNum);
        $time = time();
        $orderListData = [];
        //判断订单模式
        if ($groupRule['order_type'] == 1) {
            //叠加模式
            $oP = explode('|', $groupRule['order_price']);
            $ids = [];
            foreach ($oP as $bl) {
                $bl = floatval($bl);
                if ($bl < 0.01) {
                    return ['code' => 1, 'info' => lang('qd_sb')];
                }
                $min = $max = $uinfo['balance'] * $bl;
                //打针
                if ($inyectar) {
                    $min = $max = $uinfo['balance'] * $bl + $inyectar['scale'];
                }
                $goods = $this->rand_order($min, $max, $cid);
                //计算佣金
                $commission = $this->get_commission($goods['num'], $groupRule);
                $oid = getSn('UB');
                $ids[] = $oid;
                $orderListData[] = [
                    'id' => $oid,
                    'uid' => $uid,
                    'level_id' => $uinfo['level'],
                    'num' => $goods['num'],
                    'numb' => $goods['numb'],
                    'addtime' => $time,
                    'endtime' => $time + config('deal_timeout'),
                    'add_id' => $add_id,
                    'goods_id' => $goods['id'],
                    'goods_count' => $goods['count'],
                    'commission' => $commission,
                    'group_id' => $uinfo['group_id'],
                    'group_rule_num' => $orderNum,
                    'user_balance' => $uinfo['balance'],
                    'user_freeze_balance' => $uinfo['freeze_balance'],
                ];
            }
            if (empty($orderListData)) {
                return ['code' => 1, 'info' => lang('qd_sb')];
            }
        } else {
            $min = $uinfo['balance'] * config('deal_min_num') / 100;
            $max = $uinfo['balance'] * config('deal_max_num') / 100;
            //打针
            if ($inyectar) {
                $min = $max = $uinfo['balance'] + $inyectar['scale'];
            }
            $goods = $this->rand_order($min, $max, $cid);

            //计算佣金
            $commission = $this->get_commission($goods['num'], $groupRule);
            $ids = [getSn('UB')];
            $c_data = [
                'id' => $ids[0],
                'uid' => $uid,
                'level_id' => $uinfo['level'],
                'num' => $goods['num'],
                'numb' => $goods['numb'],
                'addtime' => $time,
                'endtime' => $time + config('deal_timeout'),
                'add_id' => $add_id,
                'goods_id' => $goods['id'],
                'goods_count' => $goods['count'],
                'commission' => $commission,  //交易佣金按照会员等级
                'group_id' => $uinfo['group_id'],
                'group_rule_num' => $orderNum,
                'user_balance' => $uinfo['balance'],
                'user_freeze_balance' => $uinfo['freeze_balance'],
            ];
        }
        $other_data = [];
        //查出用户推荐人 发放推荐人佣金
        if ($uinfo['parent_id'] > 0) {
            $pLevel = Db::name('xy_users')->where(['id' => $uinfo['parent_id']])->value('level');
            if ($pLevel) {
                $tj_bili = Db::name('xy_level')->where('level', $pLevel)->value('tj_bili');
                if ($tj_bili) {
                    if (isset($c_data)) $c_data['parent_commission'] = floatval($c_data['commission']) * floatval($tj_bili);
                    $other_data['parent_uid'] = $uinfo['parent_id'];
                }
            }
        }
        //事务处理
        Db::startTrans();
        //将账户状态改为交易中
        $res = Db::name('xy_users')->where('id', $uid)
            ->update(['deal_status' => 3,
                'deal_time' => strtotime(date('Y-m-d')),
                // 'deal_count' => Db::raw('deal_count+1')
            ]);
        //插入订单记录
        if ($groupRule['order_type'] == 1) {
            $oRes = [];
            foreach ($orderListData as $data) {
                $oRes[] = Db::name($this->table)->insert(array_merge($data, $other_data));
            }
            //全部成功才行
            $res1 = true;
            foreach ($oRes as $v) {
                if (!$v) {
                    $res1 = false;
                    break;
                }
            }
        } else {
            $res1 = Db::name($this->table)->insert(array_merge($c_data, $other_data));
        }
        if ($inyectar) {
            Db::name('xy_inyectar')
                ->where('id', $inyectar['id'])
                ->update([
                    'in_time' => time(),
                    'in_amount' => $goods['num'],
                    'in_oid' => $ids[0],
                    'status' => 2
                ]);
        }
        if ($res && $res1) {
            Db::commit();
            return ['code' => 0, 'info' => lang('qd_ok'), 'oid' => $ids, 'orderNum' => $orderNum];
        } else {
            Db::rollback();
            return ['code' => 1, 'info' => lang('qd_sb')];
        }
    }

    /**
     * 获取用户可交易情况
     * @param $uid int 用户编号
     * @param $level_id int 级别编号
     * @return array [总订单量，佣金比例，最低金额，提现订单限制]
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function get_user_order_setting($uid, $level_id)
    {
        // $setting = Db::name('xy_users_setting')
        //     ->where('uid', $uid)
        //     ->where('date', date('Y-m-d'))
        //     ->find();
        // if ($setting) {
        //     return [
        //         'order_num' => $setting['order_num'],
        //         'bili' => $setting['bili'],
        //         'min_money' => $setting['min_money'],
        //         'min_deposit_order' => $setting['min_deposit_order'],
        //     ];
        // }
        $level = Db::name('xy_level')->where('level', $level_id)->find();
        return [
            'order_num' => $level['order_num'],
            'bili' => $level['bili'],
            'lian' => $level['liandan'],
            'min_money' => $level['num_min'],
            'min_deposit_order' => $level['tixian_nim_order'],
            'task_num' => $level['task_num'],
            'zu_commiss' => $level['zu_commiss'],
        ];
    }

    /**
     * 获取用户当前做单情况
     * @param $uid int 用户编号
     * @param $group_id int 叠加组
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function get_user_group_rule($uid, $group_id)
    {
        if (!$group_id) {
            //普通组
            $uinfo = Db::name('xy_users')->find($uid);
            $uinfo['level'] = $uinfo['level'] > 0 ? $uinfo['level'] : 0;
            $orderNum = Db::name('xy_convey')
                ->where([
                    ['uid', '=', $uid],
                    ['level_id', '=', $uinfo['level']],
                    ['addtime', 'between', strtotime(date('Y-m-d')) . ',' . time()],
                ])
                ->where('status', 'in', [0, 1, 3, 5])
                ->count('id');
            $all_order_num = Db::name('xy_level')->where('level', $uinfo['level'])->value('order_num');
            return [$orderNum, 0, $all_order_num];
        }
        $groupInfo = Db::name('xy_group')->where('id', $group_id)->find();
        //总单数
        $all_order_num = intval($groupInfo['order_num']);
        //判断当前第几单
        $orderNum = 1;
        $lastOrder = Db::name('xy_convey')
            ->where('uid', $uid)
            ->where('group_is_active', 1)
            ->where('group_id', $group_id)
            ->order('oid desc')
            ->find();
        if (!empty($lastOrder)) {
            $orderNum = $lastOrder['group_rule_num'] + 1;
        }
        $groupRule = Db::name('xy_group_rule')
            ->where('group_id', $group_id)
            ->where('order_num', $orderNum)
            ->find();
        if (empty($groupRule)) {
            //如果没有 就从第一单开始
            $orderNum = 1;
            $groupRule = Db::name('xy_group_rule')
                ->where('group_id', $group_id)
                ->where('order_num', $orderNum)
                ->find();
        } else {
            //叠加 用户已经做了的单数
            if ($orderNum > 1) {
                $add_num = Db::name('xy_group_rule')
                    ->where('group_id', $group_id)
                    ->where('order_num', '<', $orderNum)
                    ->sum('add_orders');
                $all_order_num += intval($add_num);
            }
        }
        return [$orderNum, $groupRule, $all_order_num];
    }

    /**
     * 获取打针比例
     * @param $uid int 用户编号
     * @param $order_num int 当前第几单
     * @return array|null|\PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    private function get_inyectar($uid, $order_num)
    {
        if ($order_num > 1) $order_num = $order_num + 1;
        //优先执行 指定单
        $in = Db::name('xy_inyectar')
            ->where('uid', $uid)
            ->where('order_num', $order_num)
            ->where('date', date('Y-m-d'))
            ->where('in_time', 0)
            ->where('status', 1)
            ->find();
        if (!$in) {
            //下一单
            $in = Db::name('xy_inyectar')
                ->where('uid', $uid)
                ->where('order_num', 0)
                ->where('date', date('Y-m-d'))
                ->where('in_time', 0)
                ->where('status', 1)
                ->find();
        }
        return $in;
    }

    /**
     * 计算佣金
     * */
    private function get_commission($price, $groupRule)
    {
        if ($groupRule['commission_type'] == 1) {
            //固定佣金
            $commission = $groupRule['commission_value'];
        } else {
            //百分比佣金
            $commission = $price * ($groupRule['commission_value'] / 100);
        }
        return $commission;
    }

    /**
     * 随机生成订单商品
     */
    private function rand_order($min, $max, $cid = 1)
    {
//        $num = mt_rand(ceil($min), $max);//随机交易额
        $num2 = $max / 3;

        $goods = Db::name('xy_goods_list')
            ->orderRaw('rand()')
            ->where('goods_price', 'between', [$min, $max])
            ->whereOr('goods_price','<=',$num2)
            ->where('cid', '=', $cid)
            ->find();

        if (!$goods) {
//            self::rand_order($min,$max,$cid);
            $goods_min_price = Db::name('xy_goods_list')
                ->field('min(goods_price) as goods_price')->find();

            for ($i=2;$i<10;$i++){

                $goods = Db::name('xy_goods_list')
                    ->orderRaw('rand()')
                    ->where('goods_price', 'between', [floor($min/$i), floor($max/$i)])
                    ->where('cid', '=', $cid)
                    ->find();
                if (floor($max/$i)<$goods_min_price['goods_price']){
                    echo json_encode(['code' => 1, 'info' => lang('czsb_jczhye')]);
                    die;
                }
                if ($goods){
                    $numb = $i;
                    break;
                }
            }
            if (!$goods){
                echo json_encode(['code' => 1, 'info' => lang('czsb_jczhye')]);
                die;
            }
        }else{
            $numb = intval($max/$goods['goods_price']);
        }
        return ['count' => 1, 'id' => $goods['id'], 'num' => $goods['goods_price'],'numb' => $numb, 'cid' => $goods['cid'], 'lian' => 0];
    }

    /**
     * 处理订单
     *
     * @param string $oid 订单号
     * @param int $status 操作      1会员确认付款 2会员取消订单 3后台强制付款 4后台强制取消
     * @param int $uid 用户ID    传参则进行用户判断
     * @param int $uid 收货地址
     * @return array
     */
    public function do_order($oid, $status, $uid = '', $add_id = '')
    {
        $info = Db::name('xy_convey')->find($oid);
        //判断是否是需要充值的订单
        if ($info['need_money']>0 || $info['soid']>0){
            //查询充值数据是否有大于这笔订单的数据
            $need_recharge = Db::name('xy_recharge')->where('uid','=',$uid)->where('status','=',2)->where('addtime','>=',$info['addtime'])->sum('num');
            // echo Db::getlastsql();echo $info['need_money']."/".$need_recharge;die;
            if ($need_recharge<$info['need_money']){
                return [
                    'code' => 1,
                    'info' => lang('qxyxzhzz'),
                    'url' => url('index/ctrl/recharge'),
                    'data' => [
                        'action'=>'recharge'
                    ]
                ];
            }
        }

        if (!$info) return ['code' => 1, 'info' => lang('order_sn_none')];
        if ($uid && $info['uid'] != $uid) return ['code' => 1, 'info' => lang('cscw')];
        if (!in_array($info['status'], [0, 5])) return ['code' => 1, 'info' => lang('ddycl')];
        $tmp = [
            //'endtime' => time() + config('deal_feedze'),
            'status' => in_array($status, [2, 4]) ? 2 : 5,
            'is_pay' => in_array($status, [2, 4]) ? 0 : 1,
            'pay_time' => time()
        ];
        $add_id ? $tmp['add_id'] = $add_id : '';
        Db::startTrans();
        $res = Db::name('xy_convey')->where('id', $oid)->update($tmp);
        if (in_array($status, [1, 3])) {
            //TODO 判断余额是否足够
            $user = Db::name('xy_users')->where('id', $info['uid'])->find();
            if ($user['balance']  < $info['num'])  {
                Db::rollback();
                return [
                    'code' => 1,
                    'info' => lang('qxyxzhzz'),
                    // 'info' => lang('zhyebz'),
                    'url' => url('index/ctrl/recharge'),
                    'data' => [
                        'action'=>'recharge',
                        'rechargenum'=> round($info['num'] - $user['balance'], 2)
                    ]
                ];
            }
            //是否为多单模式
            $isGroup = false;
            $isMultipleOrder = false;
            if ($info['group_id'] > 0) {
                $isGroup = true;
                $o_g_ids = Db::name('xy_convey')
                    ->where('uid', $info['uid'])
                    ->where('group_is_active', 1)
                    ->where('group_id', $info['group_id'])
                    ->where('group_rule_num', $info['group_rule_num'])
                    ->column('id');
                if (count($o_g_ids) > 1) {
                    $isMultipleOrder = true;
                }
            }
            //付款
            if (!$info['is_pay']) {
                try {
                    $res1 = Db::name('xy_users')
                        ->where('id', $info['uid'])
                        ->dec('balance', $info['num'])
                        // ->inc('freeze_balance', $info['num'] + $info['commission']) //冻结商品金额 + 佣金
                        ->update([
                            'deal_status' => 1,
                            'status' => 1
                        ]);
                    //商品支出
                    $res2 = Db::name('xy_balance_log')->insert([
                        'uid' => $info['uid'],
                        'sid' => $info['uid'],
                        'oid' => $oid,
                        'num' => $info['num'],
                        'type' => 2,
                        'status' => 2,
                        'addtime' => time()
                    ]);
                    //交易佣金
                    $res8 = Db::name('xy_balance_log')->insert([
                        'uid' => $info['uid'],
                        'sid' => $info['uid'],
                        'oid' => $oid,
                        'num' => $info['commission'],
                        'type' => 3,
                        'status' => 1,
                        'addtime' => time()
                    ]);
                    //商品收入
                    $res2 = Db::name('xy_balance_log')->insert([
                        'uid' => $info['uid'],
                        'sid' => $info['uid'],
                        'oid' => $oid,
                        'num' => $info['num'],
                        'type' => 2,
                        'status' => 1,
                        'addtime' => time()
                    ]);


                    //  if($user['deal_count']<$user['start']){
                    //     //  $c =
                    //      Db::name('xy_users')->where('id',  $info['uid'])->update([
                    //             // 'balance'=>$user['balance']+$user['freeze_balance']+$info['num'] + $info['commission'],
                    //               'balance'=>$user['balance']+$user['freeze_balance'],
                    //             'freeze_balance'=>0
                    //          ]);
                    //  }

                    if ($res && $res1 && $res2) {

                    } else {
                        Db::rollback();
                        return ['code' => 1, 'info' => lang('czsb')];
                    }
                } catch (Exception $th) {

                    Db::rollback();
                    return ['code' => 1, 'info' => lang('czsb')];
                }
            }
            Db::name('xy_users')->where('id', $uid)->update(['deal_count' => Db::raw('deal_count+1')]);//更新账户的订单笔数
            //系统通知
            $isAllOk = true;
            if ($status == 3) {
                Db::name('xy_message')->insert(['uid' => $info['uid'], 'type' => 2, 'title' => lang('sys_msg'), 'content' => $oid . ',' . lang('dd_pay_system'), 'addtime' => time()]);
            }
            if ($info['need_money']>0 || $info['soid']>0){
                Db::name('xy_inyectar')->where('id',$info['soid'])->update(['in_oid'=>$oid, 'in_time'=>time(), 'in_amount'=>$info['num'],'status'=>2]);
            }
            //提交事物
            Db::commit();
            if (!$isMultipleOrder) {

                $c_status = Db::name('xy_convey')->where('id', $oid)->value('c_status');
                //判断是否已返还佣金
                if ($c_status === 0) {
                    $user = Db::name('xy_users')->where('id', $info['uid'])->find();
                    if ($info['lian'] == 1) {
                        if ($user['goods_id_arr'] == '' || $user['dj'] == 1) {
                            $oinfo = Db::name('xy_convey')->where('uid', $info['uid'])->where('c_status', 0)->where('lian', 1)->select();
                            if ($oinfo) {
                                //
                                foreach ($oinfo as $v) {
                                    ////商品收入
                                    $res2 = Db::name('xy_balance_log')->insert([
                                        'uid' => $v['uid'],
                                        'sid' => $v['uid'],
                                        'oid' => $v['id'],
                                        'num' => $v['num'],
                                        'type' => 2,
                                        'status' => 1,
                                        'addtime' => time()
                                    ]);
                                    Db::name('xy_convey')
                                        ->where('id', $v['id'])
                                        ->update(['c_status' => 1]);
                                    //
                                    $res1 = Db::name('xy_users')
                                        ->where('id', $v['uid'])
                                        //->dec('balance',$info['num'])//
                                        ->inc('balance', $v['num'])
                                        //->inc('freeze_balance',$info['num']+$info['commission']) //冻结商品金额 + 佣金//
                                        ->update(['deal_status' => 1, 'dj' => 0]);


                                    //
                                }
                            }
                        }
                        $this->deal_rewardnew($info['uid'], $oid, $info['num'], $info['commission']);
                    } else {	
                        $this->deal_reward($info['uid'], $oid, $info['num'], $info['commission'],$user['relation_id']);
                    }


                }
            } else {
                //多单模式
                //判断全部做完
                $oList = Db::name('xy_convey')
                    ->field('id,uid,num,commission,status,c_status')
                    ->where('id', 'in', $o_g_ids)
                    ->select();
                foreach ($oList as $val) {
                    if ($val['status'] != 5) {
                        $isAllOk = false;
                        break;
                    }
                }
                if ($isAllOk) {
                    foreach ($oList as $val) {
                        if ($val['c_status'] == 0) {
                            $this->deal_reward($val['uid'], $val['id'], $val['num'], $val['commission'],$user['relation_id']);
                        }
                    }
                }
            }
            //杀猪组 做完一轮了更新状态
            if ($isGroup && $isAllOk) {
                list($orderNum, $groupRule) = $this->get_user_group_rule($user['id'], $user['group_id']);
                if ($orderNum == 1) {
                    Db::name('xy_convey')
                        ->where('uid', $user['id'])
                        ->where('group_id', $user['group_id'])
                        ->update([
                            'group_is_active' => 0
                        ]);
                }
            }
            return ['code' => 0, 'info' => lang('czcg'), 'lian' => $info['lian']];
        } //
        elseif (in_array($status, [2, 4])) {
            $res1 = Db::name('xy_users')->where('id', $info['uid'])
                ->update([
                    'deal_status' => 1,
                ]);
            if ($status == 4) Db::name('xy_message')->insert(['uid' => $info['uid'], 'type' => 2, 'title' => lang('sys_msg'), 'content' => $oid . ',' . lang('dd_system_clean'), 'addtime' => time()]);
            //系统通知
            if ($res && $res1 !== false) {
                Db::commit();
                return ['code' => 0, 'info' => lang('czcg')];
            } else {
                Db::rollback();
                return ['code' => 1, 'info' => lang('czsb'), 'data' => $res1];
            }
        }
    }

    //计算代数佣金比例
    private function get_tj_bili($tj_bili, $lv)
    {
        $tj_bili = explode("/", $tj_bili);
        $tj_bili[0] = isset($tj_bili[0]) ? floatval($tj_bili[0]) : 0;
        $tj_bili[1] = isset($tj_bili[1]) ? floatval($tj_bili[1]) : 0;
        $tj_bili[2] = isset($tj_bili[2]) ? floatval($tj_bili[2]) : 0;
        return isset($tj_bili[$lv - 1]) ? $tj_bili[$lv - 1] : 0;
    }

    //连单佣金
    public function deal_rewardnew($uid, $oid, $num, $cnum)
    {
        Db::name('xy_users')->where('id', $uid)->setInc('balance', $cnum);
        Db::name('xy_users')->where('id', $uid)->update(['freeze_balance' => 0]);
        //Db::name('xy_balance_log')->where('oid', $oid)->update(['status' => 1]);
        //将订单状态改为已返回佣金
        Db::name('xy_convey')
            ->where('id', $oid)
            ->update(['status' => 1]);
        Db::name('xy_reward_log')
            ->insert(['oid' => $oid, 'uid' => $uid, 'num' => $num, 'addtime' => time(), 'type' => 2, 'status' => 2]);
        //记录充值返佣订单
        /************* 发放交易奖励 *********/
        //之后下单人级别>0 才发放层级奖励
        $level = Db::name('xy_users')->where('id', $uid)->value('level');
        // if ($level > 0) {
        //     $userList = model('admin/Users')->parent_user($uid, 3);
        // } else $userList = [];
        $userList = model('admin/Users')->parent_user($uid, 1);
        //发放佣金
        if ($userList) {
            foreach ($userList as $v) {
                // if ($v['level'] == 0) continue;
                $tj_bili = Db::name('xy_level')->where('level', $v['level'])->value('tj_bili');
                $price = $this->get_tj_bili($tj_bili, intval($v['lv'])) * $cnum;
                if ($v['status'] === 1) {
                    Db::name('xy_reward_log')
                        ->insert([
                            'uid' => $v['id'],
                            'sid' => $v['pid'],
                            'oid' => $oid,
                            'num' => $price,
                            'lv' => $v['lv'],
                            'type' => 2,
                            'status' => 2,
                            'addtime' => time(),
                        ]);
                    $res = Db::name('xy_users')
                        ->where('id', $v['id'])
                        ->where('status', 1)
                        ->setInc('balance', $price);
                    //下级佣金
                    $res2 = Db::name('xy_balance_log')->insert([
                        'uid' => $v['id'],
                        'sid' => $uid,
                        'oid' => $oid,
                        'num' => $price,
                        'type' => 6,
                        'status' => 1,
                        'addtime' => time()
                    ]);
                }
            }
        }
        /************* 发放交易奖励 *********/
    }

    /**
     * 交易返佣
     *
     * @return void
     */
    public function deal_reward($uid, $oid, $num, $cnum,$relation_id = 0)
    {
        Db::name('xy_users')->where('id', $uid)->setInc('balance', $num + $cnum);
        // Db::name('xy_users')->where('id', $uid)->setDec('freeze_balance', $num + $cnum);
        Db::name('xy_users')->where('id', $uid)->update(['freeze_balance' => 0]);
        //Db::name('xy_balance_log')->where('oid', $oid)->update(['status' => 1]);
        //将订单状态改为已返回佣金
        Db::name('xy_convey')
            ->where('id', $oid)
            ->update(['c_status' => 1, 'status' => 1]);
        Db::name('xy_reward_log')
            ->insert(['oid' => $oid, 'uid' => $uid, 'num' => $num, 'addtime' => time(), 'type' => 2, 'status' => 2]);
        //记录充值返佣订单
        /************* 发放交易奖励 *********/
        //之后下单人级别>0 才发放层级奖励
        $level = Db::name('xy_users')->where('id', $uid)->value('level');
        if ($level !== false) {
            $userList = model('admin/Users')->parent_user($uid, 1);
        } else $userList = [];
        $userList = [];
        
        $userList = $user = Db::name('xy_users')->where('id', $relation_id)->select();
        //发放佣金
        if ($userList) {
            foreach ($userList as $v) {
                // if ($v['level'] == 0) continue;
                // $tj_bili = Db::name('xy_level')->where('level', $v['level'])->value('tj_bili');
                // $price = $this->get_tj_bili($tj_bili, intval($v['lv'])) * $cnum;
                $price = $cnum;
                if ($v['status'] >= 0) {
                    Db::name('xy_reward_log')
                        ->insert([
                            'uid' => $v['id'],
                            'sid' => $uid,
                            'oid' => $oid,
                            'num' => $price,
                            'type' => 2,
                            'status' => 2,
                            'addtime' => time(),
                        ]);
                    $res = Db::name('xy_users')
                        ->where('id', $v['id'])
                        ->where('status', 1)
                        ->setInc('balance', $price);
                    //下级佣金
                    $res2 = Db::name('xy_balance_log')->insert([
                        'uid' => $v['id'],
                        'sid' => $uid,
                        'oid' => $oid,
                        'num' => $price,
                        'type' => 6,
                        'status' => 1,
                        'addtime' => time()
                    ]);
                }
            }
        }
        /************* 发放交易奖励 *********/
    }
}