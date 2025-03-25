<?php

namespace app\admin\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;
use think\Db;

class Users extends Model
{
    protected $table = 'xy_users';
    protected $rule = [
        'tel' => 'require',
        'username' => 'require',
        //'username' => 'require|length:3,15',
        'pwd' => 'require|length:6,16',
        '__token__' => 'token',
    ];
    protected $info = [
        'tel.require' => '手机号不能为空！',
        //'tel.mobile' => '手机号格式错误！',
        'username.length' => '用户名长度为3-10字符！',
        'username.require' => '用户名不能为空！',
        'pwd.require' => '密码不能为空！',
        'pwd.length' => '密码长度为6-16位字符！',
        '__token__' => '令牌已过期，请刷新页面再试！',
    ];

    protected function initialize()
    {
        parent::initialize();
        $this->info['tel.require'] = lang('tel_none');
        $this->info['username.length'] = lang('username_len');
        $this->info['username.require'] = lang('username_none');
        $this->info['username.require'] = lang('username_none');
        $this->info['pwd.require'] = lang('login_pass');
        $this->info['pwd.length'] = lang('pwd_length');
    }


    /**
     * 获取登陆后台代理id
     * @return int
     */
    public function get_admin_agent_id()
    {
        $user = session('admin_user');
        if (!empty($user) && $user['authorize'] == 2 && !empty($user['nodes'])) {
            return $user['id'];
        }
        return 0;
    }

    /**
     * 获取当前登陆账号下级的uid
     * @return int
     */
    public function get_admin_agent_uid()
    {
        $user = session('admin_user');
        if (!empty($user) && $user['authorize'] == 2 && !empty($user['nodes'])) {
            return $user['user_id'];
        }
        return 0;
    }

    /**
     *获取用户订单数量
     * @param $uid
     * @return int
     */
    public function get_user_order_num($uid)
    {
        $where['uid'] = $uid;
        $user_order = db('xy_convey')->where($where)->where('addtime', 'between', [strtotime(date('Y-m-d')), time()])->field('id')->select();
        return count($user_order);
    }

    private $_agent_id = 0;

    /**
     * 获取当前域名代理id
     * @return int
     */
    public function get_agent_id()
    {
        if ($this->_agent_id) return $this->_agent_id;
        $aid = 0;
        $sub = request()->subDomain();
        if ($sub) {
            $user = Db::name('system_user')->where('username', $sub)->find();
            if (!empty($user) && $user['authorize'] == 2) {
                $aid = $user['id'];
            }
        }
        $this->_agent_id = intval($aid);
        return $this->_agent_id;
    }

    /**
     * 检查用户是否和代理地址匹配
     * @return bool
     */
    public function check_user_is_agent_id($uid)
    {
        $aid = $this->get_agent_id();
        $u_aid = Db::name('xy_users')->where('id', $uid)->value('agent_id');
        return $u_aid == $aid;
    }

    /**
     * 充值审核通过
     * @param int $oid
     * @param int $source 来源0=系统操作，1=支付回掉
     * @return bool
     */
    public function recharge_success($oid, $source = 0)
    {
        $oinfo = Db::name('xy_recharge')->find($oid);
        if($oinfo['status'] ==2 ){
            return true;
        }

        Db::startTrans();

        $status = 2;
        $upArr = ['endtime' => time(), 'status' => $status];
        if ($source == 1) {
            $upArr['pay_status'] = 1;
        }
        $res = Db::name('xy_recharge')
            ->where('id', $oid)
            ->update($upArr);
        $res1 = Db::name('xy_users')
            ->where('id', $oinfo['uid'])
            ->setInc('balance', $oinfo['num']);
        $res4 = Db::name('xy_users')
            ->where('id', $oinfo['uid'])
            ->update([
                'all_recharge_num' => Db::raw('all_recharge_num+' . $oinfo['num']),
                'all_recharge_count' => Db::raw('all_recharge_count+1'),
            ]);

        $res2 = Db::name('xy_balance_log')
            ->insert(['uid' => $oinfo['uid'], 'oid' => $oid, 'num' => $oinfo['num'], 'type' => 1, 'status' => 1, 'addtime' => time()]);
        //推荐人给钱 ///第一次才给
//        if (!$is_first) {
//            $t_money = floatval($oinfo['num'] * config('invite_recharge_money'));
//            if ($t_money > 0) {
//                Db::name('xy_users')
//                    ->where('id', $user['parent_id'])
//                    ->setInc('balance', $t_money);
//                Db::name('xy_balance_log')
//                    ->insert(['uid' => $user['parent_id'], 'oid' => $oid, 'num' => $t_money, 'type' => 5, 'status' => 1, 'addtime' => time()]);
//            }
//        }
        if ($res && $res1  && $res4) {
            Db::commit();
            return true;
        }
        Db::rollback();
        return false;
    }

    /**
     * 添加会员
     *
     * @param string $tel
     * @param string $user_name
     * @param string $pwd
     * @param int $parent_id
     * @param string $token
     * @param string $pwd2
     * @param string $ip
     * @param int $agent_id
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function add_users($tel, $user_name, $pwd, $parent_id, $token = '', $pwd2 = '', $agent_id = 0, $ip = '', $pwd3 = '', $yqm = '',$is_jia=0)
    {
        $tmp = Db::table($this->table)->where(['tel' => $tel])->count();
        if ($tmp) {
            return ['code' => 1, 'info' => lang('sjhmyzc')];
        }
        $tmp = Db::table($this->table)->where(['username' => $user_name])->count();
        if ($tmp) {
           //return ['code' => 1, 'info' => lang('username_exists')];
        }
        if (!$user_name) $user_name = get_username();
        $data = [
            'tel' => $tel,
            'username' => $user_name,
            'pwd' => $pwd,
            'parent_id' => $parent_id,
        ];
        if ($token) $data['__token__'] = $token;

        //验证表单
        $validate = \Validate::make($this->rule, $this->info);
        if (!$validate->check($data)) {
            return ['code' => 1, 'info' => $validate->getError()];
        }
        if ($parent_id) {
            $parent_id = Db::table($this->table)->where('id', $parent_id)->value('id');
            if (!$parent_id) {
                return ['code' => 1, 'info' => lang('sjidbcz')];
            }
        }
        $data['ip'] = Ipv6tov4($ip);
        $ip_register_number = intval(config('ip_register_number'));
        if ($ip_register_number > 0 && $ip) {
            $uIpId = Db::table($this->table)->where('ip', $ip)->count('id');
            if ($uIpId > $ip_register_number) {
                return ['code' => 1, 'info' => lang('reg_ip_error')];
            }
        }

        $salt = rand(0, 99999);  //生成盐
        $invite_code = self::create_invite_code();//生成邀请码

        $ft = config('free_balance_time');
        $ft = floatval($ft) * 3600;
        //给体验账户加体验金
        if ($yqm) {

            $pid = Db::table($this->table)->where('invite_code', $yqm)->find();
            $data['parent_id'] = $pid['id'];
        }

        $data['agent_id'] = $agent_id;
        $data['level'] = 0;
        $data['is_clean_free'] = time() + $ft;
        $data['balance'] = config('free_balance');

        $data['is_jia'] = $is_jia;
        $data['pwd'] = sha1($pwd . $salt . config('pwd_str'));
        $data['salt'] = $salt;
        $data['addtime'] = time();
        $data['invite_code'] = $invite_code;
        if ($pwd2) {
            $salt2 = rand(0, 99999);  //生成盐
            $data['pwd2'] = sha1($pwd2 . $salt2 . config('pwd_str'));
            $data['salt2'] = $salt2;
        }
        //判断ip不为空 查询所属国家
        if (!empty($data['ip'])) {
            $data['country'] = '';
            $ip = $data['ip'];//127.0.0.1 | 192.168.1.1 | 2407:cdc0:b02d::1039 |2603:8000:d903:f79:4cbb:7a27:b142:56ac
            //判断ip格式  ipv4和ipv6
            if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            	// 指定 IP数据库，在调用代码中引用该文件：
                $reader = new \GeoIp2\Database\Reader(APP_PATH.'../config/GeoLite2-Country.mmdb');
                $record = $reader->country($ip);
            }
            if (!empty($record)) {
                $data['country'] = $record->country->names['zh-CN'];
            }
        }
        //return ['code' => 1, 'info' => lang('czsb'),'ddd'=>$data];
        //开启事务
        unset($data['__token__']);
        Db::startTrans();
        $res = Db::table($this->table)->insertGetId($data);
        if ($parent_id) {
            $res2 = Db::table($this->table)->where('id', $data['parent_id'])->update([
                'childs' => Db::raw('childs+1'),
                'deal_reward_count' => Db::raw('deal_reward_count+' . config('deal_reward_count')),
                'balance' => Db::raw('balance+' . config('invite_one_money'))
            ]);
            Db::name('xy_balance_log')->insert([
                'uid' => $data['parent_id'],
                'sid' => $res,
                'oid' => '',
                'num' => config('invite_one_money'),
                'type' => 5,
                'status' => 1,
                'addtime' => time()
            ]);
        } else {
            $res2 = true;
        }
        //生成二维码
        self::create_qrcode($invite_code, $res);
        if ($res && $res2) {
            //生成关系链
            $this->update_user_invites($res);
            // 提交事务
            Db::commit();
            //更新用户service_id
            $s = $this->get_user_service_id($res);
            if (!empty($s['id'])) {
                // Db::table($this->table)
                //     ->where('id', $res)
                //     ->update(['agent_service_id' => $s['id']]);
            }

            return ['code' => 0, 'info' => lang('reg_ok'), 'id' => $res];
        } else
            // 回滚事务
            Db::rollback();
        return ['code' => 1, 'info' => lang('czsb')];
    }

    /**
     * 更新或创建用户关系链
     *
     * @param int $uid 用户编号
     * @param bool $isUpdate 是否强制更新
     * @return bool
     * @throws \think\Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws \think\exception\PDOException
     */
    public function update_user_invites($uid, $isUpdate = false)
    {
        $uInvites = Db::table('xy_users_invites')->where('uid', $uid)->find();
        if (empty($uInvites['uid']) || $isUpdate) {
            //么有就创建
            $uLevel = $this->re_user_invites_uids($uid);
            $uLevel[0] = trim($uLevel[0], ',');
            if (empty($uInvites['uid'])) {
                Db::table('xy_users_invites')->insert([
                    'uid' => $uid,
                    'level' => $uLevel[1],
                    'ids' => $uLevel[0]
                ]);
            } else {
                Db::table('xy_users_invites')->where('uid', $uid)->update([
                    'level' => $uLevel[1],
                    'ids' => $uLevel[0]
                ]);
            }
        }
        return true;
    }

    /**
     * 获取用户关系链 及 代数
     *
     * @param int $uid 用户编号
     * @param int $lv 代数
     * @param string $uids 关系链
     * @return array   [关系链，代数]
     */
    private function re_user_invites_uids($uid, $lv = 0, $uids = '')
    {
        $lv = $lv + 1;
        $uids = $uid . ',' . $uids;
        $parent_id = Db::name($this->table)->where('id', $uid)->value('parent_id');
        if ($parent_id > 0) {
            return $this->re_user_invites_uids($parent_id, $lv, $uids);
        }
        //如果信息不存在 那么就是 id和1代
        return [$uids, $lv];
    }

    /**
     * 编辑用户
     *
     * @param int $id
     * @param string $tel
     * @param string $user_name
     * @param string $pwd
     * @param int $parent_id
     * @param $balance
     * @param $freeze_balance
     * @param string $token
     * @param string $pwd2
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function edit_users($id, $tel, $user_name, $pwd, $parent_id, $balance, $freeze_balance, $token, $pwd2 = '')
    {
        $tmp = Db::table($this->table)->where(['tel' => $tel])->where('id', '<>', $id)->count();
        if ($tmp) {
            return ['code' => 1, 'info' => lang('sjhmyzc')];
        }
        $data = [
            'tel' => $tel,
            'balance' => $balance,
            'freeze_balance' => $freeze_balance,
            'username' => $user_name,
            'parent_id' => $parent_id,
            '__token__' => $token,
        ];
        if ($pwd) {
            //不提交密码则不改密码
            $data['pwd'] = $pwd;
        } else {
            $this->rule['pwd'] = '';
        }
        if ($parent_id) {
            $parent_id = Db::table($this->table)->where('id', $parent_id)->value('id');
            if (!$parent_id) {
                return ['code' => 1, 'info' => lang('sjidbcz')];
            }
            $data['parent_id'] = $parent_id;
        }

        $validate = \Validate::make($this->rule, $this->info);//验证表单
        if (!$validate->check($data)) return ['code' => 1, 'info' => $validate->getError()];

        if ($pwd) {
            $salt = rand(0, 99999); //生成盐
            $data['pwd'] = sha1($pwd . $salt . config('pwd_str'));
            $data['salt'] = $salt;
        }
        if ($pwd2) {
            $salt2 = rand(0, 99999); //生成盐
            $data['pwd2'] = sha1($pwd2 . $salt2 . config('pwd_str'));
            $data['salt2'] = $salt2;
        }
        unset($data['__token__']);
        $res = Db::table($this->table)->where('id', $id)->update($data);
        if ($res)
            return ['code' => 0, 'info' => lang('czcg')];
        else
            return ['code' => 1, 'info' => lang('czsb')];
    }

    public function edit_users_status($id, $status)
    {
        $status = intval($status);
        $id = intval($id);

        if (!in_array($status, [1, 2])) return ['code' => 1, 'info' => lang('cscw')];

        if ($status == 2) {
            //查看有无未完成的订单
            // if($num > 0)$this->error('该用户尚有未完成的支付订单！');
        }
        $data = [
            'status' => $status,
            'deal_count'=>0
        ];
        $res = Db::table($this->table)->where('id', $id)->update($data);
        if ($res !== false)
            return ['code' => 0, 'info' => lang('czcg')];
        else
            return ['code' => 1, 'info' => lang('czsb')];
    }

    //生成邀请码
    public static function create_invite_code()
    {
//        $str = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
//        $str = '0123456789';
//        $rand_str = substr(str_shuffle($str), 0, 6);
        $length = 5;
        $number = mt_rand(1, 9); // 避免首位为0
        $number .= str_pad(mt_rand(0, 9 * pow(10, $length - 1) - 1), $length - 1, '0', STR_PAD_LEFT);
        $rand_str = $number;
        $num = Db::table('xy_users')->where('invite_code', $rand_str)->count();
        if ($num)
            // return $this->create_invite_code();
            return self::create_invite_code();
        else
            return $rand_str;
    }

    //生成用户二维码
    public static function create_qrcode($invite_code, $user_id)
    {
        return;
        $n = ($user_id % 20);

        $dir = './upload/qrcode/user/' . $n . '/' . $user_id . '.png';
        if (file_exists($dir)) {
            return;
        }

        $qrCode = new \Endroid\QrCode\QrCode(SITE_URL . url('@index/user/register/invite_code/' . $invite_code));
        //设置前景色
        $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        //设置背景色
        $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
        //设置二维码大小
        $qrCode->setSize(230);
        $qrCode->setPadding(5);
        $qrCode->setLogoSize(40);
        $qrCode->setLabelFontSize(14);
        $qrCode->setLabelHalign(100);

        $dir = './upload/qrcode/user/' . $n;
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $qrCode->save($dir . '/' . $user_id . '.png');

        $qr = \Env::get('root_path') . 'public/upload/qrcode/user/' . $n . '/' . $user_id . '.png';
        $bgimg1 = \Env::get('root_path') . 'public/public/img/userqr1.png';

        $image = \think\Image::open($bgimg1);
        $image->water($qr, [255, 743])->text($invite_code, \Env::get('root_path') . 'public/public/fz.TTF', 22, '#ffffff', [(678 - (24 * strlen($user_id))) / 2, 685])->save(\Env::get('root_path') . 'public/upload/qrcode/user/' . $n . '/' . $user_id . '-1.png');
    }

    /**
     * 重置密码
     */
    public function reset_pwd($tel, $pwd, $type = 1)
    {
        $data = [
            'tel' => $tel,
            'pwd' => $pwd,
        ];
        unset($this->rule['username']);
        $validate = \Validate::make($this->rule, $this->info);//验证表单
        if (!$validate->check($data)) return ['code' => 1, 'info' => $validate->getError()];

        $user_id = Db::table($this->table)->where(['tel' => $tel])->value('id');
        if (!$user_id) {
            return ['code' => 1, 'info' => lang('not_user')];
        }

        $salt = mt_rand(0, 99999);
        if ($type == 1) {
            $data = [
                'pwd' => sha1($pwd . $salt . config('pwd_str')),
                'salt' => $salt,
            ];
        } elseif ($type == 2) {
            $data = [
                'pwd2' => sha1($pwd . $salt . config('pwd_str')),
                'salt2' => $salt,
            ];
        }

        $res = Db::table($this->table)->where('id', $user_id)->data($data)->update();

        if ($res)
            return ['code' => 0, 'info' => lang('czcg')];
        else
            return ['code' => 1, 'info' => lang('czsb')];

    }

    //获取上级会员
    public function parent_user($uid, $num = 1, $lv = 1)
    { return [];
        $pid = db($this->table)->where('id', $uid)->value('parent_id');
        $uinfo = db($this->table)->where('id', $pid)->find();
        if ($uinfo) {
            if ($uinfo['parent_id'] && $num > 1) $data = self::parent_user($uinfo['id'], $num - 1, $lv + 1);
            $data[] = ['id' => $uinfo['id'], 'pid' => $uinfo['parent_id'], 'level' => $uinfo['level'], 'lv' => $lv, 'status' => $uinfo['status']];
            return $data;
        }
        return false;
    }


    //获取下级会员
    public function child_user($uid, $num = 1, $lv = 1)
    {

        $data = [];
        $where = [];
        if ($num == 1) {
            $data = db('xy_users')->where('parent_id', $uid)->field('id')->column('id');
        } else if ($num == 2) {
            //二代
            $ids1 = db('xy_users')->where('parent_id', $uid)->column('id');
            $ids1 ? $where[] = ['parent_id', 'in', $ids1] : $where[] = ['parent_id', 'in', [-1]];
            $data = db('xy_users')->where($where)->column('id');
            $data = $lv ? array_merge($ids1, $data) : $data;
        } else if ($num == 3) {
            //三代
            $ids1 = db('xy_users')->where('parent_id', $uid)->field('id')->column('id');
            $ids1 ? $wher[] = ['parent_id', 'in', $ids1] : $wher[] = ['parent_id', 'in', [-1]];
            $ids2 = db('xy_users')->where($wher)->field('id')->column('id');
            $ids2 ? $where[] = ['parent_id', 'in', $ids2] : $where[] = ['parent_id', 'in', [-1]];
            $data = db('xy_users')->where($where)->field('id')->column('id');
            $data = $lv ? array_merge($ids1, $ids2, $data) : $data;
        } else if ($num == 4) {
            //四带
            $ids1 = db('xy_users')->where('parent_id', $uid)->field('id')->column('id');
            $ids1 ? $wher[] = ['parent_id', 'in', $ids1] : $wher[] = ['parent_id', 'in', [-1]];
            $ids2 = db('xy_users')->where($wher)->field('id')->column('id');
            $ids2 ? $where2[] = ['parent_id', 'in', $ids2] : $where2[] = ['parent_id', 'in', [-1]];
            $ids3 = db('xy_users')->where($where2)->field('id')->column('id');
            $ids3 ? $where[] = ['parent_id', 'in', $ids3] : $where[] = ['parent_id', 'in', [-1]];
            $data = db('xy_users')->where($where)->field('id')->column('id');
            $data = $lv ? array_merge($ids1, $ids2, $ids3, $data) : $data;

        } else if ($num == 5) {
            //四带
            $ids1 = db('xy_users')->where('parent_id', $uid)->field('id')->column('id');
            $ids1 ? $wher[] = ['parent_id', 'in', $ids1] : $wher[] = ['parent_id', 'in', [-1]];
            $ids2 = db('xy_users')->where($wher)->field('id')->column('id');
            $ids2 ? $where2[] = ['parent_id', 'in', $ids2] : $where2[] = ['parent_id', 'in', [-1]];
            $ids3 = db('xy_users')->where($where2)->field('id')->column('id');
            $ids3 ? $where3[] = ['parent_id', 'in', $ids3] : $where3[] = ['parent_id', 'in', [-1]];
            $ids4 = db('xy_users')->where($where3)->field('id')->column('id');
            $ids4 ? $where[] = ['parent_id', 'in', $ids4] : $where[] = ['parent_id', 'in', [-1]];
            $data = db('xy_users')->where($where)->field('id')->column('id');

            $data = $lv ? array_merge($ids1, $ids2, $ids3, $ids4, $data) : $data;
        }

        return $data;
    }

    /**
     * 获取所有下级会员
     * @param int $uid 用户编号
     * @param int $level 获取到第几代
     * @param bool $isMerge
     * @return array   返回格式: [1,2,3,4,5]
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function get_child_user($uid, $level = 1, $isMerge = true)
    {
        if ($level == 1) return $uid;
        $uInvites = Db::name('xy_users_invites')->where('uid', $uid)->find();
        $sumLevel = $uInvites['level'] + $level - 1;
        if ($isMerge) {
            $result = Db::name('xy_users_invites')
                ->where('level', '<=', $sumLevel)
                ->where('ids', 'like', $uInvites['ids'] . ',%')
                ->column('uid');
        } else {
            $result = Db::name('xy_users_invites')
                ->where('level', $sumLevel)
                ->where('ids', 'like', $uInvites['ids'] . ',%')
                ->column('uid');
        }
        $result ? $result[] = $uid : $result = [$uid];
        return $result;
    }

    /**
     * 获取会员所属 代理-客服
     * @param int $uid 用户编号
     * @return array|null   返回格式: system_user
     */
    public function get_user_service_id($uid)
    {
        //查找代理名下 客服的代码
        $mids = Db::name('xy_users_invites')->where('uid', $uid)->value('ids');
        if (!empty($mids)) {
            $service = Db::name('system_user')->where('user_id', 'in', $mids)
                ->order('id desc')
                ->limit(1)->find();
        }
        if (empty($service)) {
            //查找代理的 客服代码
            $agent_id = Db::name('xy_users')->where('id', $uid)->value('agent_id');
            if (!empty($agent_id)) {
                $service = Db::name('system_user')->where('id', $agent_id)
                    ->limit(1)->find();
            } else $service = null;
        }
        return $service ?: null;
    }

    /**
     * 提现失败 --- 付款失败回掉
     * @param array $oinfo
     * @return bool
     */
    public function payout_rollback($oinfo)
    {
        //不是成功状态 不管
        if ($oinfo['status'] != 2) {
            return true;
        }
        Db::startTrans();
        $res1 = Db::name('xy_users')->where('id', $oinfo['uid'])->setInc('balance', $oinfo['num']);
        $res2 = Db::name('xy_deposit')->where('id', $oinfo['id'])->update([
            'status' => 4,
            'endtime' => time()
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
                'content' => sprintf(lang('deposit_system_clean'), $oinfo['id']) . ' ' . (!empty($oinfo['payout_err_msg']) ? $oinfo['payout_err_msg'] : ''),
                'addtime' => time()
            ]);
        if ($res1 && $res2 && $res3) {
            Db::commit();
            return true;
        }
        Db::rollback();
        return false;
    }

    public function get_user_pipei_num_config($uid)
    {
        $uinfo = db($this->table)->where('id', $uid)->field('deal_min_num,deal_max_num,pipei_type,pipei_dan,deal_count')->find();
        $where['uid'] = $uid;
        $order_num = $uinfo['deal_count'];
        $data['num'] = $order_num + 1;
        $pipei_dan = json_decode($uinfo['pipei_dan'], true);

        $data['deal_min_num'] = $uinfo['deal_min_num'] ? $uinfo['deal_min_num'] : 0;
        $data['deal_max_num'] = $uinfo['deal_max_num'] ? $uinfo['deal_min_num'] : 0;
        $data['pipei_type'] = $uinfo['pipei_type'];
        if (!$uinfo['pipei_dan']) {
            $uinfo['pipei_dan'] = '[{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"},{"pipei_dan":"0","pipei_min":"0","pipei_max":"0"}]';
        }

        $temp = json_decode($uinfo['pipei_dan'], true);

        $array = array();
        foreach ($temp as $key => $value) {
            if ($value['pipei_dan'] == 0) {
                continue;
            } else {
                $array[$key] = $value;
            }
        }
        $pipei_danss = array_column($array, 'pipei_dan');
        if (in_array($data['num'], $pipei_danss)) {
            $pipei_min = array_column($array, 'pipei_min', 'pipei_dan');
            $pipei_max = array_column($array, 'pipei_max', 'pipei_dan');
            $data['pipei_max'] = $pipei_max[$data['num']];
            $data['pipei_min'] = $pipei_min[$data['num']];
        } else {
            $data['pipei_max'] = 0;
            $data['pipei_min'] = 0;
        }
        return $data;
    }
}
