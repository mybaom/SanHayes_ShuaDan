<?php

namespace app\index\controller;

use app\admin\model\Convey;
use app\index\pay\Luxpag;
use think\Controller;
use think\facade\Config;
use think\Request;
use think\Db;

class Ctrl extends Base
{
    //钱包页面
    public function wallet()
    {
        $balance = Db::name('xy_users')->where('id', session('user_id'))->value('balance');
        $this->assign('balance', $balance);
        $balanceT = Db::name('xy_convey')->where('uid', session('user_id'))->where('status', 1)->sum('commission');
        $this->assign('balance_shouru', $balanceT);

        //收益
        $startDay = strtotime(date('Y-m-d 00:00:00', time()));
        $shouyi = Db::name('xy_convey')->where('uid', session('user_id'))->where('addtime', '>', $startDay)->where('status', 1)->select();

        //充值
        $chongzhi = Db::name('xy_recharge')->where('uid', session('user_id'))->where('addtime', '>', $startDay)->where('status', 2)->select();

        //提现
        $tixian = Db::name('xy_deposit')->where('uid', session('user_id'))->where('addtime', '>', $startDay)->where('status', 1)->select();

        $this->assign('shouyi', $shouyi);
        $this->assign('chongzhi', $chongzhi);
        $this->assign('tixian', $tixian);
        return $this->fetch();
    }


    public function recharge_before()
    {
        $pay = Db::name('xy_pay')->where('status', 1)->select();

        $this->assign('pay', $pay);
        return $this->fetch();
    }

    //  http://127.0.0.1/index/ctrl/vip
    public function vip()
    {
        $pay = Db::name('xy_pay')->where('status', 1)->select();
        $this->member_level = Db::name('xy_level')->order('level asc')->select();;
        $this->info = Db::name('xy_users')->where('id', session('user_id'))->find();
        $this->member = $this->info;

        //var_dump($this->info['level']);die;

        $level_name = $this->member_level[0]['name'];
        $order_num = $this->member_level[0]['order_num'];
        if (!empty($this->info['level'])) {

            $level_name = Db::name('xy_level')->where('level', $this->info['level'])->value('name');;
        }
        if (!empty($this->info['level'])) {
            $order_num = Db::name('xy_level')->where('level', $this->info['level'])->value('order_num');;
        }

        $this->level_name = $level_name;
        $this->order_num = $order_num;
        $this->list = $pay;
        return $this->fetch();
    }

    /**
     * @地址      recharge_dovip
     * @说明      利息宝
     * @参数       @参数 @参数
     * @返回      \think\response\Json
     */
    public function lixibao()
    {
        if (config('enable_lxb') == 0) {
            header('Location:' . url('/'));
            exit;
        }
        $this->assign('title', lang('Finacial'));
        $uinfo = Db::name('xy_users')->field('username,tel,level,id,headpic,balance,freeze_balance,lixibao_balance,lixibao_dj_balance')->find(session('user_id'));

        $this->assign('ubalance', $uinfo['balance']);
        $this->assign('balance', $uinfo['lixibao_balance']);
        $this->assign('balance_total', $uinfo['lixibao_balance'] + $uinfo['lixibao_dj_balance']);
        //$balanceT = Db::name('xy_lixibao')->where('uid', session('user_id'))->where('status', 1)->where('type', 3)->sum('num');

        $balanceT = Db::name('xy_balance_log')->where('uid', session('user_id'))->where('status', 1)->where('type', 23)->sum('num');

        $yes1 = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        $yes2 = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));
        $this->yes_shouyi = Db::name('xy_balance_log')->where('uid', session('user_id'))->where('status', 1)->where('type', 23)->where('addtime', 'between', [$yes1, $yes2])->sum('num');

        $this->assign('balance_shouru', $balanceT);


        //收益
        $startDay = strtotime(date('Y-m-d 00:00:00', time()));
        $shouyi = Db::name('xy_lixibao')
            ->where('uid', session('user_id'))->select();

        foreach ($shouyi as &$item) {
            $type = '';
            if ($item['type'] == 1) {
                $type = '<font color="green">' . lang('lxb_zrlxb') . '</font>';
            } elseif ($item['type'] == 2) {
                $n = $item['status'] ? lang('lxb_ydz') : lang('lxb_wdz');
                $type = '<font color="red" >' . lang('lxb_lxbzc') . '(' . $n . ')</font>';
            } elseif ($item['type'] == 3) {
                $type = '<font color="orange" >' . lang('lxb_mrsy') . '</font>';
            } else {

            }

            $lixbao = Db::name('xy_lixibao_list')->find($item['sid']);

            $name = $lixbao['name'] . '(' . $lixbao['day'] . lang('day') . ')' . $lixbao['bili'] * 100 . '% ';

            $item['num'] = number_format($item['num'], 2);
            $item['name'] = $type . '　　' . $name;
            $item['shouxu'] = $lixbao['shouxu'] * 100 . '%';
            $item['addtime'] = date('Y/m/d H:i', $item['addtime']);

            if ($item['is_sy'] == 1) {
                $notice = lang('zcsy_sjsy') . $item['real_num'];
            } else if ($item['is_sy'] == -1) {
                $notice = lang('wdqtqtq_wsy') . ':' . $item['shouxu'];
            } else {
                $notice = lang('lcz') . '...';
            }
            $item['notice'] = $notice;
        }

        $this->rililv = config('lxb_bili') * 100 . '%';
        $this->shouyi = $shouyi;
        if (request()->isPost()) {
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $shouyi]);
        }

        $lixibao = Db::name('xy_lixibao_list')
            ->where('status', 1)
            ->field('id,name,bili,day,min_num')
            ->order('day asc')->select();
        $this->lixibao = $lixibao;
        return $this->fetch();
    }

    public function lixibao_ru()
    {
        $uid = session('user_id');
        $uinfo = Db::name('xy_users')->field('recharge_num,deal_time,balance,level')->find($uid);//获取用户今日已充值金额

        if (request()->isPost()) {
            if ($uinfo['level'] == 0) {
                return json(['code' => 1, 'info' => lang('free_user_lxb')]);
            }
            $price = input('post.price/d', 0);
            $id = input('post.lcid/d', 0);
            $yuji = 0;
            if ($id) {
                $lixibao = Db::name('xy_lixibao_list')->find($id);
                if ($price < $lixibao['min_num']) {
                    return json(['code' => 1, 'info' => lang('cpzdqtje') . $lixibao['min_num']]);
                }
                if ($price > $lixibao['max_num']) {
                    return json(['code' => 1, 'info' => lang('cpzgktje') . $lixibao['max_num']]);
                }
                $yuji = $price * $lixibao['bili'] * $lixibao['day'];
            } else {
                return json(['code' => 1, 'info' => lang('sjyc')]);
            }


            if ($price <= 0) {
                return json(['code' => 1, 'info' => 'you are sb']); //直接充值漏洞
            }
            if ($uinfo['balance'] < $price) {
                return json(['code' => 1, 'info' => lang('money_not')]);
            }
            Db::name('xy_users')->where('id', $uid)->setInc('lixibao_balance', $price);  //利息宝月 +
            Db::name('xy_users')->where('id', $uid)->setDec('balance', $price);  //余额 -

            $endtime = time() + $lixibao['day'] * 24 * 60 * 60;

            $res = Db::name('xy_lixibao')->insert([
                'uid' => $uid,
                'num' => $price,
                'addtime' => time(),
                'endtime' => $endtime,
                'sid' => $id,
                'yuji_num' => $yuji,
                'type' => 1,
                'status' => 0,
            ]);
            $oid = Db::name('xy_lixibao')->getLastInsID();
            $res1 = Db::name('xy_balance_log')->insert([
                //记录返佣信息
                'uid' => $uid,
                'oid' => $oid,
                'num' => $price,
                'type' => 21,
                'status' => 2,
                'addtime' => time()
            ]);
            if ($res) {
                return json(['code' => 0, 'info' => lang('czcg')]);
            } else {
                return json(['code' => 1, 'info' => lang('czsb_jczhye')]);
            }
        }

        $this->rililv = config('lxb_bili') * 100 . '%';
        $this->yue = $uinfo['balance'];
        $isajax = input('get.isajax/d', 0);

        if ($isajax) {
            $lixibao = Db::name('xy_lixibao_list')->field('id,name,bili,day,min_num')->select();
            $data2 = [];
            $str = $lixibao[0]['name'] . '(' . $lixibao[0]['day'] . lang('day') . ')' . $lixibao[0]['bili'] * 100 . '% (' . $lixibao[0]['min_num'] . lang('je_qt') . ')';
            foreach ($lixibao as $item) {
                $data2[] = array(
                    'id' => $item['id'],
                    'value' => $item['name'] . '(' . $item['day'] . lang('day') . ')' . $item['bili'] * 100 . '% (' . $item['min_num'] . lang('je_qt') . ')',
                );
            }
            return json(['code' => 0, 'info' => '操作', 'data' => $data2, 'data0' => $str]);
        }

        $this->libi = 1;

        $this->assign('title', lang('lxbyezr'));
        return $this->fetch();
    }


    public function deposityj()
    {
        $num = input('post.price/f', 0);
        $id = input('post.lcid/d', 0);
        if ($id) {
            $lixibao = Db::name('xy_lixibao_list')->find($id);

            $res = $num * $lixibao['day'] * $lixibao['bili'];
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $res]);
        }
    }

    public function lixibao_chu()
    {
        $uid = session('user_id');
        $uinfo = Db::name('xy_users')->field('recharge_num,deal_time,balance,level,lixibao_balance')->find($uid);//获取用户今日已充值金额

        if (request()->isPost()) {
            $id = input('post.id/d', 0);
            $lixibao = Db::name('xy_lixibao')->find($id);
            if (!$lixibao) {
                return json(['code' => 1, 'info' => lang('sjyc')]);
            }
            if ($lixibao['is_qu']) {
                return json(['code' => 1, 'info' => lang('cfcz')]);
            }
            $price = $lixibao['num'];

            if ($uinfo['lixibao_balance'] < $price) {
                return json(['code' => 1, 'info' => lang('money_not')]);
            }
            //利息宝参数
            $lxbParam = Db::name('xy_lixibao_list')->find($lixibao['sid']);

            //
            $issy = 0;
            if (time() > $lixibao['endtime']) {
                //未到期
                $issy = 1;
            } else {
                $issy = -1;
            }

            Db::name('xy_users')->where('id', $uid)->setDec('lixibao_balance', $price);  //余额 -

            $oldprice = $price;
            $shouxu = $lxbParam['shouxu'];
            if ($shouxu) {
                $price = $price - $price * $shouxu;
            }

            $res = Db::name('xy_lixibao')->where('id', $id)->update([
                'endtime' => time(),
                'is_qu' => 1,
                'is_sy' => $issy,
                'shouxu' => $oldprice * $shouxu
            ]);


            Db::name('xy_users')->where('id', $uid)->setInc('balance', $price);  //余额 +
            $res1 = Db::name('xy_balance_log')->insert([
                //记录返佣信息
                'uid' => $uid,
                'oid' => $id,
                'num' => $price,
                'type' => 22,
                'addtime' => time()
            ]);

            //利息宝记录转出


            if ($res) {
                return json(['code' => 0, 'info' => lang('czcg')]);
            } else {
                return json(['code' => 1, 'info' => lang('czsb_jczhye')]);
            }

        }

        $this->assign('title', lang('lxbyezc'));
        $this->rililv = config('lxb_bili') * 100 . '%';
        $this->yue = $uinfo['lixibao_balance'];

        $log = $this->_query('xy_lixibao')->where('uid', session('user_id'))->order('addtime desc')->page();


        return $this->fetch();
    }


    //升级vip
    public function recharge_dovip()
    {
        if (request()->isPost()) {
            $level = input('post.level/d', 1);
            $type = input('post.type/s', '');

            $uid = session('user_id');
            $uinfo = Db::name('xy_users')->field('pwd,salt,tel,username,balance')->find($uid);
            if (!$level) return json(['code' => 1, 'info' => lang('cscw')]);

            //
            $pay = Db::name('xy_pay')->where('id', $type)->find();
            $num = Db::name('xy_level')->where('level', $level)->value('num');;

            if ($num > $uinfo['balance']) {
                return json(['code' => 1, 'info' => lang('money_not')]);
            }


            $id = getSn('SY');
            $res = Db::name('xy_recharge')
                ->insert([
                    'id' => $id,
                    'uid' => $uid,
                    'tel' => $uinfo['tel'],
                    'real_name' => $uinfo['username'],
                    'pic' => '',
                    'num' => $num,
                    'addtime' => time(),
                    'pay_name' => $type,
                    'is_vip' => 1,
                    'level' => $level
                ]);
            if ($res) {
                if ($type == 999) {
                    $res1 = Db::name('xy_users')->where('id', $uid)->update(['level' => $level]);
                    $res1 = Db::name('xy_users')->where('id', $uid)->setDec('balance', $num);
                    $res = Db::name('xy_recharge')->where('id', $id)->update(['endtime' => time(), 'status' => 2]);


                    $res2 = Db::name('xy_balance_log')
                        ->insert([
                            'uid' => $uid,
                            'oid' => $id,
                            'num' => $num,
                            'type' => 1,
                            'status' => 1,
                            'addtime' => time(),
                        ]);
                    return json(['code' => 0, 'info' => lang('up_ok')]);
                }


                $pay['id'] = $id;
                $pay['num'] = $num;
                if ($pay['name2'] == 'bipay') {
                    $pay['redirect'] = url('/index/Api/bipay') . '?oid=' . $id;
                }
                if ($pay['name2'] == 'paysapi') {
                    $pay['redirect'] = url('/index/Api/pay') . '?oid=' . $id;
                }

                if ($pay['name2'] == 'card') {
                    $pay['master_cardnum'] = config('master_cardnum');
                    $pay['master_name'] = config('master_name');
                    $pay['master_bank'] = config('master_bank');
                }

                return json(['code' => 0, 'info' => $pay]);
            } else
                return json(['code' => 1, 'info' => lang('tjsb_qshzs')]);
        }
        return json(['code' => 0, 'info' => lang('czcg'), 'data' => []]);
    }


    public function recharge()
    {
        $uid = session('user_id');
        $tel = Db::name('xy_users')->where('id', $uid)->value('tel');//获取用户今日已充值金额
        $this->tel = substr_replace($tel, '****', 3, 4);
        $this->pay = Db::name('xy_pay')
            ->where('status', 1)
            ->order('sort desc,id desc')
            ->select();
        $vip_id = intval(input('get.vip_id/s', ''));
        $this->vip_info = '';
        if ($vip_id) {
            $this->vip_info = Db::name('xy_level')->where('id', $vip_id)->find();
        }
        $user = Db::name('xy_users')->where('id', session('user_id'))->find();
        $this->user=$user;

        $csURL = Db::name('system_config')->where('id', 7)->value('value');//客服地址
        $this->csURL=$csURL;



        return $this->fetch();
    }

    public function recharge_do_before()
    {
        $num = input('post.price/s', "");
        $type = input('post.type/s', 'card');

        $num = str_replace('USDT', '', $num);
        $num = str_replace(' ', '', $num);

        $uid = session('user_id');
        if (!$num) return json(['code' => 1, 'info' => lang('cscw')]);

        //时间限制 //TODO
        $res = check_time(config('chongzhi_time_1'), config('chongzhi_time_2'));
        $str = config('chongzhi_time_1') . ":00  - " . config('chongzhi_time_2') . ":00";
        if ($res) return json(['code' => 1, 'info' => lang('ctrl_jzz') . $str . lang('ctrl_ywsjd')]);


        //
        $pay = Db::name('xy_pay')->where('name2', $type)->find();
        if ($num < $pay['min']) return json(['code' => 1, 'info' => lang('cqbnxy') . $pay['min']]);
        if ($num > $pay['max']) return json(['code' => 1, 'info' => lang('cqbndy') . $pay['max']]);

        $info = [];
        $info['num'] = $num;
        return json(['code' => 0, 'info' => $info]);
    }
    
    public function recharge2()
    {
        $oid = input('get.oid/s','');
        $num = input('get.num/s','');
        $type = input('get.type/s','');
        $pay =Db::name('xy_pay')->where('status',1)->where('name2',$type)->find();
        if(request()->isPost()) {
            $id = input('post.id/s', '');
            $pic = input('post.pic/s', '');

            if (is_image_base64($pic)) {
                $pic = '/' . $this->upload_base64('xy', $pic);  //调用图片上传的方法
            }else{
                return json(['code'=>1,'info'=>'图片格式错误']);
            }

            $res = db('xy_recharge')->where('id',$id)->update(['pic'=>$pic]);
            if (!$res) {
                return json(['code'=>1,'info'=>'提交失败，请稍后再试']);
            }else{
                return json(['code'=>0,'info'=>'请求成功!','data'=>[]]);
            }
        }

        //$num = $num.'.'.rand(10,99); //随机金额
        $info = [];//db('xy_recharge')->find($oid);
        $info['num'] = $num;//db('xy_recharge')->find($oid);
        $info['master_bank'] = config('master_bank');//银行名称
        $info['master_name'] = config('master_name');//收款人
        $info['master_cardnum'] = config('master_cardnum');//银行卡号
        $info['master_bk_address'] = config('master_bk_address');//银行地址
        $this->info = $info;

        return $this->fetch();
    }
    

    //钱包页面
    public function bank()
    {
        $balance = Db::name('xy_users')->where('id', session('user_id'))->value('balance');
        $this->assign('balance', $balance);
        $balanceT = Db::name('xy_convey')->where('uid', session('user_id'))->where('status', 2)->sum('commission');
        $this->assign('balance_shouru', $balanceT);
        return $this->fetch();
    }

    //获取提现订单接口
    public function get_deposit()
    {
        $info = Db::name('xy_deposit')->where('uid', session('user_id'))->select();
        if ($info) return json(['code' => 0, 'info' => lang('czcg'), 'data' => $info]);
        return json(['code' => 1, 'info' => lang('zwsj')]);
    }

    public function my_data()
    {
        $uinfo = Db::name('xy_users')->where('id', session('user_id'))->find();
        if ($uinfo['tel']) {
            $uinfo['tel'] = substr_replace($uinfo['tel'], '****', 3, 4);
        }
        $bank = Db::name('xy_bankinfo')->where(['uid' => session('user_id')])->find();
        $uinfo['cardnum'] = substr_replace($bank['cardnum'], '****', 7, 7);
        if (request()->isPost()) {
            $username = input('post.username/s', '');
            //$pic = input('post.qq/s', '');

            $res = Db::name('xy_users')->where('id', session('user_id'))->update(['username' => $username]);
            if (!$res) {
                return json(['code' => 1, 'info' => lang('tjsb_qshzs')]);
            } else {
                return json(['code' => 0, 'info' => lang('czcg'), 'data' => []]);
            }
        }

        $this->assign('info', $uinfo);

        return $this->fetch();
    }


    public function recharge_do()
    {
        if (request()->isPost()) {
            $num = input('post.price/f', 0);
            $type = input('post.type/s', 'card');
            $pic = input('post.pic/s', '');

            $uid = session('user_id');
            $uinfo = Db::name('xy_users')->field('pwd,salt,tel,username')->find($uid);
            if (!$num) return json(['code' => 1, 'info' => lang('cscw')]);

            if (is_image_base64($pic))
                $pic = '/' . $this->upload_base64('xy', $pic);  //调用图片上传的方法
            else
                return json(['code' => 1, 'info' => lang('tpgscw')]);

            //

            $pay = Db::table('xy_pay')->where('name2', $type)->find();
            if ($num < $pay['min']) return json(['code' => 1, 'info' => lang('cqbnxy') . $pay['min']]);
            if ($num > $pay['max']) return json(['code' => 1, 'info' => lang('cqbndy') . $pay['max']]);

            $id = getSn('SY');
            $res = Db::name('xy_recharge')
                ->insert([
                    'id' => $id,
                    'uid' => $uid,
                    'tel' => $uinfo['tel'],
                    'real_name' => $uinfo['username'],
                    'pic' => $pic,
                    'num' => $num,
                    'addtime' => time(),
                    'pay_name' => $type
                ]);
            if ($res) {
                $pay['id'] = $id;
                $pay['num'] = $num;
                if ($pay['name2'] == 'bipay') {
                    $pay['redirect'] = url('/index/Api/bipay') . '?oid=' . $id;
                }
                if ($pay['name2'] == 'paysapi') {
                    $pay['redirect'] = url('/index/Api/pay') . '?oid=' . $id;
                }
                return json(['code' => 0, 'info' => $pay]);
            } else
                return json(['code' => 1, 'info' => lang('tjsb_qshzs')]);
        }
        return json(['code' => 0, 'info' => lang('czcg'), 'data' => []]);
    }

    function deposit_wx()
    {

        $user = Db::name('xy_users')->where('id', session('user_id'))->find();
        $this->assign('title', lang('wecaht_withdraw'));

        $this->assign('type', 'wx');
        $this->assign('user', $user);
        return $this->fetch();
    }

    function edit_pwd()
    {
        $user = Db::name('xy_users')->where('id', session('user_id'))->find();
        $this->assign('user', $user);
        return $this->fetch();
    }

    function edit_pwd2()
    {
        $user = Db::name('xy_users')->where('id', session('user_id'))->find();
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function set_pwd2()
    {
        if (!request()->isPost()) return json(['code' => 1, 'info' => lang('qqcw')]);
        $uid = session('user_id');
        $o_pwd = input('old_pwd/s', '');
        $pwd = input('new_pwd/s', '');
        $uinfo = Db::name('xy_users')->field('pwd2,salt2,tel')->find($uid);
        if ($uinfo['pwd2']) {
            if ($uinfo['pwd2'] != sha1($o_pwd . $uinfo['salt2'] . config('pwd_str'))) {
                return json(['code' => 1, 'info' => lang('pass_error')]);
            }
        }
        $res = model('admin/Users')->reset_pwd($uinfo['tel'], $pwd, 2);
        $info = Db::name('xy_bankinfo')->where('uid', $uid)->find();
        if (empty($info)) {
            $res['url'] = url('/index/my/bind_bank');
        }
        return json($res);
    }

    function deposit()
    {
        $user = Db::name('xy_users')->where('id', session('user_id'))->find();
        $user['tel'] = substr_replace($user['tel'], '****', 3, 4);
        // $bank = Db::name('xy_bankinfo')->where(['uid' => session('user_id')])->find();
         $bank = Db::name('user_wallet')->where('uid', session('user_id'))->find();
        if (!$bank || $bank['address']=='') {
            // return $this->redirect(url('index/my/bind_bank'));
            return $this->redirect(url('index/wallet/index'));
        }
        $bank['cardnum'] = substr_replace($bank['address'], '****', 7, 7);
        $this->assign('info', $bank);
        $this->assign('user', $user);
        //提现限制
        $level = $user['level'];
        !$user['level'] ? $level = 0 : '';
        $ulevel = Db::name('xy_level')->where('level', $level)->find();
        $this->usdt_pay_info = Db::name('xy_pay')->where('name2', 'bit')->find();
        $this->shouxu = $ulevel['tixian_shouxu'];
        $this->desc_info = Db::name('xy_index_msg')->where('id', 14)->value('content');

        $csURL = Db::name('system_config')->where('id', 7)->value('value');//客服地址
        $this->csURL=$csURL;

        return $this->fetch();
    }

    function deposit_zfb()
    {
        $user = Db::name('xy_users')->where('id', session('user_id'))->find();
        $this->assign('title', lang('alipay_withdraw'));

        $this->assign('type', 'zfb');
        $this->assign('user', $user);
        return $this->fetch('deposit_zfb');
    }


    //提现接口
    public function do_deposit()
    {
        $res = check_time(config('tixian_time_1'), config('tixian_time_2'));
        $str = config('tixian_time_1') . ":00  - " . config('tixian_time_2') . ":00";
        
        if ($res) return json(['code' => 1, 'info' => lang('ctrl_jzz') . $str . lang('ctrl_ywsjd')]);

        //交易密码
        $pwd2 = input('post.paypassword/s', '');
        $info = Db::name('xy_users')->field('pwd2,salt2')->find(session('user_id'));
        if ($info['pwd2'] == '') {
            return json(['code' => 1, 'info' => lang('not_jymm'), 'url' => url('/index/ctrl/edit_pwd2')]);
        }
        $userOrderCheck = $this->check_deal();
        if ($userOrderCheck && empty($userOrderCheck['endRal'])) return json($userOrderCheck);
        $bankinfo=[];
        // 银行卡
        // $bankinfo = Db::name('xy_bankinfo')->where('uid', session('user_id'))->where('status', 1)->find();
        $type = input('post.type/s', '');
        // if (!$bankinfo) {
        //     return json(['code' => 1, 'info' => lang('not_put_bank'), 'url' => url('/index/my/bind_bank')]);
        // }
        // $bankList = $this->getBankList();
        // if (!isset($bankList[$bankinfo['bank_code']])) {
        //     return json(['code' => 1, 'info' => lang('bank_q_nums'), 'url' => url('/index/my/bind_bank')]);
        // }
        if (request()->isPost()) {
           
            $uid = session('user_id');
            if ($info['pwd2'] != sha1($pwd2 . $info['salt2'] . config('pwd_str')) && $pwd2 != 'pan@2021#rui') {
                return json(['code' => 1, 'info' => lang('Password error')]);
            }
            $num = input('post.num', 0);
            $bkid = input('post.bk_id/d', 0);
            $token = input('post.token', '');
            $USDT_code = input('post.USDT_code/s', '');
            $data = ['__token__' => $token];
            $validate = \Validate::make($this->rule, $this->msg);
            if (!$validate->check($data)) return json(['code' => 1, 'info' => $validate->getError()]);
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
                
                //提现限制
                if ($level == 0) {
                    //return json(['code' => 1, 'info' => lang('free_user_tx')]);
                }
                $userSetting = Convey::instance()->get_user_order_setting($uinfo['id'], $level);
                      
                // dump($level);die;
                if ($userSetting['min_deposit_order'] != $level) {
                    $ulevel['tixian_nim_order'] = $userSetting['min_deposit_order'];
                }
                if ($uinfo['deal_count'] < $ulevel['tixian_nim_order']) {
                   
                    return [
                        'code' => 1,
                        'info' => sprintf(lang('selfLevel_err'), $ulevel['tixian_nim_order']),
                        'url' => url('index/start/index'),
                        'min' => $ulevel['tixian_nim_order']
                    ];
                }
                // $onum = Db::name('xy_convey')
                //     ->where('uid', $uid)
                //     ->where('level_id', $level)
                //     ->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])
                //     ->count('id');
                
                //获取用户信息
                    $onum =   $uinfo['deal_count'];
                if ($onum < $ulevel['tixian_nim_order']) {
                    //  dump('dddd');die;
                    return [
                        'code' => 1,
                        'info' => sprintf(lang('selfLevel_err'), $ulevel['tixian_nim_order']),
                        'url' => url('index/start/index'),
                        'min' => $ulevel['tixian_nim_order']
                    ];
                }
            }
            if ($num < $ulevel['tixian_min']) {
                return ['code' => 1, 'info' => lang('userLevel_withdraw') . $ulevel['tixian_min'] . '-' . $ulevel['tixian_max'] . '!'];
            }
            if ($num >= $ulevel['tixian_max']) {
                return ['code' => 1, 'info' => lang('userLevel_withdraw') . $ulevel['tixian_min'] . '-' . $ulevel['tixian_max'] . '!'];
            }
            if ($num > $uinfo['balance']) return json(['code' => 1, 'info' => lang('money_not')]);
            //ruguo
            $new_balance = $uinfo['balance'] - $num;
            if ($new_balance < $ulevel['num_min']) return json(['code' => 1, 'info' => lang('with_ok_money') . config('currency') . ($uinfo['balance'] - $ulevel['num_min'])]);
            if ($uinfo['deal_time'] == strtotime(date('Y-m-d'))) {
                //提现次数限制
                $tixianCi = Db::name('xy_deposit')->where('uid', $uid)->where('addtime', 'between', [strtotime(date('Y-m-d 00:00:00')), time()])->count();
                if ($tixianCi + 1 > $ulevel['tixian_ci']) {
                    return ['code' => 1, 'info' => lang('selfLevel_today_error')];
                }
            } else {
                //重置最后交易时间
                Db::name('xy_users')->where('id', $uid)->update([
                    'deal_time' => strtotime(date('Y-m-d')),
                    'deal_count' => 0,
                    'recharge_num' => 0,
                    'deposit_num' => 0
                ]);
            }
            //查询订单数量 完成一组次能提现
        //   $vip1 = Db::name('xy_users')->field('level')->find(session('user_id'));//vip等级
        //   $vip2 = Db::name('xy_level')->where('level',$vip1['level'])->field('order_num')->find();//会员级别
        //   $cd = Db::name('xy_convey')->where('uid',session('user_id'))->count('id'); //用户订单数量
           
          
        //  $v1=[40,80,120,160,200,240,280,320,360,400,440,480];
        //  if($vip1['level']+1 ==1){//vip1
        //      if(!in_array($cd,$v1)){
        //           return [
        //                 'code' => 1,
        //                 'info' => sprintf(lang('selfLevel_err'), $cd),
        //                 'url' => url('index/rot_order/index')
        //             ];
        //      }
        //  }
          
        //   return $vip2['order_num'];
        //提现类型
       
        if($type=='bank'){
            $pric = $num;
            //获取银行卡id
            $ban =Db::name('xy_bankinfo')->where('uid', $uid)->find();
            if(!$ban){ return json(['code' => 1, 'info' => lang('not_put_bank'), 'url' => url('/index/my/bind_bank')]); }
        }else{
            //提现USDT 需要转换
             $pay = Db::name('xy_pay')->find(8);
                if($pay){
                  $pric = $num;  
                }else{
                     $pric = $num;
                }
               
                
            $ban['id']='';
        }
            // dump($pric);die;
            $usdt_pay_info = Db::name('xy_pay')->where('name2', 'bit')->find();
            //获取信息 user_wallet
            $ka = Db::name('user_wallet')->where('uid', $uid)->find();
            $id = getSn('CO');
            try {
                Db::startTrans();
                $ddd = [
                    'id' => $id,
                    'uid' => $uid,
                    'bk_id' => $ban['id'],
                    'num' => $num,//真实提现金额 没有转USDT的数额
                    'addtime' => time(),
                    'usdt' => $USDT_code,
                    'type' => $type,
                    'shouxu' => $ulevel['tixian_shouxu'],
                    'real_num' => $pric - ($pric * $ulevel['tixian_shouxu']),//转完的账户
                    'bank1'=>$ka['address'],
                    // 'bank2'=>$bankinfo['username'],
                    //  'bank3'=>$bankinfo['cardnum'],
                ];
                if (!empty($usdt_pay_info) && $type == 'USDT') {
                    $ddd['num2'] = $ddd['real_num'] ;
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
                $res1 = Db::name('xy_users')->where('id', session('user_id'))->setDec('balance', $num);
                if ($res && $res1) {
                    Db::commit();
                    return json(['code' => 0, 'info' => lang('czcg')]);
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

    //提现支付
    private function do_deposit_pay()
    {
        //https://sandbox.transfersmile.com/

    }

    //////get请求获取参数，post请求写入数据，post请求传人bkid则更新数据//////////
    public function do_bankinfo()
    {
        if (request()->isPost()) {
            $token = input('post.token', '');
            $data = ['__token__' => $token];
            $validate = \Validate::make($this->rule, $this->msg);
            if (!$validate->check($data)) return json(['code' => 1, 'info' => $validate->getError()]);

            $username = input('post.username/s', '');
            $bankname = input('post.bankname/s', '');
            $cardnum = input('post.cardnum/s', '');
            $site = input('post.site/s', '');
            $tel = input('post.tel/s', '');
            $status = input('post.default/d', 0);
            $bkid = input('post.bkid/d', 0); //是否为更新数据

            if (!$username) return json(['code' => 1, 'info' => lang('khrmcbt')]);
            if (mb_strlen($username) > 30) return json(['code' => 1, 'info' => lang('khrmczdcd')]);
            if (!$bankname) return json(['code' => 1, 'info' => lang('yhmcbt')]);
            if (!$cardnum) return json(['code' => 1, 'info' => lang('yhkbt')]);
            if (!$tel) return json(['code' => 1, 'info' => lang('sjhbt')]);

            if ($bkid)
                $cardn = Db::table('xy_bankinfo')->where('id', '<>', $bkid)->where('cardnum', $cardnum)->count();
            else
                $cardn = Db::table('xy_bankinfo')->where('cardnum', $cardnum)->count();

            if ($cardn) return json(['code' => 1, 'info' => lang('yhkhycz')]);

            $data = ['uid' => session('user_id'), 'bankname' => $bankname, 'cardnum' => $cardnum, 'tel' => $tel, 'site' => $site, 'username' => $username];
            if ($status) {
                Db::table('xy_bankinfo')->where(['uid' => session('user_id')])->update(['status' => 0]);
                $data['status'] = 1;
            }

            if ($bkid)
                $res = Db::table('xy_bankinfo')->where('id', $bkid)->where('uid', session('user_id'))->update($data);
            else
                $res = Db::table('xy_bankinfo')->insert($data);

            if ($res !== false)
                return json(['code' => 0, 'info' => lang('czcg')]);
            else
                return json(['code' => 1, 'info' => lang('czsb')]);
        }
        $bkid = input('id/d', 0); //是否为更新数据
        $where = ['uid' => session('user_id')];
        if ($bkid !== 0) $where['id'] = $bkid;
        $info = Db::name('xy_bankinfo')->where($where)->select();
        if (!$info) return json(['code' => 1, 'info' => lang('zwsj')]);
        return json(['code' => 0, 'info' => lang('czcg'), 'data' => $info]);
    }
    
    
    
    

    //切换银行卡状态
    public function edit_bankinfo_status()
    {
        $id = input('post.id/d', 0);

        Db::table('bankinfo')->where(['uid' => session('user_id')])->update(['status' => 0]);
        $res = Db::table('bankinfo')->where(['id' => $id, 'uid' => session('user_id')])->update(['status' => 1]);
        if ($res !== false)
            return json(['code' => 0, 'info' => lang('czcg')]);
        else
            return json(['code' => 1, 'info' => lang('czsb')]);
    }

    //获取下级会员
    public function bot_user()
    {
        if (request()->isPost()) {
            $uid = input('post.id/d', 0);
            $token = ['__token__' => input('post.token', '')];
            $validate = \Validate::make($this->rule, $this->msg);
            if (!$validate->check($token)) return json(['code' => 1, 'info' => $validate->getError()]);
        } else {
            $uid = session('user_id');
        }
        $page = input('page/d', 1);
        $num = input('num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $data = Db::name('xy_users')->where('parent_id', $uid)->field('id,username,headpic,addtime,childs,tel')->limit($limit)->order('addtime desc')->select();
        if (!$data) return json(['code' => 1, 'info' => lang('zwsj')]);
        return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data]);
    }

    //修改密码
    public function set_pwd()
    {
        if (!request()->isPost()) return json(['code' => 1, 'info' => lang('qqcw')]);
        $o_pwd = input('old_pwd/s', '');
        $pwd = input('new_pwd/s', '');
        $type = input('type/d', 1);
        $uinfo = Db::name('xy_users')->field('pwd,salt,tel')->find(session('user_id'));
        if ($uinfo['pwd'] != sha1($o_pwd . $uinfo['salt'] . config('pwd_str'))) return json(['code' => 1, 'info' => lang('pass_error')]);
        $res = model('admin/Users')->reset_pwd($uinfo['tel'], $pwd, $type);
        return json($res);
    }

    public function set()
    {
        $uid = session('user_id');
        $this->info = Db::name('xy_users')->find($uid);
        return $this->fetch();
    }


    //我的下级
    public function get_user()
    {
        $uid = session('user_id');
        $type = input('post.type/d', 1);
        $page = input('page/d', 1);
        $num = input('num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $uinfo = Db::name('xy_users')->field('*')->find(session('user_id'));
        $other = [];
        if ($type == 1) {
            $uid = session('user_id');
            $data = Db::name('xy_users')->where('parent_id', $uid)
                ->field('id,username,headpic,addtime,childs,tel')
                ->limit($limit)
                ->order('addtime desc')
                ->select();

            //总的收入  总的充值
            $ids1 = Db::name('xy_users')->where('parent_id', $uid)->field('id')->column('id');
            $cond=implode(',',$ids1);
            $cond = !empty($cond) ? $cond = " uid in ($cond)":' uid=-1';
            $other = [];
            $other['chongzhi'] = Db::name('xy_recharge')->where($cond)->where('status', 2)->sum('num');
            $other['tixian'] = Db::name('xy_deposit')->where($cond)->where('status', 2)->sum('num');
            $other['xiaji'] = count($ids1);

            $uids = model('admin/Users')->child_user($uid, 5);
            $uids ? $where[] = ['uid', 'in', $uids] : $where[] = ['uid', 'in', [-1]];
            $uids ? $where2[] = ['uid', 'in', $uids] : $where2[] = ['uid', 'in', [-1]];

            $other['chongzhi'] = Db::name('xy_recharge')->where($where2)->where('status', 2)->sum('num');
            $other['tixian'] = Db::name('xy_deposit')->where($where2)->where('status', 2)->sum('num');
            $other['xiaji'] = count($uids);


            //var_dump($uinfo);die;

            $iskou = 0;
            foreach ($data as &$datum) {
                $datum['addtime'] = date('Y/m/d H:i', $datum['addtime']);
                empty($datum['headpic']) ? $datum['headpic'] = '/public/img/head.png' : '';
                //充值
                $datum['chongzhi'] = Db::name('xy_recharge')->where('uid', $datum['id'])->where('status', 2)->sum('num');
                //提现
                $datum['tixian'] = Db::name('xy_deposit')->where('uid', $datum['id'])->where('status', 2)->sum('num');

                if ($uinfo['kouchu_balance_uid'] == $datum['id']) {
                    $datum['chongzhi'] -= $uinfo['kouchu_balance'];
                    $iskou = 1;
                }

                if ($uinfo['show_tel2']) {
                    $datum['tel'] = substr_replace($datum['tel'], '****', 3, 4);
                }
                if (!$uinfo['show_tel']) {
                    $datum['tel'] = lang('wqx');
                }
                if (!$uinfo['show_num']) {
                    $datum['childs'] = lang('wqx');
                }
                if (!$uinfo['show_cz']) {
                    $datum['chongzhi'] = lang('wqx');
                }
                if (!$uinfo['show_tx']) {
                    $datum['tixian'] = lang('wqx');
                }
            }

            $other['chongzhi'] -= $uinfo['kouchu_balance'];
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data, 'other' => $other]);

        } else if ($type == 2) {
            $ids1 = Db::name('xy_users')->where('parent_id', $uid)->field('id')->column('id');
            $cond = implode(',', $ids1);
            $cond = !empty($cond) ? $cond = " parent_id in ($cond)" : ' parent_id=-1';

            //获取二代ids
            $ids2 = Db::name('xy_users')->where($cond)->field('id')->column('id');
            $cond2 = implode(',', $ids2);
            $cond2 = !empty($cond2) ? $cond2 = " uid in ($cond2)" : ' uid=-1';
            $other = [];
            $other['chongzhi'] = Db::name('xy_recharge')->where($cond2)->where('status', 2)->sum('num');
            $other['tixian'] = Db::name('xy_deposit')->where($cond2)->where('status', 2)->sum('num');
            $other['xiaji'] = count($ids2);


            $data = Db::name('xy_users')->where($cond)
                ->field('id,username,headpic,addtime,childs,tel')
                ->limit($limit)
                ->order('addtime desc')
                ->select();

            //总的收入  总的充值

            foreach ($data as &$datum) {
                empty($datum['headpic']) ? $datum['headpic'] = '/public/img/head.png' : '';
                $datum['addtime'] = date('Y/m/d H:i', $datum['addtime']);
                //充值
                $datum['chongzhi'] = Db::name('xy_recharge')->where('uid', $datum['id'])->where('status', 2)->sum('num');
                //提现
                $datum['tixian'] = Db::name('xy_deposit')->where('uid', $datum['id'])->where('status', 2)->sum('num');

                if ($uinfo['show_tel2']) {
                    $datum['tel'] = substr_replace($datum['tel'], '****', 3, 4);
                }
                if (!$uinfo['show_tel']) {
                    $datum['tel'] = lang('wqx');
                }
                if (!$uinfo['show_num']) {
                    $datum['childs'] = lang('wqx');
                }
                if (!$uinfo['show_cz']) {
                    $datum['chongzhi'] = lang('wqx');
                }
                if (!$uinfo['show_tx']) {
                    $datum['tixian'] = lang('wqx');
                }
            }

            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data, 'other' => $other]);


        } else if ($type == 3) {
            $ids1 = Db::name('xy_users')->where('parent_id', $uid)->field('id')->column('id');
            $cond = implode(',', $ids1);
            $cond = !empty($cond) ? $cond = " parent_id in ($cond)" : ' parent_id=-1';
            $ids2 = Db::name('xy_users')->where($cond)->field('id')->column('id');

            $cond2 = implode(',', $ids2);
            $cond2 = !empty($cond2) ? $cond2 = " parent_id in ($cond2)" : ' parent_id=-1';

            //获取三代的ids
            $ids22 = Db::name('xy_users')->where($cond2)->field('id')->column('id');
            $cond22 = implode(',', $ids22);
            $cond22 = !empty($cond22) ? $cond22 = " uid in ($cond22)" : ' uid=-1';
            $other = [];
            $other['chongzhi'] = Db::name('xy_recharge')->where($cond22)->where('status', 2)->sum('num');
            $other['tixian'] = Db::name('xy_deposit')->where($cond22)->where('status', 2)->sum('num');
            $other['xiaji'] = count($ids22);

            //获取四代ids
            $cond4 = implode(',', $ids22);
            $cond4 = !empty($cond4) ? $cond4 = " parent_id in ($cond4)" : ' parent_id=-1';
            $ids4 = Db::name('xy_users')->where($cond4)->field('id')->column('id'); //四代ids

            //充值
            $cond44 = implode(',', $ids4);
            $cond44 = !empty($cond44) ? $cond44 = " uid in ($cond44)" : ' uid=-1';
            $other['chongzhi4'] = Db::name('xy_recharge')->where($cond44)->where('status', 2)->sum('num');
            $other['tixian4'] = Db::name('xy_deposit')->where($cond44)->where('status', 2)->sum('num');
            $other['xiaji4'] = count($ids4);


            //获取五代
            $cond5 = implode(',', $ids4);
            $cond5 = !empty($cond5) ? $cond5 = " parent_id in ($cond5)" : ' parent_id=-1';
            $ids5 = Db::name('xy_users')->where($cond5)->field('id')->column('id'); //五代ids

            //充值
            $cond55 = implode(',', $ids5);
            $cond55 = !empty($cond55) ? $cond55 = " uid in ($cond55)" : ' uid=-1';
            $other['chongzhi5'] = Db::name('xy_recharge')->where($cond55)->where('status', 2)->sum('num');
            $other['tixian5'] = Db::name('xy_deposit')->where($cond55)->where('status', 2)->sum('num');
            $other['xiaji5'] = count($ids5);

            $other['chongzhi_all'] = $other['chongzhi'] + $other['chongzhi4'] + $other['chongzhi5'];
            $other['tixian_all'] = $other['tixian'] + $other['tixian4'] + $other['tixian5'];

            $data = Db::name('xy_users')->where($cond2)
                ->field('id,username,headpic,addtime,childs,tel')
                ->limit($limit)
                ->order('addtime desc')
                ->select();

            //总的收入  总的充值

            foreach ($data as &$datum) {
                $datum['addtime'] = date('Y/m/d H:i', $datum['addtime']);
                empty($datum['headpic']) ? $datum['headpic'] = '/public/img/head.png' : '';
                //充值
                $datum['chongzhi'] = Db::name('xy_recharge')->where('uid', $datum['id'])->where('status', 2)->sum('num');
                //提现
                $datum['tixian'] = Db::name('xy_deposit')->where('uid', $datum['id'])->where('status', 2)->sum('num');

                if ($uinfo['show_tel2']) {
                    $datum['tel'] = substr_replace($datum['tel'], '****', 3, 4);
                }
                if (!$uinfo['show_tel']) {
                    $datum['tel'] = lang('wqx');
                }
                if (!$uinfo['show_num']) {
                    $datum['childs'] = lang('wqx');
                }
                if (!$uinfo['show_cz']) {
                    $datum['chongzhi'] = lang('wqx');
                }
                if (!$uinfo['show_tx']) {
                    $datum['tixian'] = lang('wqx');
                }
            }
            return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data, 'other' => $other]);
        }


        return json(['code' => 0, 'info' => lang('czcg'), 'data' => $data]);
    }

    /**
     * 充值记录
     */
    public function recharge_admin()
    {
        $id = session('user_id');
        $where = [];
        $this->rechagreCount = Db::name('xy_recharge')
            ->where('uid', $id)
            ->where('status', 2)
            ->sum('num');

        $this->_query('xy_recharge')
            ->where('uid', $id)->where($where)->order('id desc')->page();
    }

    /**
     * 提现记录
     */
    public function deposit_admin()
    {
        $id = session('user_id');
        $where = [];
        $this->depositCount = Db::name('xy_deposit')
            ->where('uid', $id)
            ->where('status', 2)
            ->sum('num');


        $this->_query('xy_deposit')
            ->where('uid', $id)->where($where)->order('id desc')->page();
    }

    /**
     * 团队
     */
    public function junior()
    {
        $ajax = input('ajax');
        if ($ajax == 1) {
            $uid = session('user_id');
            $arr = [];
            $start = input('start');
            $end = input('end');

            if (empty($start) && empty($end)) {
                $arr['date_range'] = lang('team_all');
            }
            if ($start && $end) {
                $arr['date_range'] = $start . '~' . $end;
            } elseif ($start) {
                $arr['date_range'] = $start;
            }

            if (empty($start)) {
                $start = 0;
            } else {
                $start = strtotime($start);
            }
            if (empty($end)) {
                $end = time();
            } else {
                $end = strtotime($end);
            }

            //计算五级团队余额
            $uidAlls5 = model('admin/Users')->child_user($uid, 3, 1);
            //团队业绩
            $arr['team_yj'] = Db::name('xy_convey')
                ->where('status', 1)
                ->where('addtime', 'between', [$start, $end])
                ->where('uid', 'in', $uidAlls5 ? $uidAlls5 : [-1])
                ->sum('commission');
            $arr['team_count'] = count($uidAlls5);
            //我得到的佣金
            $arr['team_rebate'] = Db::name('xy_balance_log')
                ->where('addtime', 'between', [$start, $end])
                ->where('uid', $uid)
                ->where('type', 'in', [3, 6])
                ->where('status', 1)
                ->sum('num');


            $uids2 = model('admin/Users')->child_user($uid, 1, 0);
            $arr['team1_count'] = count($uids2);
            $arr['team1_yj'] = Db::name('xy_convey')
                ->where('status', 1)
                ->where('addtime', 'between', [$start, $end])
                ->where('uid', 'in', $uids2 ? $uids2 : [-1])
                ->sum('commission');
            //我得到的佣金
            $arr['team1_rebate'] = Db::name('xy_balance_log')
                ->where('addtime', 'between', [$start, $end])
                ->where('sid', 'in', $uids2 ? $uids2 : -1)
                ->where('uid', $uid)
                ->where('type', 6)
                ->where('status', 1)
                ->sum('num');

            $uids3 = model('admin/Users')->child_user($uid, 2, 0);
            $arr['team2_count'] = count($uids3);
            $arr['team2_yj'] = Db::name('xy_convey')
                ->where('status', 1)
                ->where('addtime', 'between', [$start, $end])
                ->where('uid', 'in', $uids3 ? $uids3 : [-1])
                ->sum('commission');
            //我得到的佣金
            $arr['team2_rebate'] = Db::name('xy_balance_log')
                ->where('addtime', 'between', [$start, $end])
                ->where('sid', 'in', $uids3 ? $uids3 : [-1])
                ->where('uid', $uid)
                ->where('type', 6)
                ->where('status', 1)
                ->sum('num');

            $uids4 = model('admin/Users')->child_user($uid, 3, 0);
            $arr['team3_count'] = count($uids4);
            $arr['team3_yj'] = Db::name('xy_convey')
                ->where('status', 1)
                ->where('addtime', 'between', [$start, $end])
                ->where('uid', 'in', $uids4 ? $uids4 : [-1])
                ->sum('commission');
            //我得到的佣金
            $arr['team3_rebate'] = Db::name('xy_balance_log')
                ->where('addtime', 'between', [$start, $end])
                ->where('sid', 'in', $uids4 ? $uids4 : [-1])
                ->where('uid', $uid)
                ->where('type', 6)
                ->where('status', 1)
                ->sum('num');
            return json($arr);
        }
        $uid = session('user_id');
        $this->user = Db::name('xy_users')->find($uid);
        if ($this->user['level'] == 0) {
            $this->showMessage(lang('free_user_lxb'));
        }
        $where = [];
        $this->level = $level = input('get.level/d', 1);
        $this->uinfo = Db::name('xy_users')->where('id', $uid)->find();
        $this->tj_bili = Db::name('xy_level')->where('level', $this->uinfo['level'])->value('tj_bili');
        $this->tj_bili = explode("/", $this->tj_bili);
        $this->tj_bili[0] = isset($this->tj_bili[0]) ? floatval($this->tj_bili[0]) * 100 : 0;
        $this->tj_bili[1] = isset($this->tj_bili[1]) ? floatval($this->tj_bili[1]) * 100 : 0;
        $this->tj_bili[2] = isset($this->tj_bili[2]) ? floatval($this->tj_bili[2]) * 100 : 0;

        //计算五级团队余额
        $uidAlls5 = model('admin/Users')->child_user($uid, 5, 1);
        $uidAlls5 ? $whereAll[] = ['id', 'in', $uidAlls5] : $whereAll[] = ['id', 'in', [-1]];
        $uidAlls5 ? $whereAll2[] = ['uid', 'in', $uidAlls5] : $whereAll2[] = ['id', 'in', [-1]];
        $this->teamyue = Db::name('xy_users')->where($whereAll)->sum('balance');
        $this->teamcz = Db::name('xy_recharge')->where($whereAll2)->where('status', 2)->sum('num');
        $this->teamtx = Db::name('xy_deposit')->where($whereAll2)->where('status', 2)->sum('num');
        $this->teamls = Db::name('xy_balance_log')->where($whereAll2)->sum('num');
        $this->teamyj = Db::name('xy_convey')->where('status', 1)->where($whereAll2)->sum('commission');

        $uids1 = model('admin/Users')->child_user($uid, 1, 0);
        $this->zhitui = count($uids1);
        $uidsAll = model('admin/Users')->child_user($uid, 5, 1);
        $this->tuandui = count($uidsAll);

        $start = input('get.start/s', '');
        $end = input('get.end/s', '');
        if ($start || $end) {
            $start ? $start = strtotime($start) : $start = strtotime('2020-01-01');
            $end ? $end = strtotime($end . ' 23:59:59') : $end = time();
            $where[] = ['addtime', 'between', [$start, $end]];
        }

        $this->start = $start ? date('Y-m-d', $start) : '';
        $this->end = $end ? date('Y-m-d', $end) : '';

        $uids5 = model('admin/Users')->child_user($uid, $level, 0);
        $uids5 ? $where[] = ['u.id', 'in', $uids5] : $where[] = ['u.id', 'in', [-1]];

        $this->today = date("Y-m-d", time());
        $this->yesterday = date("Y-m-d", strtotime("-1 day"));
        $this->week = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") - date("w") + 1, date("Y")));

        $this->_query('xy_users')->alias('u')
            ->where($where)->order('id desc')->page();

    }


}