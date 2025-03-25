<?php

namespace app\index\controller;

use library\Controller;
use think\facade\Request;
use think\db;

class Send extends Controller
{
    //获取验证码
    public function sendsms(Request $request)
    {
        $tel = Request::post('tel/s', '');
        $type = Request::post('type', 1);
        $code = Request::post('code/s', '');

        if (strlen($tel) != 11) {
            return json(['code' => 1, 'info' => lang('sjhmgzbzq')]);
        }

        if ($type == 1) {
            $num = Db::table('xy_users')->where(['tel' => $tel])->count();
            if ($num) {
                return json(['code' => 1, 'info' => lang('sjhmyzc')]);
            }
        }

        $res = Db::table('xy_verify_msg')->field('addtime,tel')->where(['tel' => $tel])->find();
        if ($res && (($res['addtime'] + 60) > time()))
            return json(['code' => 0, 'info' => lang('yfzznfytdx')]);

        $time = date('YmdHis', time());
        $num = rand(10000, 99999);
        //$msg = config('app.zhangjun_sms.content') . $num  . '，' . config('app.zhangjun_sms.min') . '分钟内有效！';
        //$result = \org\ZhangjunSms::sendsms(config('app.zhangjun_sms.userid'),$time,md5(config('app.zhangjun_sms.account').config('app.zhangjun_sms.pwd').$time),$tel,$msg);
        $code = substr($code, 1);
        $result = $this->smsbao($tel, $num, $code);

        if ($result['status'] == 1) {  //发送成功
            if (!$res) {
                $r = Db::table('xy_verify_msg')->insert(['tel' => $tel, 'msg' => $num, 'addtime' => time(), 'type' => $type]);
            } else {
                $r = Db::table('xy_verify_msg')->where(['tel' => $tel])->data(['msg' => $num, 'addtime' => time(), 'type' => $type])->update();
            }

            if ($r)
                return json(['code' => 0, 'info' => lang('fscg')]);
            else
                return json(['code' => 0, 'info' => lang('fssb')]);
        } else
            return $result;
    }


    public function smsbao($tel, $code, $g_code)
    {

        $url = 'http://api2.nxcloud.com/api/sms/mtsend';
        $data = [];
        $data['appkey'] = 'JyDAPvfw';
        $data['secretkey'] = 'IzgySRfl';
        $data['phone'] = $g_code . $tel;
        //$data['content'] = lang('ndyzm') . "{$code}," . lang('yzmwfzyx');
        $data['content'] = lang('ndyzm') . "{$code},";
        $res = $this->send_post($url, $data);
        $res = json_decode($res, true);
        if ($res['code'] == '0') {
            return ['status' => 1, 'msg' => lang('fscg')];
        } else {
            return ['status' => 0, 'msg' => $res['code']];
        }
        //----------------短信宝---------------------
        /*$statusStr = array(
            "0" => "短信发送成功",
            "-1" => "参数不全",
            "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
            "30" => "密码错误",
            "40" => "账号不存在",
            "41" => "余额不足",
            "42" => "帐户已过期",
            "43" => "IP地址限制",
            "50" => "内容含有敏感词"
        );
        $smsapi = "http://api.smsbao.com/";
        $user = config('app.smsbao.user');       //短信平台帐号15196952584
        $pass = config('app.smsbao.pass') ;
        $pass = md5("$pass");   //短信平台密码
        $sign = config('app.smsbao.sign') ;
        $content = "【".$sign."】您的验证码为{$code}，验证码5分钟内有效。";
        $phone = $tel;//要发送短信的手机号码
        $sendurl = $smsapi . "sms?u=" . $user . "&p=" . $pass . "&m=" . $phone . "&c=" . urlencode($content);
        $result = file_get_contents($sendurl);

        if ($result == '0') {
            return ['status' => 1, 'msg' => "发送成功"];
        } else {
            return ['status' => 0, 'msg' => $statusStr[$result]];
        }*/

    }

    /**
     * 发送post请求
     * @param string $url 请求地址
     * @param array $post_data post键值对数据
     * @return string
     */
    function send_post($url, $post_data)
    {
        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }

}
