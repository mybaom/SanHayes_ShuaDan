<?php

namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Request;

/**
 * 订单列表
 */
class Order extends Base
{
    /**
     * 获取订单列表
     */
    public function order_list()
    {
        $page = input('post.page/d', 1);
        $num = input('post.num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $status = input('post.status/d', 0);
        $where = [];
        if ($status) {
            if ($status == -1){
                $nstatus = 0;
            }
            $where['xc.status'] = $nstatus??$status;
        }
        $data = db('xy_convey')
            ->alias('xc')
            ->where('xc.uid', $this->_uid)
            ->where($where)
            ->leftJoin('xy_goods_list xg', 'xc.goods_id=xg.id')
            ->field('xc.*,xg.goods_name,xg.shop_name,xg.goods_price,xg.goods_pic')
            ->order('xc.status asc,xc.addtime desc')
            ->paginate($num)
            ->each(function($item, $key){
                if($item['status'] ==0 &&$item['need_money']>0){
                    $need_recharge = Db::name('xy_recharge')->where('uid','=',$this->_uid)->where('status','=',2)->where('addtime','>=',$item['addtime'])->sum('num');
                    $item['need_money'] = $item['need_money'] - $need_recharge<0?0:round($item['need_money'] - $need_recharge,2);
                }
                $item['endtime'] = date('Y/m/d H:i:s', $item['endtime']);
                $item['addtime'] = date('Y/m/d H:i:s', $item['addtime']);
                $item['goods_pic'] = request()->domain().$item['goods_pic'];
                $item['goods_price'] = $item['num'];
                return $item;
            });
        if (!$data) $this->error(lang('zwsj'));
        return $this->success(lang('czcg'), $data);
    }

    /**
     * 获取单笔订单详情
     */
    public function order_info()
    {
        if (\request()->isPost()) {
            $oid = input('post.id', '');
            $oinfo = db('xy_convey')
                ->alias('xc')
                ->leftJoin('xy_member_address ar', 'ar.uid=xc.uid', 'ar.is_default=1')
                ->leftJoin('xy_goods_list xg', 'xg.id=xc.goods_id')
                ->leftJoin('xy_users u', 'u.id=xc.uid')
                ->field('xc.id oid,xc.commission,xc.addtime,xc.endtime,xc.status,xc.num,xc.goods_count,xc.add_id,xg.goods_name,xg.goods_price,xg.shop_name,xg.goods_pic,ar.name,ar.tel,ar.address,u.balance')
                ->where('xc.id', $oid)
                ->where('xc.uid', $this->_uid)
                ->find();
            if (!$oinfo) return json(['code' => 1, lang('zwsj')]);
            $oinfo['endtime'] = date('Y/m/d H:i:s', $oinfo['endtime']);
            $oinfo['addtime'] = date('Y/m/d H:i:s', $oinfo['addtime']);
            $oinfo['yuji'] = $oinfo['commission'] + $oinfo['num'];
            return $this->success(lang('czcg'), $oinfo);
        }
    }

    /**
     * 处理提交订单展示的订单详情
     * @return \think\response\Json|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order_info_list()
    {
        if (\request()->isPost()) {
            $oid = input('post.id');
            if (is_array($oid)) {
                $orderList = db('xy_convey')
                    ->alias('xc')
                    ->leftJoin('xy_member_address ar', 'ar.uid=xc.uid', 'ar.is_default=1')
                    ->leftJoin('xy_goods_list xg', 'xg.id=xc.goods_id')
                    ->leftJoin('xy_users u', 'u.id=xc.uid')
                    ->field('xc.id,xc.commission,xc.addtime,xc.endtime,xc.status,xc.num,xc.goods_count,xc.add_id,xg.goods_name,xg.goods_price,xg.shop_name,xg.goods_pic,ar.name,ar.tel,ar.address,u.balance,xc.need_money,xc.numb,xc.deal_count')
                    ->where('xc.id', 'in', $oid)
                    ->where('xc.uid', $this->_uid)
                    ->select();
            } else {
                $orderList[] = db('xy_convey')
                    ->alias('xc')
                    ->leftJoin('xy_member_address ar', 'ar.uid=xc.uid', 'ar.is_default=1')
                    ->leftJoin('xy_goods_list xg', 'xg.id=xc.goods_id')
                    ->leftJoin('xy_users u', 'u.id=xc.uid')
                    ->field('xc.id,xc.commission,xc.addtime,xc.endtime,xc.status,xc.num,xc.goods_count,xc.add_id,xg.goods_name,xg.goods_price,xg.shop_name,xg.goods_pic,ar.name,ar.tel,ar.address,u.balance,xc.need_money,xc.numb,xc.deal_count')
                    ->where('xc.id', $oid)
                    ->where('xc.uid', $this->_uid)
                    ->find();

            }
            if (!$orderList) return json(['code' => 1, lang('zwsj')]);
            foreach ($orderList as $k => $oinfo) {
                $orderList[$k]['endtime'] = date('Y/m/d H:i:s', $oinfo['endtime']);
                $orderList[$k]['addtime'] = date('Y/m/d H:i:s', $oinfo['addtime']);
                $orderList[$k]['yuji'] = $oinfo['commission'] + $oinfo['num'];
                $orderList[$k]['goods_pic'] = request()->domain().$oinfo['goods_pic'];
                $orderList[$k]['goods_price'] = $oinfo['num'];
            }
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $orderList]);
        }
    }

    /**
     * 处理订单
     */
    public function do_order()
    {
        if (request()->isPost()) {
            $oid = input('post.id');
            $status = input('post.status/d', 1);
            $add_id = input('post.add_id/d', 0);
            $uinfo = Db::name('xy_users')->where('id', $this->_uid)->find();
            if (!\in_array($status, [1, 2])) return $this->error(lang('cscw'));
            if (is_array($oid)) {
                $uinfo = Db::name('xy_users')->where('id', $this->_uid)->find();
                $oidList = [];
                $all_amount = 0;
                foreach ($oid as $o) {
                    $order = Db::name('xy_convey')
                        ->field('id,num')
                        ->where('id', $o)
                        ->where('uid', $this->_uid)
                        ->where('status', 0)
                        ->find();
                    if (!empty($order['id'])) {
                        $oidList[] = $o;
                        $all_amount += floatval($order['num']);
                    }
                }
                if (empty($oidList)) {
                    return $this->error(lang('qqcw'));
                }
                if ($uinfo['balance'] < $all_amount) return [
                    'code' => 5,
                    'info' => lang('qxyxzhzz'),
                    'url' => url('index/ctrl/recharge'),
                    'data' => [
                        'action'=>'recharge'
                    ]
                ];
                foreach ($oidList as $v) {
                    $res = model('admin/Convey')->do_order($v, $status, $this->_uid, $add_id);
                    if ($res['code'] == 1) {
                        return json($res);
                    }
                }

                //判断如果连单字段空返回冻结金额
                //  dump($uinfo['freeze_balance']);die;
                //  if($uinfo['deal_count']<$uinfo['start']){
                //     //  $c = 
                //      Db::name('xy_users')->where('id', $this->_uid)->update([
                //             'balance'=>$uinfo['balance']+$uinfo['freeze_balance'],
                //             'freeze_balance'=>0
                //          ]);
                //  }
            } else {
                $res = model('admin/Convey')->do_order($oid, $status, $this->_uid, $add_id);
                //判断如果连单字段空返回冻结金额
                //  dump($uinfo['freeze_balance']);die;
                //  if($uinfo['deal_count']<$uinfo['start']){
                //     //  $c = 
                //      Db::name('xy_users')->where('id', $this->_uid)->update([
                //             'balance'=>$uinfo['balance']+$uinfo['freeze_balance'],
                //             'freeze_balance'=>0
                //          ]);
                //  }
            }
            return json($res);
        }
        return $this->error(lang('qqcw'));
    }

    /**
     * 获取充值订单
     */
    public function get_recharge_order()
    {
        $page = input('post.page/d', 1);
        $num = input('post.num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $info = db('xy_recharge')->where('uid', $this->_uid)->order('addtime desc')->limit($limit)->select();
        if (!$info) return json(['code' => 1, 'info' => lang('zwsj')]);
        return json(['code' => 0, 'info' => lang('czcg'), 'data' => $info]);
    }

    /**
     * 验证提现密码
     */
    public function check_pwd2()
    {
        if (!request()->isPost()) return json(['code' => 1, 'info' => lang('qqcw')]);
        $pwd2 = input('post.pwd2/s', '');
        $info = db('xy_users')->field('pwd2,salt2')->find($this->_uid);
        if ($info['pwd2'] == '') return json(['code' => 1, 'info' => lang('not_jymm')]);
        if ($info['pwd2'] != sha1($pwd2 . $info['salt2'] . config('pwd_str'))) return json(['code' => 1, 'info' => lang('pass_error')]);
        return json(['code' => 0, 'info' => lang('czcg')]);
    }
}