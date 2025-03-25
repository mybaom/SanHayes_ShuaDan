<?php

use Firebase\JWT\JWT;
use think\facade\Config;

function convert(&$args)
{
    $data = '';
    if (is_array($args)) {
        foreach ($args as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $k => $v) {
                    $data .= $key . '[' . $k . ']=' . rawurlencode($v) . '&';
                }
            } else {
                $data .= "$key=" . rawurlencode($val) . "&";
            }
        }
        return trim($data, "&");
    }
    return $args;
}

function isAllChinese($str)
{
    if (preg_match("/([\x81-\xfe][\x40-\xfe])/", $str, $match)) {
        return true;//全是中文
    } else {
        return false;//不全是中文
    }
}
/*
 * 检查图片是不是bases64编码的
 */
function is_image_base64($base64)
{
    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)) {
        return true;
    } else {
        return false;
    }
}

function check_pic($dir, $type_img)
{
    $new_files = $dir . date("YmdHis") . '-' . rand(0, 9999999) . "{$type_img}";
    if (!file_exists($new_files))
        return $new_files;
    else
        return check_pic($dir, $type_img);
}

/**
 * 获取数组中的某一列
 * @param array $arr 数组
 * @param string $key_name 列名
 * @return array  返回那一列的数组
 */
function get_arr_column($arr, $key_name)
{
    $arr2 = array();
    foreach ($arr as $key => $val) {
        $arr2[] = $val[$key_name];
    }
    return $arr2;
}

//保留两位小数
function tow_float($number)
{
    return (floor($number * 100) / 100);
}

//生成订单号
function getSn($head = '')
{
    $order_id_main = date('YmdHis') . mt_rand(1000, 9999);
    //唯一订单号码（YYMMDDHHIISSNNN）
    $osn = $head . substr($order_id_main, 2); //生成订单号
    return $osn;
}

/**
 * 修改本地配置文件
 *
 * @param array $name ['配置名']
 * @param array $value ['参数']
 * @return boolean
 */
function setconfig($name, $value)
{   
    
    if (is_array($name) and is_array($value)) {
        for ($i = 0; $i < count($name); $i++) {
            $names[$i] = '/\'' . $name[$i] . '\'(.*?),/';
            $values[$i] = "'" . $name[$i] . "'" . "=>" . "'" . $value[$i] . "',";
        }
        
        $fileurl = APP_PATH . "../config/app.php";
        $string = file_get_contents($fileurl); //加载配置文件
        $string = preg_replace($names, $values, $string); // 正则查找然后替换
        file_put_contents($fileurl, $string); // 写入配置文件
        return true;
    } else {
        return false;
    }
}

//生成随机用户名
function get_username()
{
    $chars1 = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $chars2 = "abcdefghijklmnopqrstuvwxyz0123456789";
    $username = "";
    for ($i = 0; $i < mt_rand(2, 3); $i++) {
        $username .= $chars1[mt_rand(0, 25)];
    }
    $username .= '_';

    for ($i = 0; $i < mt_rand(4, 6); $i++) {
        $username .= $chars2[mt_rand(0, 35)];
    }
    return $username;
}

/**
 * 判断当前时间是否在指定时间段之内
 * @param integer $a 起始时间
 * @param integer $b 结束时间
 * @return boolean
 */
function check_time($a, $b)
{
    $nowtime = time();
    $start = strtotime($a . ':00:00');
    $end = strtotime($b . ':00:00');

    if ($nowtime >= $end || $nowtime <= $start) {
        return true;
    } else {
        return false;
    }
}

//获取url参数
function get_params($key = "")
{
    return Request::instance()->param($key);
}

/**
 * 生成token
 * @param $user_id
 * @return string
 */
function getToken($user_id)
{
    $time = time(); //当前时间
    $conf = Config::get()['jwt'];
    $token = [
        'iss' => $conf['iss'], //签发者 可选
        'aud' => $conf['aud'], //接收该JWT的一方，可选
        'iat' => $time, //签发时间
        'nbf' => $time - 1, //(Not Before)：某个时间点后才能访问，比如设置time+30，表示当前时间30秒后才能使用
        'exp' => $time + $conf['exptime'], //过期时间,这里设置2个小时
        'data' => [
            //自定义信息，不要定义敏感信息
            'userid' => $user_id,
        ]
    ];
    return JWT::encode($token, $conf['secrect'], 'HS256'); //输出Token  默认'HS256'
}


/**
 * ipv6转成ipv4
 * @param $ip
 * @return string
 */
function Ipv6tov4($ip)
{
    if($ip){
        if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) !== false) {
            return $ip;
        }
        $str = mb_substr($ip, 30, 38);
        $arr = explode(':', $str);
        $Ip1 = base_convert(mb_substr($arr[0], 0, 2), 16, 10);
        $Ip2 = base_convert(mb_substr($arr[0], 2, 4), 16, 10);
        $Ip3 = base_convert(mb_substr($arr[1], 0, 2), 16, 10);
        $Ip4 = base_convert(mb_substr($arr[1], 2, 4), 16, 10);
        $IpV4 = $Ip1 . '.' . $Ip2 . '.' . $Ip3 . '.' . $Ip4;
        return $IpV4;
    }
    return $ip;
}




