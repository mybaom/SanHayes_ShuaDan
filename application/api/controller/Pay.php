<?php

namespace app\api\controller;

use app\api\pay\Trcpay;
use think\App;
use think\Controller;
use think\Exception;
use think\Request;
use think\Db;
use think\View;

class Pay extends Controller
{
    /**
     * @param Request $request
     * 充值
     */

    public function notify_url (Request $request){
        $trcPay = new Trcpay();
        $put = file_get_contents('php://input');
        $data = $this->request->post(false);
        $log_file = APP_PATH . 'recharge_trcpay_callback.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ' PUT: ' . $put . "\n", FILE_APPEND);
        file_put_contents($log_file, date('Y-m-d H:i:s') . ' POST: ' . json_encode($data) . "\n", FILE_APPEND);
        $signature = $data['signature'];
        $sign = $trcPay->check_sign($data);
        if($sign == $signature){
            if($data['type'] == 'order'){
                $res = model('admin/Users')->recharge_success($data['t_order_num']);
                file_put_contents($log_file, '充值成功', FILE_APPEND);
                echo '__SUCCESS__';
                exit();

            }
        }
        file_put_contents($log_file, '验签失败', FILE_APPEND);

    }
}