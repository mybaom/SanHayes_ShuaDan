<?php

namespace app\admin\controller;

use app\admin\service\NodeService;
use library\Controller;
use library\tools\Data;
use think\Db;
use PHPExcel;

//tp5.1用法
use PHPExcel_IOFactory;

/**
 * 交易中心
 * Class Users
 * @package app\admin\controller
 */
class Deal extends Base
{

    /**
     * 订单列表
     * @auth true
     * @menu true
     */
    public function order_list()
    {
        $this->title = '订单列表';
        $where = [];
        if (input('oid/s', '')) $where[] = ['xc.id', 'like', '%' . input('oid', '') . '%'];
        $status = input('status', -1);
        if ($status != -1) $where[] = ['xc.status', '=', $status];
        if (input('username/s', '')) $where[] = ['u.username', 'like', '%' . input('username/s', '') . '%'];
        if (input('mobile/s', '')) $where[] = ['u.tel', '=', input('mobile/s', '')];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['fc.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1] . ' 23:59:59')]];
        }
        $this->status = $status;
        $this->statusList = [0 => '待付款', 1 => '交易完成', 2 => '用户取消', 3 => '强制完成', 4 => '强制取消', 5 => '交易冻结'];
        $agent_id = model('admin/Users')->get_admin_agent_id();
        if ($agent_id) {
            $agent_user_id = model('admin/Users')->get_admin_agent_uid();
            if ($agent_user_id) {
                $where[] = ['u.agent_service_id', '=', $agent_id];
            } else {
                $where[] = ['u.agent_id', '=', $agent_id];
            }
        }
    // $page_limit = cookie('page-limit')>0?cookie('page-limit'):20;
    //     $limit = input('limit', $page_limit) ;
    //     cookie('page-limit',$limit);
    //     $page = input('page', 1);
    //     $pagesize = $limit*($page-1);
    //     $count = Db::name('xy_convey')->alias('xc')
    //         ->leftJoin('xy_users u', 'u.id=xc.uid')
    //         ->leftJoin('xy_goods_list g', 'g.id=xc.goods_id')
    //         ->field('xc.*,u.username,u.tel,g.goods_name,g.goods_price,u.balance,u.freeze_balance')
    //         ->field('xc.*,u.username,u.tel,u.balance,u.freeze_balance')
    //         ->where($where)
    //         ->count();
        
    //     $data = Db::name('xy_convey')->alias('xc')
    //         ->leftJoin('xy_users u', 'u.id=xc.uid')
    //         ->leftJoin('xy_goods_list g', 'g.id=xc.goods_id')
    //         ->field('xc.*,u.username,u.tel,g.goods_name,g.goods_price,u.balance,u.freeze_balance')
    //         ->field('xc.*,u.username,u.tel,u.balance,u.freeze_balance')
    //         ->where($where)
    //         ->order('xc.id desc')->paginate($limit,$count);
           
        // echo Db::getlastsql();
    
   
    
        $this->_query('xy_convey')
            ->alias('xc')
            ->leftJoin('xy_users u', 'u.id=xc.uid')
            // ->leftJoin('xy_goods_list g', 'g.id=xc.goods_id')
            // ->field('xc.*,u.username,u.tel,g.goods_name,g.goods_price,u.balance,u.freeze_balance')
            ->field('xc.*,u.username,u.tel,u.balance,u.freeze_balance')
            ->where($where)
            ->order('xc.oid desc')
            ->page();
    }
     public function _order_list_page_filter(&$data){
         foreach ($data as &$vo) {
             if($vo['is_pay'] ==1){
                 $vo['need_money'] = 0;
             }else{
                 $need_recharge = Db::name('xy_recharge')->where('uid','=',$vo['uid'])->where('status','=',2)->where('addtime','>=',$vo['addtime'])->sum('num');
                    $vo['need_money'] = $vo['need_money'] - $need_recharge<0?0:$vo['need_money'] - $need_recharge;
             }
         }
         $data = Data::arr2table($data);
     }

    /**
     * 更改订单状态
     * @auth true
     */
    public function order_status()
    {
        $this->applyCsrfToken();
        $this->_form('xy_convey', 'form');
    }

    protected function _order_status_form_result($result, $data)
    {
        sysoplog('更改订单状态', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 处理用户交易订单
     * @auth true
     */
    public function do_user_order()
    {
        $this->applyCsrfToken();
        $oid = input('post.id/s', '');
        $status = input('post.status/d', 2);
        if (!\in_array($status, [3, 4])) return $this->error('参数错误');
        $res = model('Convey')->do_order($oid, $status);
        if ($res['code'] === 0) {
            sysoplog('处理用户交易订单', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            return $this->success('操作成功');
        } else
            return $this->error($res['info']);
    }

    /**
     * 交易控制
     * @auth true
     * @menu true
     */
    public function deal_console()
    {
        $this->title = '交易控制';
        if (request()->isPost()) {
            $deal_min_balance = input('post.deal_min_balance/d', 0);
            $deal_timeout = input('post.deal_timeout/d', 0);
            $deal_min_num = input('post.deal_min_num/d', 0);
            $deal_max_num = input('post.deal_max_num/d', 0);
            $deal_count = input('post.deal_count/d', 0);
            $deal_reward_count = input('post.deal_reward_count/d', 0);
            $deal_feedze = input('post.deal_feedze/d', 0);
            $deal_error = input('post.deal_error/d', 0);
            $deal_commission = input('post.deal_commission/f', 0);
            $_1reward = input('post.1_reward/f', 0);
            $_2reward = input('post.2_reward/f', 0);
            $_3reward = input('post.3_reward/f', 0);
            $_1_d_reward = input('post.1_d_reward/f', 0);
            $_2_d_reward = input('post.2_d_reward/f', 0);
            $_3_d_reward = input('post.3_d_reward/f', 0);
            $_4_d_reward = input('post.4_d_reward/f', 0);
            $_5_d_reward = input('post.5_d_reward/f', 0);

            //可以加上限制条件
            if ($deal_commission > 1 || $deal_commission < 0) return $this->error('参数错误');
            setconfig(['deal_min_balance'], [$deal_min_balance]);
            setconfig(['deal_timeout'], [$deal_timeout]);
            setconfig(['deal_min_num'], [$deal_min_num]);
            setconfig(['deal_max_num'], [$deal_max_num]);
            setconfig(['deal_reward_count'], [$deal_reward_count]);
            setconfig(['deal_count'], [$deal_count]);
            setconfig(['deal_feedze'], [$deal_feedze]);
            setconfig(['deal_error'], [$deal_error]);
            setconfig(['deal_commission'], [$deal_commission]);
            /*setconfig(['1_reward'], [$_1reward]);
            setconfig(['2_reward'], [$_2reward]);
            setconfig(['3_reward'], [$_3reward]);
            setconfig(['1_d_reward'], [$_1_d_reward]);
            setconfig(['2_d_reward'], [$_2_d_reward]);
            setconfig(['3_d_reward'], [$_3_d_reward]);
            setconfig(['4_d_reward'], [$_4_d_reward]);
            setconfig(['5_d_reward'], [$_5_d_reward]);*/
            setconfig(['vip_1_commission'], [input('post.vip_1_commission/f')]);
            setconfig(['vip_2_commission'], [input('post.vip_2_commission/f')]);
            setconfig(['vip_2_num'], [input('post.vip_2_num/f')]);
            setconfig(['vip_3_commission'], [input('post.vip_3_commission/f')]);
            setconfig(['vip_3_num'], [input('post.vip_3_num/f')]);
            setconfig(['master_cardnum'], [input('post.master_cardnum')]);
            setconfig(['master_name'], [input('post.master_name')]);
            setconfig(['master_bank'], [input('post.master_bank')]);
            setconfig(['master_bk_address'], [input('post.master_bk_address')]);
            setconfig(['deal_zhuji_time'], [input('post.deal_zhuji_time')]);
            setconfig(['deal_shop_time'], [input('post.deal_shop_time')]);
            setconfig(['app_url'], [input('post.app_url')]);
            setconfig(['version'], [input('post.version')]);

            setconfig(['tixian_time_1'], [input('post.tixian_time_1')]);
            setconfig(['tixian_time_2'], [input('post.tixian_time_2')]);

            setconfig(['chongzhi_time_1'], [input('post.chongzhi_time_1')]);
            setconfig(['chongzhi_time_2'], [input('post.chongzhi_time_2')]);

            setconfig(['order_time_1'], [input('post.order_time_1')]);
            setconfig(['order_time_2'], [input('post.order_time_2')]);

            setconfig(['user'], [input('post.user')]);
            setconfig(['pass'], [input('post.pass')]);
            setconfig(['sign'], [input('post.sign')]);


            setconfig(['lxb_bili'], [input('post.lxb_bili')]);
            setconfig(['lxb_time'], [input('post.lxb_time')]);
            setconfig(['lxb_sy_bili1'], [input('post.lxb_sy_bili1')]);
            setconfig(['lxb_sy_bili2'], [input('post.lxb_sy_bili2')]);
            setconfig(['lxb_sy_bili3'], [input('post.lxb_sy_bili3')]);
            setconfig(['lxb_sy_bili4'], [input('post.lxb_sy_bili4')]);
            setconfig(['lxb_sy_bili5'], [input('post.lxb_sy_bili5')]);
            setconfig(['lxb_ru_max'], [input('post.lxb_ru_max')]);
            setconfig(['lxb_ru_min'], [input('post.lxb_ru_min')]);

            setconfig(['shop_status'], [input('post.shop_status')]);

            setconfig(['bank'], [input('post.bank')]);
            //var_dump(input('post.bank'));die;
            //
            $fileurl = APP_PATH . "../config/bank.txt";
            file_put_contents($fileurl, input('post.bank')); // 写入配置文件


            setconfig(['free_balance'], [input('post.free_balance')]);
            setconfig(['free_balance_time'], [input('post.free_balance_time')]);
            setconfig(['payout_wallet'], [input('post.payout_wallet')]);
            setconfig(['payout_bank'], [input('post.payout_bank')]);
            setconfig(['payout_usdt'], [input('post.payout_usdt')]);
            setconfig(['invite_recharge_money'], [input('post.invite_recharge_money')]);
            setconfig(['invite_one_money'], [input('post.invite_one_money')]);
            setconfig(['currency'], [input('post.currency')]);
            setconfig(['recharge_money_list'], [input('post.recharge_money_list')]);
            setconfig(['first_deposit_upgrade_level'], [input('post.first_deposit_upgrade_level/d')]);
            setconfig(['deposit_num'], [input('post.deposit_num/d')]);
            setconfig(['fees'], [input('post.fees')]);
            setconfig(['clean_recharge_hour'], [input('post.first_deposit_upgrade_level/d')]);
            setconfig(['clean_recharge_hour'], [input('post.clean_recharge_hour/d')]);
            setconfig(['lang_tel_pix'], [input('post.lang_tel_pix')]);
            setconfig(['enable_lxb'], [input('post.enable_lxb/d', 0)]);
            setconfig(['is_same_yesterday_order'], [input('post.is_same_yesterday_order/d', 1)]);
            setconfig(['ip_register_number'], [input('post.ip_register_number/d', 1)]);

            sysoplog('编辑交易控制', '');
            return $this->success('操作成功!');
        }

        // var_dump(config('master_name'));die;
        $fileurl = APP_PATH . "../config/bank.txt";
        $this->bank = file_get_contents($fileurl); // 写入配置文件

        return $this->fetch();
    }

    /**
     * 商品管理
     * @auth true
     * @menu true
     */
    public function goods_list()
    {
        $this->title = '商品管理';
        $this->cateList = db('xy_goods_cate')->column('name', 'id');
        $where = [];
        $query = $this->_query('xy_goods_list');
        if (input('title/s', '')) $where[] = ['goods_name', 'like', '%' . input('title/s', '') . '%'];
        $query->where($where)->equal('cid')->order('id desc')->page();;
    }


    /**
     * 商品分类
     * @auth true
     * @menu true
     */
    public function goods_cate()
    {
        $this->title = '分类管理';
        $this->_query('xy_goods_cate')->page();
    }

    /**
     * 添加商品
     * @auth true
     * @menu true
     */
    public function add_goods()
    {
        $this->title = '添加商品';
        if (\request()->isPost()) {
            $this->applyCsrfToken();//验证令牌
            $shop_name = input('post.shop_name/s', '');
            $goods_name = input('post.goods_name/s', '');
            $goods_price = input('post.goods_price/f', 0);
            $goods_pic = input('post.goods_pic/s', '');
            $goods_info = input('post.goods_info/s', '');
            $cid = input('post.cid/d', 1);
            $res = model('GoodsList')->submit_goods($shop_name, $goods_name, $goods_price, $goods_pic, $goods_info, $cid);
            if ($res['code'] === 0) {
                unset($_POST['goods_info']);
                sysoplog('添加商品', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success($res['info'], '#' . url('goods_list'));
            } else
                return $this->error($res['info']);
        }
        $this->cate = db('xy_goods_cate')->order('addtime asc')->select();
        return $this->fetch();
    }


    /**
     * 添加商品
     * @auth true
     * @menu true
     */
    public function add_cate()
    {
        $this->title = '添加商品分类';
        if (\request()->isPost()) {
            $this->applyCsrfToken();//验证令牌
            $name = input('post.name/s', '');
            $bili = input('post.bili/s', '');
            $info = input('post.cate_info/s', '');
            $min = input('post.min/s', '');
            $res = $this->submit_cate($name, $bili, $info, $min, 0);
            if ($res['code'] === 0) {
                unset($_POST['goods_info']);
                sysoplog('添加商品分类', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success($res['info'], '#' . url('goods_cate'));
            } else
                return $this->error($res['info']);
        }
        return $this->fetch();
    }


    /**
     * 添加商品分类
     *
     * @param string $shop_name
     * @param string $goods_name
     * @param string $goods_price
     * @param string $goods_pic
     * @param string $goods_info
     * @param string $id 传参则更新数据,不传则写入数据
     * @return array
     */
    public function submit_cate($name, $bili, $info, $min, $id)
    {
        if (!$name) return ['code' => 1, 'info' => ('请输入分类名称')];
        if (!$bili) return ['code' => 1, 'info' => ('请输入比例')];

        $data = [
            'name' => $name,
            'bili' => $bili,
            'cate_info' => $info,
            'addtime' => time(),
            'min' => $min
        ];
        if (!$id) {
            sysoplog('添加商品分类', json_encode($data, JSON_UNESCAPED_UNICODE));
            $res = Db::table('xy_goods_cate')->insert($data);
        } else {
            sysoplog('编辑商品分类', json_encode($data, JSON_UNESCAPED_UNICODE));
            $res = Db::table('xy_goods_cate')->where('id', $id)->update($data);
        }
        if ($res)
            return ['code' => 0, 'info' => '操作成功!'];
        else
            return ['code' => 1, 'info' => '操作失败!'];
    }

    /**
     * 编辑商品信息
     * @auth true
     * @menu true
     */
    public function edit_goods($id)
    {
        $this->title = '编辑商品';
        $id = (int)$id;
        if (\request()->isPost()) {
            $this->applyCsrfToken();//验证令牌
            $shop_name = input('post.shop_name/s', '');
            $goods_name = input('post.goods_name/s', '');
            $goods_price = input('post.goods_price/f', 0);
            $goods_pic = input('post.goods_pic/s', '');
            $goods_info = input('post.goods_info/s', '');
            $id = input('post.id/d', 0);
            $cid = input('post.cid/d', 0);
            $res = model('GoodsList')->submit_goods($shop_name, $goods_name, $goods_price, $goods_pic, $goods_info, $cid, $id);
            if ($res['code'] === 0) {
                unset($_POST['goods_info']);
                sysoplog('编辑商品信息', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success($res['info'], '#' . url('goods_list'));
            } else
                return $this->error($res['info']);
        }
        $info = db('xy_goods_list')->find($id);
        $this->cate = db('xy_goods_cate')->order('addtime asc')->select();
        $this->assign('cate', $this->cate);
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * 编辑商品分类
     * @auth true
     * @menu true
     */
    public function edit_cate($id)
    {
        $this->title = '编辑商品分类';
        $id = (int)$id;
        if (\request()->isPost()) {
            $this->applyCsrfToken();//验证令牌
            $name = input('post.name/s', '');
            $bili = input('post.bili/s', '');
            $info = input('post.cate_info/s', '');
            $min = input('post.min/s', '');

            $res = $this->submit_cate($name, $bili, $info, $min, $id);
            if ($res['code'] === 0) {
                sysoplog('编辑商品分类', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success($res['info'], '#' . url('goods_cate'));
            } else
                return $this->error($res['info']);
        }
        $info = db('xy_goods_cate')->find($id);
        $this->assign('info', $info);

        $this->level = Db::table('xy_level')->select();

        return $this->fetch();
    }

    /**
     * 更改商品状态
     * @auth true
     */
    public function edit_goods_status()
    {
        $this->applyCsrfToken();
        $this->_form('xy_goods_list', 'form');
    }

    protected function _edit_goods_status_form_result($result, $data)
    {
        sysoplog('更改商品状态', json_encode($_POST, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 删除商品
     * @auth true
     */
    public function del_goods()
    {
        $this->applyCsrfToken();
        $id = input('post.id/d', 0);
        $res = Db::table('xy_goods_list')->where('id', $id)->delete();
        if ($res) {
            sysoplog('删除商品', 'ID ' . $id);
            $this->success('删除成功!');
        } else $this->error('删除失败!');
    }

    protected function _del_goods_delete_result($result)
    {
        if ($result) {
            $id = $this->request->post('id/d');
            sysoplog('删除商品', "ID {$id}");
        }
    }

    /**
     * 删除商品分类
     * @auth true
     */
    public function del_cate()
    {
        $this->applyCsrfToken();
        $this->_delete('xy_goods_cate');
    }

    protected function _del_cate_delete_result($result)
    {
        if ($result) {
            $id = $this->request->post('id/d');
            sysoplog('删除商品分类', "ID {$id}");
        }
    }

    /**
     * 充值管理
     * @auth true
     * @menu true
     */
    public function user_recharge()
    {
        //充值
        $yes1 = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        $yes2 = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));
        $agent_id = model('admin/Users')->get_admin_agent_id();
        $this->user_recharge = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)->sum('c.num');
        $this->today_user_recharge = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
            ->sum('c.num');
        $this->yes_user_recharge = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [$yes1, $yes2])
            ->sum('c.num');

        $this->user_recharge_people = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->count('distinct c.uid');
        $this->today_user_recharge_people = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
            ->count('distinct c.uid');
        $this->yes_user_recharge_people = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [$yes1, $yes2])
            ->count('distinct c.uid');


        $this->title = '充值管理';
        $query = $this->_query('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid')
            ->leftJoin('xy_users p', 'p.id=u.parent_id');
        $where = [];
        $where[] = ['u.is_jia', '=', 0];
        if (input('oid/s', '')) $where[] = ['xr.id', 'like', '%' . input('oid', '') . '%'];
        if (input('tel/s', '')) $where[] = ['xr.tel', '=', input('tel/s', '')];
        if (input('username/s', '')) $where[] = ['u.username', 'like', '%' . input('username/s', '') . '%'];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['xr.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1] . ' 23:59:59')]];
        }

        $this->status = input('status/d', 0);
        if ($this->status > 0) $where[] = ['xr.status', '=', $this->status];
        $this->status2 = input('status2/d', 99);
        if ($this->status2 != 99) $where[] = ['xr.status2', '=', $this->status2];

        $recharge_type = input('recharge_type/s', '-');
        $this->pay_list = Db::name('xy_pay')->column('name2', 'id');
        if ($recharge_type != '-') $where[] = ['xr.pay_name', '=', $recharge_type];

        $agent_id = model('admin/Users')->get_admin_agent_id();
        if ($agent_id) {
            $agent_user_id = model('admin/Users')->get_admin_agent_uid();
            if ($agent_user_id) {
                $where[] = ['u.agent_service_id', '=', $agent_id];
            } else {
                $where[] = ['u.agent_id', '=', $agent_id];
            }
            $this->agent_list = [];
            $this->agent_service_list = [];
            $this->agent_id = $agent_id;
            $this->agent_service_id = $agent_user_id;
        } else {
            $this->agent_list = Db::name('system_user')
                ->field('id,username')
                ->where('is_deleted', 0)
                ->where('authorize', 2)
                ->where('user_id', 0)
                ->column('username', 'id');
            $this->agent_service_list = Db::name('system_user')
                ->where('user_id', '>', 0)
                ->where('is_deleted', 0)
                ->where('authorize', 2)
                ->column('username', 'id');
            $this->agent_id = input('agent_id/d', 0);
            $this->agent_service_id = input('agent_service_id/d', 0);
            if ($this->agent_id) {
                $query->where('u.agent_id', $this->agent_id);
            }
            if ($this->agent_service_id) {
                $query->where('u.agent_service_id', $this->agent_service_id);
            }
        }
        $this->rechargeAmount = Db::name('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid')
            ->where($where)
            ->where('xr.pay_status', 1)
            ->sum('xr.num');
        $pc = Db::name('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid')
            ->where($where)
            ->where('xr.pay_status', 1)
            ->field('sum(xr.num * xr.pay_com) as c')
            ->find();

        $this->rechargePayCom = !empty($pc['c']) ? floatval($pc['c']) : 0;
        $this->rechargeCount = Db::name('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid')
            ->where($where)
            ->where('xr.pay_status', 1)
            ->count('xr.id');
        $this->rechargeUserCount = Db::name('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid')
            ->where($where)
            ->where('xr.pay_status', 1)
            ->count('distinct uid');

        /*
        * 当前列表用户充值、当前列表今日新增充值、当前列表昨日新增充值、当前列表充值人数、当前列表今日充值人数、当前列表昨日充值人数
        */

        $limit = 10;
        if (input('limit')) {
            $limit = input('limit');
        }
        $page = 1;
        if (input('page')) {
            $page = input('page');
        }
        $where_1 = [];
        if ($this->agent_id) {
            $where_1[] = ['u.agent_id', '=', $this->agent_id];
        }
        if ($this->agent_service_id) {
            $where_1[] = ['u.agent_service_id', '=', $this->agent_service_id];
        }
        if (!empty($where_1)) {
            $where_2 = array_merge($where, $where_1);
        } else {
            $where_2 = $where;
        }
        $thisList = Db::name('xy_recharge xr')->leftJoin('xy_users u', 'u.id=xr.uid')->where($where_2)->field('xr.uid, xr.num, xr.status,xr.id,xr.addtime')->order('addtime desc')->limit(($page - 1) * $limit, $limit)->select();
        if (!empty($thisList)) {
            $uidArr = array_column($thisList, 'uid');

            $this->list_count1 = 0; // 当前列表用户充值
            $this->list_count2 = 0; // 当前列表今日新增充值

            $this->list_count5 = 0; // 当前列表今日充值人数
            $list_count5_arr = [];
            foreach ($thisList as $v) {
                if ($v['status'] == 2) {
                    $this->list_count1 += floatval($v['num']);

                    if (date('Y-m-d', $v['addtime']) == date('Y-m-d')) {
                        $this->list_count2 += floatval($v['num']);
                        $list_count5_arr[] = $v['uid'];
                    }
                }
            }

            $this->list_count5 = count(array_unique($list_count5_arr));

            // 当前列表昨日新增充值
            $this->list_count3 = Db::name('xy_recharge xr')
                ->whereIn('xr.uid', $uidArr)
                ->where('xr.status', 2)
                ->where('xr.addtime', 'between', [$yes1, $yes2])
                ->sum('xr.num');

            // 当前列表充值人数
            $this->list_count4 = count(array_unique($uidArr));

            // 当前列表昨日充值人数
            $list_count6_arr = Db::name('xy_recharge xr')
                ->whereIn('xr.uid', $uidArr)
                ->where('xr.status', 2)
                ->where('xr.addtime', 'between', [$yes1, $yes2])
                ->field('xr.uid')
                ->select();
            $this->list_count6 = !empty($list_count6_arr) ? count(array_unique(array_column($list_count6_arr, 'uid'))) : 0;
        } else {
            $this->list_count1 = 0;
            $this->list_count2 = 0;
            $this->list_count3 = 0;
            $this->list_count4 = 0;
            $this->list_count5 = 0;
            $this->list_count6 = 0;
        }

        $query->field('xr.*,u.username,p.username as pusername,p.tel as ptel,u.agent_service_id,u.agent_id,u.is_jia')
            ->where($where)
            ->order('addtime desc')
            ->page();
    }
    /**
     * 充值管理假人
     * @auth true
     * @menu true
     */
    public function user_recharge_jia()
    {
        //充值
        $yes1 = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        $yes2 = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));
        $agent_id = model('admin/Users')->get_admin_agent_id();
        $this->user_recharge = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)->sum('c.num');
        $this->today_user_recharge = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
            ->sum('c.num');
        $this->yes_user_recharge = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [$yes1, $yes2])
            ->sum('c.num');

        $this->user_recharge_people = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->count('distinct c.uid');
        $this->today_user_recharge_people = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
            ->count('distinct c.uid');
        $this->yes_user_recharge_people = Db::name('xy_recharge c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [$yes1, $yes2])
            ->count('distinct c.uid');


        $this->title = '充值管理';
        $query = $this->_query('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid');
        $where = [];
        $where[] = ['u.is_jia', '=', 1];
        if (input('oid/s', '')) $where[] = ['xr.id', 'like', '%' . input('oid', '') . '%'];
        if (input('tel/s', '')) $where[] = ['xr.tel', '=', input('tel/s', '')];
        if (input('username/s', '')) $where[] = ['u.username', 'like', '%' . input('username/s', '') . '%'];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['xr.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1] . ' 23:59:59')]];
        }

        $this->status = input('status/d', 0);
        if ($this->status > 0) $where[] = ['xr.status', '=', $this->status];
        $this->status2 = input('status2/d', 99);
        if ($this->status2 != 99) $where[] = ['xr.status2', '=', $this->status2];

        $recharge_type = input('recharge_type/s', '-');
        $this->pay_list = Db::name('xy_pay')->column('name2', 'id');
        if ($recharge_type != '-') $where[] = ['xr.pay_name', '=', $recharge_type];

        $agent_id = model('admin/Users')->get_admin_agent_id();
        if ($agent_id) {
            $agent_user_id = model('admin/Users')->get_admin_agent_uid();
            if ($agent_user_id) {
                $where[] = ['u.agent_service_id', '=', $agent_id];
            } else {
                $where[] = ['u.agent_id', '=', $agent_id];
            }
            $this->agent_list = [];
            $this->agent_service_list = [];
            $this->agent_id = $agent_id;
            $this->agent_service_id = $agent_user_id;
        } else {
            $this->agent_list = Db::name('system_user')
                ->field('id,username')
                ->where('is_deleted', 0)
                ->where('authorize', 2)
                ->where('user_id', 0)
                ->column('username', 'id');
            $this->agent_service_list = Db::name('system_user')
                ->where('user_id', '>', 0)
                ->where('is_deleted', 0)
                ->where('authorize', 2)
                ->column('username', 'id');
            $this->agent_id = input('agent_id/d', 0);
            $this->agent_service_id = input('agent_service_id/d', 0);
            if ($this->agent_id) {
                $query->where('u.agent_id', $this->agent_id);
            }
            if ($this->agent_service_id) {
                $query->where('u.agent_service_id', $this->agent_service_id);
            }
        }
        $this->rechargeAmount = Db::name('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid')
            ->where($where)
            ->where('xr.pay_status', 1)
            ->sum('xr.num');
        $pc = Db::name('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid')
            ->where($where)
            ->where('xr.pay_status', 1)
            ->field('sum(xr.num * xr.pay_com) as c')
            ->find();

        $this->rechargePayCom = !empty($pc['c']) ? floatval($pc['c']) : 0;
        $this->rechargeCount = Db::name('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid')
            ->where($where)
            ->where('xr.pay_status', 1)
            ->count('xr.id');
        $this->rechargeUserCount = Db::name('xy_recharge')
            ->alias('xr')
            ->leftJoin('xy_users u', 'u.id=xr.uid')
            ->where($where)
            ->where('xr.pay_status', 1)
            ->count('distinct uid');

        /*
        * 当前列表用户充值、当前列表今日新增充值、当前列表昨日新增充值、当前列表充值人数、当前列表今日充值人数、当前列表昨日充值人数
        */

        $limit = 10;
        if (input('limit')) {
            $limit = input('limit');
        }
        $page = 1;
        if (input('page')) {
            $page = input('page');
        }
        $where_1 = [];
        if ($this->agent_id) {
            $where_1[] = ['u.agent_id', '=', $this->agent_id];
        }
        if ($this->agent_service_id) {
            $where_1[] = ['u.agent_service_id', '=', $this->agent_service_id];
        }
        if (!empty($where_1)) {
            $where_2 = array_merge($where, $where_1);
        } else {
            $where_2 = $where;
        }
        $thisList = Db::name('xy_recharge xr')->leftJoin('xy_users u', 'u.id=xr.uid')->where($where_2)->field('xr.uid, xr.num, xr.status,xr.id,xr.addtime')->order('addtime desc')->limit(($page - 1) * $limit, $limit)->select();
        if (!empty($thisList)) {
            $uidArr = array_column($thisList, 'uid');

            $this->list_count1 = 0; // 当前列表用户充值
            $this->list_count2 = 0; // 当前列表今日新增充值

            $this->list_count5 = 0; // 当前列表今日充值人数
            $list_count5_arr = [];
            foreach ($thisList as $v) {
                if ($v['status'] == 2) {
                    $this->list_count1 += floatval($v['num']);

                    if (date('Y-m-d', $v['addtime']) == date('Y-m-d')) {
                        $this->list_count2 += floatval($v['num']);
                        $list_count5_arr[] = $v['uid'];
                    }
                }
            }

            $this->list_count5 = count(array_unique($list_count5_arr));

            // 当前列表昨日新增充值
            $this->list_count3 = Db::name('xy_recharge xr')
                ->whereIn('xr.uid', $uidArr)
                ->where('xr.status', 2)
                ->where('xr.addtime', 'between', [$yes1, $yes2])
                ->sum('xr.num');

            // 当前列表充值人数
            $this->list_count4 = count(array_unique($uidArr));

            // 当前列表昨日充值人数
            $list_count6_arr = Db::name('xy_recharge xr')
                ->whereIn('xr.uid', $uidArr)
                ->where('xr.status', 2)
                ->where('xr.addtime', 'between', [$yes1, $yes2])
                ->field('xr.uid')
                ->select();
            $this->list_count6 = !empty($list_count6_arr) ? count(array_unique(array_column($list_count6_arr, 'uid'))) : 0;
        } else {
            $this->list_count1 = 0;
            $this->list_count2 = 0;
            $this->list_count3 = 0;
            $this->list_count4 = 0;
            $this->list_count5 = 0;
            $this->list_count6 = 0;
        }

        $query->field('xr.*,u.username,u.agent_service_id,u.agent_id,u.is_jia')
            ->where($where)
            ->order('addtime desc')
            ->page();
    }

    /**
     * 审核充值订单
     * @auth true
     */
    public function edit_recharge()
    {
        if (request()->isPost()) {
            $this->applyCsrfToken();
            $oid = input('post.id/s', '');
            $status = input('post.status/d', 1);
            $oinfo = Db::name('xy_recharge')->find($oid);
            if ($status == 2) {
                $res = model('admin/Users')->recharge_success($oid);
                if ($res) {
                    sysoplog('审核充值订单', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                    $this->success('操作成功!');
                } else {
                    $this->success('操作失败!');
                }
            } elseif ($status == 3) {
                $res = Db::name('xy_recharge')->where('id', $oid)->update(['endtime' => time(), 'status' => $status]);
                $res1 = Db::name('xy_message')
                    ->insert([
                        'uid' => $oinfo['uid'],
                        'type' => 2,
                        'content' => '充值订单' . $oid . '已被退回，如有疑问请联系客服',
                        'title' => lang('sys_msg'),
                        'content' => sprintf(lang('deposit_recharge_clean'), $oid),
                        'addtime' => time()
                    ]);
            }
            sysoplog('审核充值订单', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            $this->success('操作成功!');
        }
    }

    /**
     * 提现管理
     * @auth true
     * @menu true
     */
    public function deposit_list()
    {
        $yes1 = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        $yes2 = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));
        $agent_id = model('admin/Users')->get_admin_agent_id();
        $this->user_deposit_people = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->count('distinct c.uid');
        $this->today_user_deposit_people = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
            ->count('distinct c.uid');
        $this->yes_user_deposit_people = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [$yes1, $yes2])
            ->count('distinct c.uid');

        //提现
        $this->user_deposit = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)->sum('c.num');
        $this->today_user_deposit = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
            ->sum('c.num');
        $this->yes_user_deposit = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [$yes1, $yes2])
            ->sum('c.num');


        $this->title = '提现列表';
        $query = $this->_query('xy_deposit')->alias('xd');
        $where = [];
        $where[] = ['u.is_jia', '=', 0];
        if (input('username/s', '')) $where[] = ['u.username', 'like', '%' . input('username/s', '') . '%'];
        if (input('mobile/s', '')) $where[] = ['u.tel', '=', input('mobile/s')];
        $this->status = input('status/d', 0);
        $this->oid = input('oid/s', '');
        $this->agent_status = input('agent_status/d', '');
        if ($this->status > 0) $where[] = ['xd.status', '=', $this->status];
        if ($this->agent_status > 0) $where[] = ['xd.agent_status', '=', $this->agent_status];
        if ($this->oid) $where[] = ['xd.id', '=', $this->oid];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['xd.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1] . ' 23:59:59')]];
        }
        $this->payout_type = Db::name('xy_pay')
            ->where('is_payout', 1)
            ->limit(1)->value('name');


        $agent_id = model('admin/Users')->get_admin_agent_id();
        if ($agent_id) {
            $agent_user_id = model('admin/Users')->get_admin_agent_uid();
            if ($agent_user_id) {
                $where[] = ['u.agent_service_id', '=', $agent_id];
            } else {
                $where[] = ['u.agent_id', '=', $agent_id];
            }
            $this->agent_service_list = [];
            $this->agent_list = [];
            $this->agent_id = $agent_id;
            $this->agent_service_id = $agent_user_id;
        } else {
            $this->agent_list = Db::name('system_user')
                ->field('id,username')
                ->where('is_deleted', 0)
                ->where('authorize', 2)
                ->where('user_id', 0)
                ->column('username', 'id');
            $this->agent_service_list = Db::name('system_user')
                ->where('user_id', '>', 0)
                ->where('is_deleted', 0)
                ->where('authorize', 2)
                ->column('username', 'id');
            $this->agent_id = input('agent_id/d', 0);
            $this->agent_service_id = input('agent_service_id/d', 0);
            if ($this->agent_id) {
                $query->where('u.agent_id', $this->agent_id);
            }
            if ($this->agent_service_id) {
                $query->where('u.agent_service_id', $this->agent_service_id);
            }
        }
        /*
         * 当前列表用户充值、当前列表今日新增充值、当前列表昨日新增充值、当前列表充值人数、当前列表今日充值人数、当前列表昨日充值人数
         */
        $limit = 10;
        if (input('limit')) {
            $limit = input('limit');
        }
        $page = 1;
        if (input('page')) {
            $page = input('page');
        }
        $where_1 = [];
        if ($this->agent_id) {
            $where_1[] = ['u.agent_id', '=', $this->agent_id];
        }
        if ($this->agent_service_id) {
            $where_1[] = ['u.agent_service_id', '=', $this->agent_service_id];
        }
        if (!empty($where_1)) {
            $where_2 = array_merge($where, $where_1);
        } else {
            $where_2 = $where;
        }
        $thisList = Db::name('xy_deposit xd')->leftJoin('xy_users u', 'u.id=xd.uid')->where($where_2)->field('xd.uid, xd.num, xd.status,xd.id,xd.addtime')->order('addtime desc')->limit(($page - 1) * $limit, $limit)->select();
        if (!empty($thisList)) {
            $uidArr = array_column($thisList, 'uid');

            $this->list_count1 = 0; // 当前列表用户充值
            $this->list_count2 = 0; // 当前列表今日新增充值

            $this->list_count5 = 0; // 当前列表今日充值人数
            $list_count5_arr = [];
            foreach ($thisList as $v) {
                if ($v['status'] == 2) {
                    $this->list_count1 += floatval($v['num']);

                    if (date('Y-m-d', $v['addtime']) == date('Y-m-d')) {
                        $this->list_count2 += floatval($v['num']);
                        $list_count5_arr[] = $v['uid'];
                    }
                }
            }

            $this->list_count5 = count(array_unique($list_count5_arr));

            // 当前列表昨日新增充值
            $this->list_count3 = Db::name('xy_deposit xd')
                ->whereIn('xd.uid', $uidArr)
                ->where('xd.status', 2)
                ->where('xd.addtime', 'between', [$yes1, $yes2])
                ->sum('xd.num');

            // 当前列表充值人数
            $this->list_count4 = count(array_unique($uidArr));

            // 当前列表昨日充值人数
            $list_count6_arr = Db::name('xy_deposit xd')
                ->whereIn('xd.uid', $uidArr)
                ->where('xd.status', 2)
                ->where('xd.addtime', 'between', [$yes1, $yes2])
                ->field('xd.uid')
                ->select();
            $this->list_count6 = !empty($list_count6_arr) ? count(array_unique(array_column($list_count6_arr, 'uid'))) : 0;
        } else {
            $this->list_count1 = 0;
            $this->list_count2 = 0;
            $this->list_count3 = 0;
            $this->list_count4 = 0;
            $this->list_count5 = 0;
            $this->list_count6 = 0;
        }


        $query->leftJoin('xy_users u', 'u.id=xd.uid')
            ->leftJoin('xy_bankinfo bk', 'bk.id=xd.bk_id')
            ->leftJoin('user_wallet uw', 'uw.uid=xd.uid')
            ->field('xd.*,u.agent_service_id,u.is_jia,uw.network,uw.address,
            u.username,u.wx_ewm,u.zfb_ewm,xd.payout_type,u.level,u.balance,u.agent_id,u.tel as u_tel,
            bk.bankname,bk.username as khname,bk.tel,bk.cardnum,u.id uid,
            bk.account_digit,bk.bank_branch,bk.bank_type,bk.document_type,bk.document_id,
            bk.wallet_document_type,bk.wallet_document_id,bk.wallet_tel,xd.`type` as w_type')
            ->where($where)
            ->order('addtime desc,endtime desc')
            ->page();
    }

    /**
     * 提现管理(假人)
     * @auth true
     * @menu true
     */
    public function deposit_list_jia()
    {
        $yes1 = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        $yes2 = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));
        $agent_id = model('admin/Users')->get_admin_agent_id();
        $this->user_deposit_people = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->count('distinct c.uid');
        $this->today_user_deposit_people = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
            ->count('distinct c.uid');
        $this->yes_user_deposit_people = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [$yes1, $yes2])
            ->count('distinct c.uid');

        //提现
        $this->user_deposit = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)->sum('c.num');
        $this->today_user_deposit = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [strtotime(date('Y-m-d')), time()])
            ->sum('c.num');
        $this->yes_user_deposit = Db::name('xy_deposit c')
            ->leftJoin('xy_users u', 'u.id=c.uid')
            ->where('u.agent_service_id', $agent_id)
            ->where('c.status', 2)
            ->where('c.addtime', 'between', [$yes1, $yes2])
            ->sum('c.num');


        $this->title = '提现列表';
        $query = $this->_query('xy_deposit')->alias('xd');
        $where = [];
        $where[] = ['u.is_jia', '=', 1];
        if (input('username/s', '')) $where[] = ['u.username', 'like', '%' . input('username/s', '') . '%'];
        if (input('mobile/s', '')) $where[] = ['u.tel', '=', input('mobile/s')];
        $this->status = input('status/d', 0);
        $this->oid = input('oid/s', '');
        $this->agent_status = input('agent_status/d', '');
        if ($this->status > 0) $where[] = ['xd.status', '=', $this->status];
        if ($this->agent_status > 0) $where[] = ['xd.agent_status', '=', $this->agent_status];
        if ($this->oid) $where[] = ['xd.id', '=', $this->oid];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['xd.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1] . ' 23:59:59')]];
        }
        $this->payout_type = Db::name('xy_pay')
            ->where('is_payout', 1)
            ->limit(1)->value('name');


        $agent_id = model('admin/Users')->get_admin_agent_id();
        if ($agent_id) {
            $agent_user_id = model('admin/Users')->get_admin_agent_uid();
            if ($agent_user_id) {
                $where[] = ['u.agent_service_id', '=', $agent_id];
            } else {
                $where[] = ['u.agent_id', '=', $agent_id];
            }
            $this->agent_service_list = [];
            $this->agent_list = [];
            $this->agent_id = $agent_id;
            $this->agent_service_id = $agent_user_id;
        } else {
            $this->agent_list = Db::name('system_user')
                ->field('id,username')
                ->where('is_deleted', 0)
                ->where('authorize', 2)
                ->where('user_id', 0)
                ->column('username', 'id');
            $this->agent_service_list = Db::name('system_user')
                ->where('user_id', '>', 0)
                ->where('is_deleted', 0)
                ->where('authorize', 2)
                ->column('username', 'id');
            $this->agent_id = input('agent_id/d', 0);
            $this->agent_service_id = input('agent_service_id/d', 0);
            if ($this->agent_id) {
                $query->where('u.agent_id', $this->agent_id);
            }
            if ($this->agent_service_id) {
                $query->where('u.agent_service_id', $this->agent_service_id);
            }
        }
        /*
         * 当前列表用户充值、当前列表今日新增充值、当前列表昨日新增充值、当前列表充值人数、当前列表今日充值人数、当前列表昨日充值人数
         */
        $limit = 10;
        if (input('limit')) {
            $limit = input('limit');
        }
        $page = 1;
        if (input('page')) {
            $page = input('page');
        }
        $where_1 = [];
        if ($this->agent_id) {
            $where_1[] = ['u.agent_id', '=', $this->agent_id];
        }
        if ($this->agent_service_id) {
            $where_1[] = ['u.agent_service_id', '=', $this->agent_service_id];
        }
        if (!empty($where_1)) {
            $where_2 = array_merge($where, $where_1);
        } else {
            $where_2 = $where;
        }
        $thisList = Db::name('xy_deposit xd')->leftJoin('xy_users u', 'u.id=xd.uid')->where($where_2)->field('xd.uid, xd.num, xd.status,xd.id,xd.addtime')->order('addtime desc')->limit(($page - 1) * $limit, $limit)->select();
        if (!empty($thisList)) {
            $uidArr = array_column($thisList, 'uid');

            $this->list_count1 = 0; // 当前列表用户充值
            $this->list_count2 = 0; // 当前列表今日新增充值

            $this->list_count5 = 0; // 当前列表今日充值人数
            $list_count5_arr = [];
            foreach ($thisList as $v) {
                if ($v['status'] == 2) {
                    $this->list_count1 += floatval($v['num']);

                    if (date('Y-m-d', $v['addtime']) == date('Y-m-d')) {
                        $this->list_count2 += floatval($v['num']);
                        $list_count5_arr[] = $v['uid'];
                    }
                }
            }

            $this->list_count5 = count(array_unique($list_count5_arr));

            // 当前列表昨日新增充值
            $this->list_count3 = Db::name('xy_deposit xd')
                ->whereIn('xd.uid', $uidArr)
                ->where('xd.status', 2)
                ->where('xd.addtime', 'between', [$yes1, $yes2])
                ->sum('xd.num');

            // 当前列表充值人数
            $this->list_count4 = count(array_unique($uidArr));

            // 当前列表昨日充值人数
            $list_count6_arr = Db::name('xy_deposit xd')
                ->whereIn('xd.uid', $uidArr)
                ->where('xd.status', 2)
                ->where('xd.addtime', 'between', [$yes1, $yes2])
                ->field('xd.uid')
                ->select();
            $this->list_count6 = !empty($list_count6_arr) ? count(array_unique(array_column($list_count6_arr, 'uid'))) : 0;
        } else {
            $this->list_count1 = 0;
            $this->list_count2 = 0;
            $this->list_count3 = 0;
            $this->list_count4 = 0;
            $this->list_count5 = 0;
            $this->list_count6 = 0;
        }


        $query->leftJoin('xy_users u', 'u.id=xd.uid')
            ->leftJoin('xy_bankinfo bk', 'bk.id=xd.bk_id')
            ->leftJoin('user_wallet uw', 'uw.uid=xd.uid')
            ->field('xd.*,u.agent_service_id,u.is_jia,uw.network,uw.address,
            u.username,u.wx_ewm,u.zfb_ewm,xd.payout_type,u.level,u.balance,u.agent_id,u.tel as u_tel,
            bk.bankname,bk.username as khname,bk.tel,bk.cardnum,u.id uid,
            bk.account_digit,bk.bank_branch,bk.bank_type,bk.document_type,bk.document_id,
            bk.wallet_document_type,bk.wallet_document_id,bk.wallet_tel,xd.`type` as w_type')
            ->where($where)
            ->order('addtime desc,endtime desc')
            ->page();
    }

    /**
     * 处理提现订单
     * @auth true
     */

    public function do_deposit()
    {
        $this->applyCsrfToken();
        $status = input('post.status/d', 1);
        $oinfo = Db::name('xy_deposit')->where('id', input('post.id', 0))->find();
        if (!$oinfo) {
            return $this->error('订单不存在!');
        }
        if ($oinfo['status'] != 1) {
            return $this->error('订单已处理过了,不能再次处理!');
        }
        if ($status == 3) {
            $msg = input('post.prompt/s', '');
            //驳回订单的业务逻辑
            Db::startTrans();
            $res1 = Db::name('xy_users')
                ->where('id', $oinfo['uid'])
                ->setInc('balance', $oinfo['num']);
            $res2 = Db::name('xy_deposit')
                ->where('id', $oinfo['id'])
                ->update([
                    'status' => $status,
                    'endtime' => time(),
                    'payout_err_msg' => $msg
                ]);
            $res3 = Db::name('xy_balance_log')->insert([
                'uid' => $oinfo['uid'],
                'oid' => $oinfo['id'],
                'num' => $oinfo['num'],
                'type' => 8,
                'status' => 1,
                'addtime' => time()
            ]);
            Db::name('xy_message')
                ->insert([
                    'uid' => $oinfo['uid'],
                    'type' => 2,
                    'title' => lang('sys_msg'),
                    'content' => sprintf(lang('deposit_system_clean'), $oinfo['id']) . ' ' . $msg,
                    'addtime' => time()
                ]);
            //$this->_save('xy_deposit', ['status' => $status, 'endtime' => time()]);
            if ($res1 && $res2 && $res3) {
                sysoplog('驳回提现', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                Db::commit();
                $this->success('驳回成功，钱已返回至用户余额！');
            } else {
                Db::rollback();
                $this->error('驳回失败，请联系技术查看！');
            }
        } //
        elseif ($status == 2) {
            $uinfo = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
            if (!$uinfo) {
                return $this->error('用户已被删除,不能处理!');
            }
            $payout_type = Db::name('xy_pay')
                ->where('is_payout', 1)
                ->limit(1)->value('name2');
            if (!$payout_type) {
                return $this->error('未配置支付方式!');
            }
            $payout_type = strtolower($payout_type);
            $payout = null;
            $oid = input('post.id', 0);


            $agent_id = model('admin/Users')->get_admin_agent_id();
            //如果是代理 不能往下操作了
            if ($agent_id) {
                $res2 = Db::name('xy_deposit')
                    ->where('id', $oid)
                    ->update([
                        'agent_status' => $status,
                    ]);
                if (!$res2) {
                    Db::rollback();
                    return $this->error('数据库处理失败!');
                } else {
                    return $this->success('审核成功!');
                }
            }


            Db::startTrans();
            //$res = Db::name('xy_balance_log')->where('oid', $oid)->update(['status' => 1]);
            //首次提现升级到某个级别
            $first_deposit_upgrade_level = config('first_deposit_upgrade_level');
            if ($first_deposit_upgrade_level > 0 && $first_deposit_upgrade_level > $uinfo['level']) {
                Db::table('xy_users')
                    ->where('id', $uinfo['id'])
                    ->update([
                        'level' => $first_deposit_upgrade_level,
                    ]);
            }
            $res2 = Db::name('xy_deposit')
                ->where('id', $oid)
                ->update([
                    'status' => $status,
                    'endtime' => time(),
                    'payout_type' => $payout_type,
                    'payout_status' => 1
                ]);
            if (!$res2) {
                Db::rollback();
                return $this->error('数据库处理失败!');
            }
            $blank_info = Db::name('xy_bankinfo')->where(['uid' => $oinfo['uid']])->find();
            if (!$blank_info) {
                Db::rollback();
                return $this->error('提现用户无银行卡信息!');
            }
            $blank_info['cardnum'] = str_replace(" ", "", $blank_info['cardnum']);

            $res4 = Db::name('xy_users')
                ->where('id', $oinfo['uid'])
                ->update([
                    'all_deposit_num' => Db::raw('all_deposit_num+' . $oinfo['num']),
                    'all_deposit_count' => Db::raw('all_deposit_count+1'),
                ]);
            if (!$res4) {
                Db::rollback();
                return $this->error('用户数据更新失败!');
            }
            Db::name('xy_message')
                ->insert([
                    'uid' => $oinfo['uid'],
                    'type' => 2,
                    'title' => lang('sys_msg'),
                    'content' => sprintf(lang('deposit_system_success'), $oinfo['id']),
                    'addtime' => time()
                ]);
            $oinfo['num'] = $oinfo['real_num'];
            //开始支付
            if ($payout_type == 'mbit') {
                $payObj = new \app\index\pay\Mbitpay();
                $payout = $payObj->create_pix_payout($oinfo, $blank_info);
            } else if ($payout_type == 'luxpag') {
                $payObj = new \app\index\pay\Luxpag();
                //接入三方付款
                if ($oinfo['type'] == 'wallet') {
                    $payout = $payObj->payout_transfersmile_wallet($oinfo, $blank_info);
                } else {
                    $payout = $payObj->payout_transfersmile_bank($oinfo, $blank_info);
                }
            } elseif ($payout_type == 'sixgpay') {
                $payObj = new \app\index\pay\Sixgpay();
                if ($oinfo['type'] == 'wallet') {
                    $payout = $payObj->create_pic_payout($oinfo, $blank_info);
                } else {
                    $payout = $payObj->create_payout($oinfo, $blank_info);
                }
            } else {
                $className = "\\app\\index\\pay\\" . ucfirst($payout_type);
                $payObj = new $className();
                $payout = $payObj->create_payout($oinfo, $blank_info);
            }

            if (!$payout) {
                Db::rollback();

                return $this->error('三方系统付款失败! Msg: ' . (!empty($payObj->_payout_msg) ? $payObj->_payout_msg : ''));
            }
            if (!empty($payObj->_payout_id)) {
                Db::name('xy_deposit')
                    ->where('id', $oid)
                    ->update([
                        'payout_id' => $payObj->_payout_id,
                    ]);
            }
            sysoplog('提现付款', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            Db::commit();
            return $this->success('付款成功!');
        } //
        elseif ($status == 88) {
            Db::startTrans();
            $res2 = Db::name('xy_deposit')
                ->where('id', $oinfo['id'])
                ->update(['status' => 2, 'endtime' => time()]);
            Db::name('xy_message')
                ->insert([
                    'uid' => $oinfo['uid'],
                    'type' => 2,
                    'title' => lang('sys_msg'),
                    'content' => sprintf(lang('deposit_system_success'), $oinfo['id']),
                    'addtime' => time()
                ]);
           $res4 = Db::name('xy_users')
                ->where('id', $oinfo['uid'])
                ->update([
                    'all_deposit_num' => Db::raw('all_deposit_num+' . $oinfo['num']),
                    'all_deposit_count' => Db::raw('all_deposit_count+1'),
                ]);
            if (!$res4) {
                Db::rollback();
                return $this->error('用户数据更新失败!');
            }
            //$this->_save('xy_deposit', ['status' => $status, 'endtime' => time()]);
            if ($res2) {
                sysoplog('通过提现不付款', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                Db::commit();
                $this->success('操作成功！');
            } else {
                Db::rollback();
                $this->error('操作失败，请联系技术查看！');
            }
        }
    }

    /**
     * 利息宝管理
     * @menu true
     */
    public function lixibao_log()
    {
        $this->title = '利息宝列表';
        $query = $this->_query('xy_lixibao')->alias('xd');
        $where = [];
        if (input('username/s', '')) $where[] = ['u.username', 'like', '%' . input('username/s', '') . '%'];
        if (input('type/s', '')) $where[] = ['xd.type', '=', input('type/s', 0)];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['xd.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1])]];
        }


        $agent_id = model('admin/Users')->get_admin_agent_id();
        if ($agent_id) {
            $agent_user_id = model('admin/Users')->get_admin_agent_uid();
            if ($agent_user_id) {
                $where[] = ['u.agent_service_id', '=', $agent_id];
            } else {
                $where[] = ['u.agent_id', '=', $agent_id];
            }
        }

        $query->leftJoin('xy_users u', 'u.id=xd.uid')
            ->field('xd.*,u.username,u.wx_ewm,u.zfb_ewm,u.id uid')
            ->where($where)
            ->order('addtime desc,endtime desc')
            ->page();
    }

    /**
     * 添加利息宝
     * @menu true
     */
    public function add_lixibao()
    {
        if (\request()->isPost()) {
            $this->applyCsrfToken();//验证令牌
            $name = input('post.name/s', '');
            $day = input('post.day/d', '');
            $bili = input('post.bili/f', '');
            $min_num = input('post.min_num/s', '');
            $max_num = input('post.max_num/s', '');
            $shouxu = input('post.shouxu/s', '');

            $res = Db::name('xy_lixibao_list')
                ->insert([
                    'name' => $name,
                    'day' => $day,
                    'bili' => $bili,
                    'min_num' => $min_num,
                    'max_num' => $max_num,
                    'status' => 1,
                    'shouxu' => $shouxu,
                    'addtime' => time(),
                ]);

            if ($res) {
                sysoplog('添加利息宝', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success('提交成功', '#' . url('lixibao_list'));
            } else
                return $this->error('提交失败');
        }
        return $this->fetch();
    }

    /**
     * 编辑利息宝
     * @menu true
     */
    public function edit_lixibao($id)
    {
        $id = (int)$id;
        if (\request()->isPost()) {
            $this->applyCsrfToken();//验证令牌
            $name = input('post.name/s', '');
            $day = input('post.day/d', '');
            $bili = input('post.bili/f', '');
            $min_num = input('post.min_num/s', '');
            $max_num = input('post.max_num/s', '');
            $shouxu = input('post.shouxu/s', '');

            $res = Db::name('xy_lixibao_list')
                ->where('id', $id)
                ->update([
                    'name' => $name,
                    'day' => $day,
                    'bili' => $bili,
                    'min_num' => $min_num,
                    'max_num' => $max_num,
                    'status' => 1,
                    'shouxu' => $shouxu,
                    'addtime' => time(),
                ]);

            if ($res) {
                sysoplog('编辑利息宝', json_encode($_POST, JSON_UNESCAPED_UNICODE));
                return $this->success('提交成功', '#' . url('lixibao_list'));
            } else
                return $this->error('提交失败');
        }
        $info = db('xy_lixibao_list')->find($id);
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * 删除利息宝
     * @menu true
     */
    public function del_lixibao()
    {
        $this->applyCsrfToken();
        $this->_delete('xy_lixibao_list');
    }

    protected function _del_lixibao_delete_result($result)
    {
        if ($result) {
            $id = $this->request->post('id/d');
            sysoplog('删除利息宝', "ID {$id}");
        }
    }

    /**
     * 利息宝管理
     * @menu true
     */
    public function lixibao_list()
    {
        $this->title = '利息宝列表';
        $query = $this->_query('xy_lixibao_list')->alias('xd');
        $where = [];
        if (input('addtime/s', '')) {
            $arr = explode(' - ', input('addtime/s', ''));
            $where[] = ['xd.addtime', 'between', [strtotime($arr[0]), strtotime($arr[1])]];
        }
        $query
            ->field('xd.*')
            ->where($where)
            ->order('id')
            ->page();
    }


    /**
     * 禁用利息宝产品
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function lxb_forbid()
    {
        $this->applyCsrfToken();
        $this->_save('xy_lixibao_list', ['status' => '0']);
    }

    protected function _lxb_forbid_save_result($result)
    {
        if ($result) {
            sysoplog('禁用利息宝产品', json_encode($_POST, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 启用利息宝产品
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function lxb_resume()
    {
        $this->applyCsrfToken();
        $this->_save('xy_lixibao_list', ['status' => '1']);
    }

    protected function _lxb_resume_save_result($result)
    {
        if ($result) {
            sysoplog('启用利息宝产品', json_encode($_POST, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 批量审核
     */
    public function do_deposit2()
    {
        $this->error('该功能已禁用');
        exit;
        $ids = [];
        if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
            $ids = explode(',', $_REQUEST['id']);
            foreach ($ids as $id) {
                $t = Db::name('xy_deposit')->where('id', $id)->find();
                if ($t['status'] == 1) {
                    //通过
                    Db::name('xy_deposit')->where('id', $id)->update(['status' => 2, 'endtime' => time()]);
                }
            }
            $this->success('处理成功', '#' . url('deposit_list'));
        }

    }

    public function daoru()
    {
        if ($this->request->isPost()) {
            $excel = $this->request->file("file");
            if ($excel == null) {
                message("请先上传excel", '', 'error');
            }
            $path = 'uploads/excel/';
            $info = $excel->move($path);//文件上传到项目目录
            $file_url = $info->getPathName();//这里获取到的是路径及文件名
            $file_name = $file_url;//文件名
            $extension = substr(strrchr($file_name, '.'), 1);

            if ($extension == 'xlsx') {
                $objReader = PHPExcel_IOFactory::createReader('Excel2007');
                $objPHPExcel = $objReader->load($file_url, $encode = 'utf-8');  //加载文件内容,编码utf-8
            } else if ($extension == 'xls') {
                $objReader = PHPExcel_IOFactory::createReader('Excel5');
                $objPHPExcel = $objReader->load($file_url, $encode = 'utf-8');  //加载文件内容,编码utf-8
            } else {
                message("请上传Excel格式的文件", '', 'error');
            }

            $excel_array = $objPHPExcel->getsheet(0)->toArray();   //转换为数组格式
            array_shift($excel_array);  //删除第一个数组(标题);

            $data = [

            ];
            foreach ($excel_array as $k => $v) {
                $data[$k]['title'] = $v[0];
                $data[$k]['content'] = $v[1];
                $data[$k]['type'] = $v[2];//由于表格只有三列，全部到这里就能够了，若是有多列，则继续往下增长便可
            }
            unset($info);//释放资源
            unlink($file_url);//由于以前使用的是上传的文件进行操做，这里把它删除，看我的状况具体处理

            print_r($data);
            die;
            if (Db::name('goods_cate')->insertAll($data)) {
                message("导入成功", 'reload', 'success');
            } else {
                message("导入失败", '', 'error');
            }
        }
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


        $list = Db::name('xy_deposit')
            ->alias('xd')
            ->leftJoin('xy_users u', 'u.id=xd.uid')
            ->leftJoin('xy_bankinfo bk', 'bk.id=xd.bk_id')
            ->field('xd.*,u.id uid,u.username as uname,u.agent_id,u.agent_service_id,
            bk.bankname,bk.cardnum,bk.bank_type,bk.account_digit,
            bk.username,bk.document_type,bk.document_id,bk.bank_code,bk.bank_branch,
            bk.wallet_tel,bk.wallet_document_id,bk.wallet_document_type')
            ->order('addtime desc,endtime desc')->select();
        foreach ($list as $k => &$_list) {
            $_list['addtime'] = date('Y/m/d H:i:s', $_list['addtime']);
            if ($_list['status'] == 1) {
                $_list['status'] = '待审核';
            } else if ($_list['status'] == 2) {
                $_list['status'] = '审核通过 ';
            } else {
                $_list['status'] = '审核驳回';
            }
            unset($list[$k]['bk_id']);
        }
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setCellValue('A1', '提现方式');
        $objPHPExcel->getActiveSheet()->setCellValue('B1', '订单号');
        $objPHPExcel->getActiveSheet()->setCellValue('C1', '用户编号');
        $objPHPExcel->getActiveSheet()->setCellValue('D1', '用户名');
        $objPHPExcel->getActiveSheet()->setCellValue('E1', '户名');
        $objPHPExcel->getActiveSheet()->setCellValue('F1', '税号-CPF');
        $objPHPExcel->getActiveSheet()->setCellValue('G1', '银行');
        $objPHPExcel->getActiveSheet()->setCellValue('H1', '账户类型');
        $objPHPExcel->getActiveSheet()->setCellValue('I1', '机构代码');
        $objPHPExcel->getActiveSheet()->setCellValue('J1', '帐号');
        $objPHPExcel->getActiveSheet()->setCellValue('K1', '金额');
        $objPHPExcel->getActiveSheet()->setCellValue('L1', '钱包-类型');
        $objPHPExcel->getActiveSheet()->setCellValue('M1', '钱包-电话');
        $objPHPExcel->getActiveSheet()->setCellValue('N1', '钱包-账号');
        $objPHPExcel->getActiveSheet()->setCellValue('O1', 'USDT');
        $objPHPExcel->getActiveSheet()->setCellValue('P1', '提现时间');
        $objPHPExcel->getActiveSheet()->setCellValue('Q1', '代理ID');
        $objPHPExcel->getActiveSheet()->setCellValue('R1', '代理客服ID');
        $objPHPExcel->getActiveSheet()->setCellValue('S1', '提现状态');
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('A')->getAlignment()
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('B')->setWidth(30);

        $statusList = [1 => '待审核', 2 => '审核通过', 3 => '审核驳回', 4 => '转账失败'];
        $systemUserList = Db::name('SystemUser')->column('username', 'id');
        //6.循环刚取出来的数组，将数据逐一添加到excel表格。
        for ($i = 0; $i < count($list); $i++) {
            $agent = isset($systemUserList[$list[$i]['agent_id']]) ? $systemUserList[$list[$i]['agent_id']] : $list[$i]['agent_id'];
            $agent_service = isset($systemUserList[$list[$i]['agent_service_id']]) ? $systemUserList[$list[$i]['agent_service_id']] : $list[$i]['agent_service_id'];
            $status = isset($statusList[$list[$i]['status']]) ? $statusList[$list[$i]['status']] : $list[$i]['status'];
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($i + 2), $list[$i]['type']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . ($i + 2), $list[$i]['id']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . ($i + 2), $list[$i]['uid']);
            $objPHPExcel->getActiveSheet()->setCellValue('D' . ($i + 2), $list[$i]['uname']);
            $objPHPExcel->getActiveSheet()->setCellValue('E' . ($i + 2), $list[$i]['username']);
            $objPHPExcel->getActiveSheet()->setCellValue('F' . ($i + 2), $list[$i]['document_id']);
            $objPHPExcel->getActiveSheet()->setCellValue('G' . ($i + 2), $list[$i]['bankname']);
            $objPHPExcel->getActiveSheet()->setCellValue('H' . ($i + 2), $list[$i]['bank_type']);
            $objPHPExcel->getActiveSheet()->setCellValue('I' . ($i + 2), $list[$i]['bank_branch']);
            $objPHPExcel->getActiveSheet()->setCellValue('J' . ($i + 2), $list[$i]['cardnum'] . '-' . $list[$i]['account_digit']);
            $objPHPExcel->getActiveSheet()->setCellValue('K' . ($i + 2), $list[$i]['num']);
            $objPHPExcel->getActiveSheet()->setCellValue('L' . ($i + 2), $list[$i]['status']);
            $objPHPExcel->getActiveSheet()->setCellValue('L' . ($i + 2), $list[$i]['wallet_document_type']);
            $objPHPExcel->getActiveSheet()->setCellValue('M' . ($i + 2), $list[$i]['wallet_tel']);
            $objPHPExcel->getActiveSheet()->setCellValue('N' . ($i + 2), $list[$i]['wallet_document_id']);
            $objPHPExcel->getActiveSheet()->setCellValue('O' . ($i + 2), $list[$i]['usdt']);
            $objPHPExcel->getActiveSheet()->setCellValue('P' . ($i + 2), $list[$i]['addtime']);
            $objPHPExcel->getActiveSheet()->setCellValue('Q' . ($i + 2), $agent);
            $objPHPExcel->getActiveSheet()->setCellValue('R' . ($i + 2), $agent_service);
            $objPHPExcel->getActiveSheet()->setCellValue('S' . ($i + 2), $status);
        }

        //7.设置保存的Excel表格名称
        $filename = 'tixian' . date('ymd', time()) . '.xls';
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
        sysoplog('导出提现', json_encode($_POST, JSON_UNESCAPED_UNICODE));
        //下载文件在浏览器窗口
        $objWriter->save('php://output');
        exit;
    }


    /**
     * 批量拒绝
     */
    public function do_deposit3()
    {
        $ids = [];
        if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
            $ids = explode(',', $_REQUEST['id']);
            foreach ($ids as $id) {
                $t = Db::name('xy_deposit')->where('id', $id)->find();
                if ($t['status'] == 1) {
                    //通过
                    Db::name('xy_deposit')->where('id', $id)->update(['status' => 3, 'endtime' => time()]);
                    //驳回订单的业务逻辑
                    Db::name('xy_users')->where('id', $t['uid'])->setInc('balance', input('num/f', 0));
                }
            }
            sysoplog('批量拒绝提现', json_encode($_POST, JSON_UNESCAPED_UNICODE));
            $this->success('处理成功', '#' . url('deposit_list'));
        }
    }


    /**
     * 一键返佣
     */
    public function do_commission()
    {
        $this->applyCsrfToken();
        $info = Db::name('xy_convey')
            ->field('id oid,uid,num,commission cnum')
            ->where([
                ['c_status', 'in', [0, 2]],
                ['status', 'in', [1, 3]],
                //['endtime','between','??']    //时间限制
            ])
            ->select();
        if (!$info) return $this->error('当前没有待返佣订单!');
        try {
            foreach ($info as $k => $v) {
                Db::startTrans();
                $res = Db::name('xy_users')->where('id', $v['uid'])->where('status', 1)->setInc('balance', $v['num'] + $v['cnum']);
                if ($res) {
                    $res1 = Db::name('xy_balance_log')->insert([
                        //记录返佣信息
                        'uid' => $v['uid'],
                        'oid' => $v['oid'],
                        'num' => $v['num'] + $v['cnum'],
                        'type' => 3,
                        'addtime' => time()
                    ]);
                    Db::name('xy_convey')->where('id', $v['oid'])->update(['c_status' => 1]);
                } else {
                    // Db::name('xy_system_log')->insert();
                    $res1 = Db::name('xy_convey')->where('id', $v['oid'])->update(['c_status' => 2]);//记录账号异常
                }
                if ($res !== false && $res1) {
                    sysoplog('一键返佣', '');
                    Db::commit();
                } else
                    Db::rollback();
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
        return $this->success('操作成功!');
    }

    /**
     * 交易佣金流水
     */
    public function order_commission_list($oid)
    {
        if (!$oid) {
            $this->error('请选择要查看的订单');
        }
        $this->_query('xy_balance_log')
            ->alias('xc')
            ->leftJoin('xy_users u', 'u.id=xc.uid')
            ->field('xc.*,u.username')
            ->where(['oid' => $oid])->page();
    }

    /**
     * 团队返佣
     * @menu true
     */
    public function team_reward()
    {

    }
}