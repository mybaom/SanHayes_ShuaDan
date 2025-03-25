<?php

namespace app\api\controller;

use app\api\pay\Trcpay;
use think\App;
use think\Controller;
use think\Exception;
use think\Request;
use think\Db;
use think\View;

class My extends Base
{
    /**
     * 修改用户信息
     */
    public function edit_user(){
        if (!request()->isPost()) return json(['code' => 1, 'info' => lang('qqcw')]);
        $username = input('post.username/s', '');
        $address = input('post.address/s', '');
        $old_pwd = input('post.old_pwd/s', '');
        $pwd = input('post.pwd/s', '');
        $uid = $this->_uid;
        $res = ['code' => 0, 'info' => lang('czcg')];
        if ($username){
            $data['username'] = $username;
        }else if ($address){
            $data['address'] = $address;
            $row  = Db::name('xy_users')->where('id','<>',$uid)->where('address',$address)->find();
            if($row){
                return json(['code' => 1, 'info' => lang('Address already exists')]);
            }
            if (strlen($address) !=34) {
                return json(['code' => 1, 'info' => lang('The address is incorrect')]);
            }
        }else if ($old_pwd){
            $info = Db::name('xy_users')->field('tel,pwd,salt')->find($uid);
            if ($info['pwd'] != sha1($old_pwd . $info['salt'] . config('pwd_str')))             {
                return json(['code' => 1, 'info' => lang('Password error')]);
            }
            $res = model('admin/Users')->reset_pwd($info['tel'], $pwd);
            return json($res);
        }
         Db::name('xy_users')->where('id',$uid)->update($data);
        return json($res);
    }
    /**
     * 获取个人信息
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_userinfo()
    {
        $info = Db::name('xy_users')
            ->field('username,tel,level,id,agent_id,xyf,headpic,balance,freeze_balance,lixibao_balance,invite_code,show_td,deal_count,gender,address,login_time')
            ->find($this->_uid);
//        if (!$info['headpic']) {
//            $info['headpic'] = '/avatar.png';//默认头像
//        }
//        $info['headpic'] = $info['headpic'];
        // 获取当日佣金
        $addtime = strtotime(date('Y-m-d 00:00:00'));
        // if($info['deal_count']<=60&& $info['deal_count']>0){
        //     $conveyInfo = Db::name('xy_convey')->field('addtime')
        //         ->where('uid', $this->_uid)
        //         ->where('deal_count', 1)
        //         ->where('status', 'in', [1, 3, 5])
        //         ->order('oid desc')
        //         ->find();
        //     if($conveyInfo) {
        //         $addtime = $conveyInfo['addtime'];
        //     }
        // }
        $commission = Db::name('xy_convey')->where(['uid' => $this->_uid, 'status' => 1])->where('addtime', '>=', $addtime)->sum('commission');

        $info['today_win'] = $commission;
        $info['miitbeian'] = sysconf('miitbeian');
        $info['master_cardnum'] = sysconf('master_cardnum');
        $info['fees'] = sysconf('fees') / 100;
        // 获取总佣金
        $sum_commission = Db::name('xy_balance_log')->where(['type' => 3, 'uid' => $this->_uid, 'status' => 1])->sum('num');
        $info['sum_commission'] = $sum_commission;

        $info['notice'] = [
            ['content'=> "User[+3587880****] made $150.04 today"],
            ['content'=> "User[+1556789****] made $1000.00 today"],
            ['content'=> "User[+6854336****] made $745.53 today"],
            ['content'=> "User[+1122459****] made $736542 today"],
            ['content'=> "User[+5468456****] made $74645 today"],
            ['content'=> "User[+51435459****] made $260.00 today"],
            ['content'=> "User[+1125458****] made $2452.32 today"],
            ['content'=> "User[+8534211****] made $980.04 today"],
            ['content'=> "User[+6834536****] made $423.74 today"],
            ['content'=> "User[+4536684****] made $535.04 today"],
            ['content'=> "User[+4534556****] made $500.00 today"],
        ];
        $info['recharge'] = Db::name('xy_recharge')->field('id,qr,pay_address as address,num2 as money,addtime')->where('uid',$this->_uid)->where('addtime','>',time()-600)->where('status',1)->find();
        if(time() - $info['login_time']>1200){
             $res = Db::table('xy_users')->where('id',$this->_uid)->update(['login_time'=>time(), 'login_status' => 1]);
        }
        
        return $this->success('success', $info);

    }

    /**
     * 客服列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_customer()
    {
        $services = Db::name('xy_cs')->field('username,btime,etime,url')->where(['status' => 1])->select();
        if ($services) {
            return $this->success('success', $services, 0);
        }

    }

    /**
     * 修改登录密码
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function set_pwd()
    {
        if (!request()->isPost()) return json(['code' => 1, 'info' => lang('qqcw')]);
        $uid = $this->_uid;
        $o_pwd = input('old_pwd/s', '');
        $pwd = input('new_pwd/s', '');
        $pwd2 = input('new_pwd2/s', '');
        if ($pwd != $pwd2) return json(['code' => 1, 'info' => lang('pass_two_error')]);
        $type = 1;//登录密码
        $uinfo = Db::name('xy_users')->field('pwd,salt,tel')->find($uid);
        if ($uinfo['pwd'] != sha1($o_pwd . $uinfo['salt'] . config('pwd_str'))) return json(['code' => 1, 'info' => lang('pass_error')]);
        $res = model('admin/Users')->reset_pwd($uinfo['tel'], $pwd, $type);
        return json($res);
    }
    /**
     * 团队
     */
    public function teamList(){
        $uid = $this->_uid;
        $data = Db::name('xy_users')->where('relation_id', $uid)->field('id,username,deal_count')->select();
        foreach ($data as $k=> $v){
            $data[$k]['commission'] = Db::name('xy_convey')->where('uid',$v['id'])->where('p_status',1)->sum('parent_commission');
        }
        $parent_commission = Db::name('xy_convey')->field('uid,addtime')->where('parent_uid',$uid)->where('p_status',1)->sum('parent_commission');
        $arr['list'] = $data;
        $arr['commission'] = round($parent_commission,2);
        return $this->success('success', $arr, 0);

    }
    /**
     * 领取佣金
     */
    public function receive_commission(){
        if (!request()->isPost()) return json(['code' => 1, 'info' => lang('qqcw')]);
        $uid = $this->_uid;
        $commission = input('commission/s', '');
        $result = Db::name('xy_convey')->field('uid,addtime')->where('parent_uid',$uid)->where('p_status',1)->where('deal_count',60)->select();
        $parent_commission = 0;
        foreach ($result as  $vv){
            $r = Db::name('xy_convey')->where('uid',$vv['uid'])->where('p_status',1)->where('addtime','<=',$vv['addtime'])->sum('parent_commission');
            $parent_commission = $parent_commission+$r;
        }

        if ($commission != round($parent_commission,2)){
            return $this->error('fail');
        }
        foreach ($result as  $vv){
            $r = Db::name('xy_convey')->where('uid',$vv['uid'])->where('p_status',1)->where('addtime','<=',$vv['addtime'])->update(['p_status'=>2]);

        }
        if ($parent_commission>0){
            $res2 = Db::name('xy_balance_log')->insert([
                'uid' => $uid,
                'sid' => 1,
                'oid' => 1,
                'num' => $parent_commission,
                'type' => 6,
                'status' => 1,
                'addtime' => time()
            ]);
            Db::name('xy_users')->where('id', $uid)->setInc('balance', $parent_commission);
        }
        return $this->success('success', [], 0);
    }

    // 修改提现密码
    public function set_pwd2()
    {
        if (!request()->isPost()) return json(['code' => 1, 'info' => lang('qqcw')]);
        $uid = $this->_uid;
        $o_pwd = input('old_pwd/s', '');
        $pwd = input('new_pwd/s', '');
        $pwd2 = input('new_pwd2/s', '');
        if ($pwd != $pwd2) return json(['code' => 1, 'info' => lang('pass_two_error')]);
        $type = 2;//提现密码
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

    //更新个人资料
    public function up_userinfo()
    {
        $headpic = input('post.headpic/s', '');
        $uid = $this->_uid;
        $res = Db::name('xy_users')->where('id', $uid)->update(['headpic' => $headpic]);
        if ($res !== false) {
            return $this->success('success');
        }
        return $this->error('fail');
    }

    //用户动账密码  充值和提现
    public function transaction()
    {
        $id = $this->_uid;
        $page = input('post.page/d', 1);
        $num = input('post.num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $where = [];
        $res = Db::name('xy_balance_log')
            ->where('uid', $id)
            ->where('type', 'in', [1,3,6,7])
            ->order('id desc')
            ->paginate($num)
            ->each(function ($item, $key) {
                $item['addtime'] = date('Y/m/d H:i:s', $item['addtime']);
                return $item;
            });
        if ($res) {
            $this->success('', $res);
        }
        $this->error(lang('zwsj'));

    }

    //上传头像
    public function up_headimg()
    {
        $uid = $this->_uid;
        if (request()->isPost()) {
            $pic = input('post.pic/s', '');
            $pic = $this->upload('headimg');  //调用图片上传的方法
            $res = Db::name('xy_users')->where('id', $uid)->update(['headpic' => $pic]);
            if ($res !== false) {
                return json(['code' => 0, 'info' => lang('czcg'), 'data' => $pic]);
            } else {
                return json(['code' => 1, 'info' => lang('czsb')]);
            }
        }
    }
    /**
     * 查询充值详情
     */
    public function recharge_info(){
        $uid = $this->_uid;
        $oid = input('post.id', 0);
        $oinfo = Db::name('xy_recharge')->where('addtime','>',time()-600)->find($oid);
        return json(['code' => 0, 'info' => lang('czcg'),'data'=>$oinfo]);
    }


    /**
     * 用户账号充值
     */
    public function recharge(){
        $res = check_time(config('chongzhi_time_1'), config('chongzhi_time_2'));
        $str = config('chongzhi_time_1') . ":00  - " . config('chongzhi_time_2') . ":00";


        if ($res) return json(['code' => 1, 'info' => lang('task_worktime') . $str]);
        // $row = Db::name('xy_recharge')->field('id,qr,pay_address as address,num2 as money,addtime')->where('uid',$this->_uid)->where('addtime','>',time()-600)->where('status',1)->find();
        // if ($row){
        //     return json(['code' => 0, 'info' => lang('czcg'),'data'=>$row]);
        // }

        $num = input('post.num', 0);
        if ($num <=0){
            return json(['code' => 1, 'info' => lang('czsbqshcs')]);
        }
        $uid = $this->_uid;
        $uinfo = Db::name('xy_users')->field('pwd2,salt2,tel')->find($uid);
        $trc_pay = new Trcpay();
        $id = getSn('SY');
        $res = $trc_pay->create_order(['sn'=>$id,'money'=>$num]);

        if ($res['code'] ==1){

            db('xy_recharge')
                ->insert([
                    'id' => $id,
                    'uid' => $uid,
                    'tel' => $uinfo['tel'],
                    'num' => $num,
                    'num2' => $res['data']['money'],
                    'pay_address' => $res['data']['address'],
                    'sn' => $res['data']['order_num'],
                    'qr' => $res['data']['qr'],
                    'addtime' => time()
                ]);
            $res['data']['id'] = $id;
            $res['data']['addtime'] = time();
            return json(['code' => 0, 'info' => lang('czcg'),'data'=>$res['data']]);
        }else{
            return json(['code' => 1, 'info' => lang('czsbqshcs')]);
        }
    }

    /**
     * 取消充值订单
     */
    public function qx_recharge(){
        $uid = $this->_uid;
        $oid = input('post.id', 0);
        $oinfo = Db::name('xy_recharge')->find($oid);
        if ($oinfo && $oinfo['status'] ==1){
            $res = Db::name('xy_recharge')->where('uid',$uid)->where('id', $oid)->update(['endtime' => time(), 'status' => 3]);
        }
        return json(['code' => 0, 'info' => lang('czcg')]);
    }
    public function user_recharge()
    {

        $res = check_time(config('chongzhi_time_1'), config('chongzhi_time_2'));
        $str = config('chongzhi_time_1') . ":00  - " . config('chongzhi_time_2') . ":00";


        if ($res) return json(['code' => 1, 'info' => lang('task_worktime') . $str]);

        $num = input('post.num/d', 0);
        $real_name = input('post.real_name/s', '');
        $pic = input('post.pic/s', '');
        $type = input('post.type/d', 1);
        $uid = $this->_uid;
        $uinfo = Db::name('xy_users')->field('pwd2,salt2,tel')->find($uid);
        $id = getSn('SY');
        $res = db('xy_recharge')
            ->insert([
                'id' => $id,
                'uid' => $uid,
                'tel' => $uinfo['tel'],
                'real_name' => $real_name,
                'pic' => $pic,
                'num' => $num,
                'type' => $type,
                'addtime' => time()
            ]);
        if ($res)
            return json(['code' => 0, 'info' => lang('czcg')]);
        else
            return json(['code' => 1, 'info' => lang('czsbqshcs')]);
    }

    //充值记录
    public function rechargeLog()
    {
        $uid = $this->_uid;
        $page = input('post.page/d', 1);
        $num = input('post.num/d', 10);
        $limit = ((($page - 1) * $num) . ',' . $num);
        $where[] = ['uid', '=', $uid];
//        $where[] = ['status', '=', 2];
        $up = Db::name('xy_recharge')
            ->where($where)
            ->order('id', 'desc')
            ->paginate($num)
            ->each(function ($item, $key) {
                $item['addtime'] = date('Y/m/d H:i:s', $item['addtime']);
                return $item;
            });
        return json(['code' => $up ? 0 : 1, 'data' => $up]);
    }

    //系统头像列表
    public function headlist()
    {
        $result = [];
        for ($i = 1; $i <= 40; $i++) {
            $result[] = request()->domain() . '/headimg/' . $i . '.png';
        }
        $this->success('', $result);;
    }
    //删除银行卡信息
    public function del_bank(){
        $id = input('post.id/d', 0);
        $uid = $this->_uid;
        $info = Db::name('xy_bankinfo')->where('uid', $uid)->where('id',$id)->find();
        if($info){
            Db::name('xy_bankinfo')->where('uid', $uid)->where('id',$id)->delete();
        }
        $this->success(lang('czcg'));
    }

    //绑定银行卡信息
    public function bind_bank()
    {
        $id = input('post.id/d', 0);
        $uid = $this->_uid;
        $uinfo = Db::name('xy_users')->find($uid);

        if (request()->isPost()) {

            $username = input('post.username/s', '');
            $cardnum = input('post.cardnum/s', '');
            $bankname = input('post.bankname/s', '');
            $bank_type = input('post.bank_type/d', 1);

            //同一姓名和卡号只绑定一次
            $res = Db::name('xy_bankinfo')
                ->where('username', $username)
                ->where('cardnum', $cardnum)
                ->find();
            // if ($res && $res['uid'] != $uid) {
            //     return json(['code' => 1, 'info' => lang('bind_bank_err')]);
            // }
            $data = array(
                'username' => $username,
                'bank_type'=>$bank_type,
                'bankname' => $bankname ,
                'cardnum' => $cardnum,
                'status' => 1
            );
            $data['uid'] = $uid;
                $res = Db::name('xy_bankinfo')->insert($data);
            if ($res) {
                $this->success(lang('czcg'));
            } else {
                $this->error(lang('czsb'));

            }
        }
    }

    //银行卡列表
    public function bank_lists(){
//        $infoslist = Db::name('xy_bank_list')->select();
        $fileurl = APP_PATH . "../config/bank.txt";
        $infoslist = file_get_contents($fileurl);
        $this->success('success', $infoslist);
    }

    // 银行卡信息
    public function getbankinfo()
    {
        $infos = Db::name('xy_bankinfo')->where('uid', $this->_uid)->select();
        return $this->success('success', $infos);
    }
}