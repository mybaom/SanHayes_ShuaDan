<?php

namespace app\api\controller;

use app\admin\model\Convey;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use library\Controller;
use think\App;
use think\facade\Cookie;
use think\facade\Config;
use think\facade\Lang;
use think\facade\Request;
use think\Db;

/**
 * 验证登录控制器
 */
class Base extends Controller
{
    protected $rule = ['__token__' => 'token'];
    protected $msg = ['__token__' => '无效token！'];
    protected $_uid;

    /**
     * 加载方法
     */
    protected function initialize()
    {
        parent::initialize();
        //解决跨域问题
        header('Access-Control-Allow-Origin:*');//允许所有来源访问
        header('Access-Control-Allow-Method:POST,GET');//允许访问的方式
        //token验证
        $this->checkToken();
    }

    function __construct(App $app)
    {
        parent::__construct($app);
        // if (config('shop_status') == 0) exit();

        //获取用户id
        $this->_uid = JWT_UID;

        //切换语言
        $this->lang = Request::header('language')??'en';
        Lang::range($this->lang);
        config('lang_set',$this->lang);//设置语言
        lang::load(APP_PATH . '/lang/' . $this->lang . '.php');
        //切换语言
        
    }

    public function showMessage($msg, $url = '-1')
    {
        if ($url == '-1') {
            echo "<script>alert('" . lang('free_user_lxb') . "');window.history.back();</script>";
        } else {
            echo "<script>alert('" . lang('free_user_lxb') . "');window.location.href='" . $url . "';</script>";
        }
        exit();
    }

    /**
     * 空操作 用于显示错误页面
     */
    public function _empty($name)
    {
        exit;
        return $this->fetch($name);
    }

    //图片上传为base64为的图片
    public function upload_base64($type, $img)
    {
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $img, $result)) {
            //$type_img = $result[2];  //得到图片的后缀
            $type_img = 'png';
            //上传 的文件目录

            $App = new \think\App();
            $new_files = $App->getRootPath() . 'upload' . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m-d') . DIRECTORY_SEPARATOR;

            if (!file_exists($new_files)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                //服务器给文件夹权限
                mkdir($new_files, 0777, true);
            }
            //$new_files = $new_files.date("YmdHis"). '-' . rand(0,99999999999) . ".{$type_img}";
            $new_files = check_pic($new_files, ".{$type_img}");
            if (file_put_contents($new_files, base64_decode(str_replace($result[1], '', $img)))) {
                //上传成功后  得到信息
                $filenames = str_replace('\\', '/', $new_files);
                $file_name = substr($filenames, strripos($filenames, "/upload"));
                return $file_name;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    //图片上传
    public function upload($type)
    {
        $file = request()->file('pic');
        //上传 的文件目录
        $App = new \think\App();
        $new_files = $App->getRootPath() . 'upload' . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR;
        $file = $file->validate(['size' => 20 * 1024 * 1024, 'ext' => 'jpg,png,jpeg,gif'])->move($new_files);
        if ($file) {
            $data['filepath'] = str_replace('\\', '/', '/' . $new_files . $file->getSaveName());
            $filenames = str_replace('\\', '/', '/' . $new_files . $file->getSaveName());
            return $filenames;
        }
    }

    /**
     * 检查交易状态
     */
    public function check_deal()
    {
        $uid = $this->_uid;
        $uinfo = Db::name('xy_users')->where('id', $uid)->find();
        if ($uinfo['status'] == 2) return [
            'code' => 1,
            'info' => lang('gzhybjy')
        ];
        if ($uinfo['deal_status'] == 0) return [
            'code' => 1,
            'info' => lang('gzhybdj')
        ];
        $uinfo['level'] = $uinfo['level'] ? intval($uinfo['level']) : 0;
        // if ($uinfo['deal_status'] == 3) return [
        //     'code' => 1,
        //     'info' => lang('gzhczwwcdd'),
        //     'url' => url('/index/order/index')
        // ];
        //判断是否有未完成订单
        $order = Db::name('xy_convey')->field('id')
            ->where('uid', $uid)->where('status', 'in', [0, 5])->find();
        if ($order) {
            return [
                'code' => 0,
                'info' => lang('gzhczwwcdd'),
                'data' => [
                    'action'=>'orderUrl'
                ]
            ];
        }
        //判断用户有冻结金额 跳过
        // dump($uinfo['freeze_balance']);die;
//        if ($uinfo['freeze_balance'] == 0.00) {
//            if ($uinfo['balance'] < config('deal_min_balance')) return [
//                'code' => 0,
//                'info' => lang('wfjy'),
//                'data' => [
//                    'action'=>'recharge'
//                ]
//            ];
//        }


        //是否昨天做过相同级别的任务
        if (config('is_same_yesterday_order') == 0 && $uinfo['group_id'] == 0) {
            // $d1 = strtotime(date('Y-m-d')) - 86400;
            // $d2 = strtotime(date('Y-m-d'));
            // $oTd = Db::name('xy_convey')
            //     ->where('status', 1)
            //     ->where('uid', $uinfo['id'])
            //     ->where('level_id', $uinfo['level'])
            //     //->where('addtime', 'between', [$d1, $d2])
            //     ->where('addtime', '<', $d2)
            //     ->value('id');
            // if ($oTd) {
            //     return [
            //         'code' => 1,
            //         'info' => lang('order_error_level_num'),
            //         'url' => url('/index/support/index'),
            //         'endRal' => true
            //     ];
            // }
        }


        return false;
    }

    /**
     * 返回失败的操作
     * @param $info
     * @param $data
     * @param $code
     * @return void
     */
    public function error($info, $data = [], $code = 1)
    {
        parent::error($info, $data, $code); // TODO: Change the autogenerated stub
    }

    /**
     * 返回成功的操作
     * @param $info
     * @param $data
     * @param $code
     * @return void
     */
    public function success($info, $data = [], $code = 0)
    {
        parent::success($info, $data, $code); // TODO: Change the autogenerated stub
    }

    /**
     * token验证
     * @param $token
     */
    public function checkToken()
    {
        $token = Request::header('Token');
        $config = Config::get()['jwt'];//读取jwt配置
        $secrect = $config['secrect'];
        if ($token) {
            if (count(explode('.', $token)) != 3) {
                return $this->error('illegal request', [], '401');
            }
            try {
                JWT::$leeway = 60;//当前时间减去60，把时间留点余地
                $decoded = JWT::decode($token, new Key($secrect, 'HS256')); //HS256方式，这里要和签发的时候对应
                $decoded_array = json_decode(json_encode($decoded), TRUE);
                $jwt_data = $decoded_array['data'];
                define('JWT_UID', $jwt_data['userid']);
            } catch (\Firebase\JWT\SignatureInvalidException $e) {  //签名不正确
                return $this->error('fail!', [], '401');//签名错误
            } catch (\Firebase\JWT\BeforeValidException $e) {  // 签名在某个时间点之后才能用
                return $this->error('token is fail', [], '401');
            } catch (\Firebase\JWT\ExpiredException $e) {  // token过期
                return $this->error('Token has expired', [], '401');
            } catch (Exception $e) {  //其他错误
                return $this->error('illegal request', [], '401');//非法请求
            } catch (\UnexpectedValueException $e) {  //其他错误
                return $this->error('illegal request', [], '401');//非法请求
            } catch (\DomainException $e) {  //其他错误
                return $this->error('illegal request', [], '401');
            }
        } else {
            return $this->error('Token cannot be empty', [], '401');
        }
    }

}
