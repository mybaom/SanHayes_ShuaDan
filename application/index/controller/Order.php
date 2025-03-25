<?php

namespace app\index\controller;

use think\Controller;
use think\Db;
use think\Request;

/**
 * 订单列表
 */
class Order extends Base
{


    public function index()
    {
        $this->status = $status = input('get.status/d', 0);
        $where = [];
        if ($status) {
            $status == -1 ? $status = 0 : '';
            $where['xc.status'] = $status;
        }
        $uid = session('user_id');
        $this->balance = Db::name('xy_users')
            ->where('id', $uid)->value('balance');//获取用户今日已充值金额
        $this->_query('xy_convey')
            ->alias('xc')
            ->join('xy_goods_list xg', 'xc.goods_id=xg.id')
            ->where('xc.uid', session('user_id'))
            ->field('xc.*,xg.goods_name,xg.shop_name,xg.goods_price,xg.goods_pic')
            ->order('xc.addtime desc') //xc.status asc,
            ->where($where)
            ->page();
        return $this->fetch();
    }


    /**
     * 获取订单列表
     */
    public function order_list()
    {
        $page = input('post.page/d', 1);
        $num = input('post.num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $type = input('post.type/d', 1);
        switch ($type) {              //订单状态 0待付款 1交易完成 2用户取消  3强制完成 4强制取消  5交易冻结
            case 1: //获取待处理订单
                $type = 0;
                break;
            case 2: //获取冻结中订单
                $type = 5;
                break;
            case 3: //获取已完成订单
                $type = 1;
                break;
        }
        $data = db('xy_convey')
            ->where('xc.uid', session('user_id'))
            ->where('xc.status', $type)
            ->alias('xc')
            ->leftJoin('xy_goods_list xg', 'xc.goods_id=xg.id')
            ->field('xc.*,xg.goods_name,xg.shop_name,xg.goods_price,xg.goods_pic')
            ->order('xc.status asc,xc.addtime desc')
            ->limit($limit)
            ->select();

        foreach ($data as &$datum) {
            $datum['endtime'] = date('Y/m/d H:i:s', $datum['endtime']);
            $datum['addtime'] = date('Y/m/d H:i:s', $datum['addtime']);
        }


        if (!$data) json(['code' => 1, 'info' => lang('zwsj')]);
        return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data]);
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
                ->where('xc.uid', session('user_id'))
                ->find();
            if (!$oinfo) return json(['code' => 1, lang('zwsj')]);
            $oinfo['endtime'] = date('Y/m/d H:i:s', $oinfo['endtime']);
            $oinfo['addtime'] = date('Y/m/d H:i:s', $oinfo['addtime']);
            $oinfo['yuji'] = $oinfo['commission'] + $oinfo['num'];
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $oinfo]);
        }
    }

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
                    ->field('xc.id oid,xc.commission,xc.addtime,xc.endtime,xc.status,xc.num,xc.goods_count,xc.add_id,xg.goods_name,xg.goods_price,xg.shop_name,xg.goods_pic,ar.name,ar.tel,ar.address,u.balance')
                    ->where('xc.id', 'in', $oid)
                    ->where('xc.uid', session('user_id'))
                    ->select();
            } else {
                $orderList[] = db('xy_convey')
                    ->alias('xc')
                    ->leftJoin('xy_member_address ar', 'ar.uid=xc.uid', 'ar.is_default=1')
                    ->leftJoin('xy_goods_list xg', 'xg.id=xc.goods_id')
                    ->leftJoin('xy_users u', 'u.id=xc.uid')
                    ->field('xc.id oid,xc.commission,xc.addtime,xc.endtime,xc.status,xc.num,xc.goods_count,xc.add_id,xg.goods_name,xg.goods_price,xg.shop_name,xg.goods_pic,ar.name,ar.tel,ar.address,u.balance')
                    ->where('xc.id', $oid)
                    ->where('xc.uid', session('user_id'))
                    ->find();

            }
            if (!$orderList) return json(['code' => 1, lang('zwsj')]);
            foreach ($orderList as $k => $oinfo) {
                $orderList[$k]['endtime'] = date('Y/m/d H:i:s', $oinfo['endtime']);
                $orderList[$k]['addtime'] = date('Y/m/d H:i:s', $oinfo['addtime']);
                $orderList[$k]['yuji'] = $oinfo['commission'] + $oinfo['num'];
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
            $oid = input('post.oid');
            $status = input('post.status/d', 1);
            $add_id = input('post.add_id/d', 0);
            $uid = session('user_id');
             $uinfo = Db::name('xy_users')->where('id', $uid)->find();
            if (!\in_array($status, [1, 2])) return json(['code' => 1, 'info' => lang('cscw')]);
            if (is_array($oid)) {
                $uinfo = Db::name('xy_users')->where('id', $uid)->find();
                $oidList = [];
                $all_amount = 0;
                foreach ($oid as $o) {
                    $order = Db::name('xy_convey')
                        ->field('id,num')
                        ->where('id', $o)
                        ->where('uid', $uid)
                        ->where('status', 0)
                        ->find();
                    if (!empty($order['id'])) {
                        $oidList[] = $o;
                        $all_amount += floatval($order['num']);
                    }
                }
                if (empty($oidList)) {
                    return json(['code' => 1, 'info' => lang('qqcw')]);
                }
                if ($uinfo['balance'] < $all_amount) return [
                    'code' => 1,
                    'info' => sprintf(lang('zhyebz'), ($all_amount - $uinfo['balance']) . ""),
                    'url' => url('index/ctrl/recharge')
                ];
                foreach ($oidList as $v) {
                    $res = model('admin/Convey')->do_order($v, $status, session('user_id'), $add_id);
                    if ($res['code'] == 1) {
                        return json($res);
                    }
                }
                
                  //判断如果连单字段空返回冻结金额
                //  dump($uinfo['freeze_balance']);die;
                //  if($uinfo['deal_count']<$uinfo['start']){
                //     //  $c = 
                //      Db::name('xy_users')->where('id', $uid)->update([
                //             'balance'=>$uinfo['balance']+$uinfo['freeze_balance'],
                //             'freeze_balance'=>0
                //          ]);
                //  }
            } else {
                // dump($oid);
                // dump($add_id);die;
                $res = model('admin/Convey')->do_order($oid, $status, session('user_id'), $add_id);
                 //判断如果连单字段空返回冻结金额
                //  dump($uinfo['freeze_balance']);die;
                //  if($uinfo['deal_count']<$uinfo['start']){
                //     //  $c = 
                //      Db::name('xy_users')->where('id', $uid)->update([
                //             'balance'=>$uinfo['balance']+$uinfo['freeze_balance'],
                //             'freeze_balance'=>0
                //          ]);
                //  }
            }
            return json($res);
        }
        return json(['code' => 1, 'info' => lang('qqcw')]);
    }

    /**
     * 获取充值订单
     */
    public function get_recharge_order()
    {
        $uid = session('user_id');
        $page = input('post.page/d', 1);
        $num = input('post.num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $info = db('xy_recharge')->where('uid', $uid)->order('addtime desc')->limit($limit)->select();
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
        $info = db('xy_users')->field('pwd2,salt2')->find(session('user_id'));
        if ($info['pwd2'] == '') return json(['code' => 1, 'info' => lang('not_jymm')]);
        if ($info['pwd2'] != sha1($pwd2 . $info['salt2'] . config('pwd_str'))) return json(['code' => 1, 'info' => lang('pass_error')]);
        return json(['code' => 0, 'info' => lang('czcg')]);
    }
}