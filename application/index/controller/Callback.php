<?php

namespace app\index\controller;

use app\index\pay\Ceanpay;
use app\index\pay\Mxpay;
use app\index\pay\Qeapay;
use app\index\pay\Sepropay;
use app\index\pay\Sixgpay;
use app\index\pay\Speedypay;
use app\index\pay\Tokpay;
use app\index\pay\Yulupay;
use library\Controller;
use think\facade\Request;
use think\Db;

/**
 * 验证登录控制器
 */
class Callback extends Controller
{
    //收款回调
    public function recharge_luxpay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'recharge_luxpay_callback.log';
        $log_file_final = APP_PATH . 'recharge_luxpay_callback_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        $data = json_decode($put, true);
        if (!empty($data['out_trade_no'])) {
            if ($data['trade_status'] != 'SUCCESS') {
                file_put_contents($log_file, $data['out_trade_no'] . ' ======订单状态不正确!' . "\n", FILE_APPEND);
                exit('success');
            }
            $oinfo = Db::name('xy_recharge')->find($data['out_trade_no']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['out_trade_no'] . ' ======订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if (floatval($data['amount']) != floatval($oinfo['num'])) {
                file_put_contents($log_file, $data['out_trade_no'] . ' ======金额不对!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] == 2) {
                exit('success');
            }
            if ($oinfo['status'] != 1) {
                file_put_contents($log_file, $data['out_trade_no'] . ' ======订单已处理!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $user = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
            if (!$user) {
                file_put_contents($log_file, $data['out_trade_no'] . ' ======用户已被删除!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $res = model('admin/Users')->recharge_success($data['out_trade_no'], 1);
            if ($res) {
                exit('success');
            } else {
                Db::rollback();
                file_put_contents($log_file, $data['out_trade_no'] . ' ======数据库插入失败!' . "\n", FILE_APPEND);
                file_put_contents($log_file_final, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
        } else {
            http_response_code(500);
            exit();
        }
    }

    //收款回调
    public function recharge_sixpag()
    {
        $put = file_get_contents('php://input');
        $data = $this->request->post(false);
        $log_file = APP_PATH . 'recharge_sixpag_callback.log';
        $log_file_final = APP_PATH . 'recharge_sixpag_callback_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ' PUT: ' . $put . "\n", FILE_APPEND);
        file_put_contents($log_file, date('Y-m-d H:i:s') . ' POST: ' . json_encode($data) . "\n", FILE_APPEND);
        if (!empty($data['mchOrderNo'])) {
            if (!Sixgpay::instance()->check_sign($data)) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======签名校验失败!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            if ($data['tradeResult'] != '1') {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======订单状态不正确!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            $oinfo = Db::name('xy_recharge')->find($data['mchOrderNo']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if (floatval($data['amount']) != floatval($oinfo['num'])) {
                file_put_contents($log_file, $oinfo['id'] . ' ======金额不对!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] == 2) {
                exit('success');
            }
            if ($oinfo['status'] != 1) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======订单已处理!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $user = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
            if (!$user) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======用户已被删除!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $res = model('admin/Users')->recharge_success($data['mchOrderNo'], 1);
            if ($res) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('success');
            } else {
                Db::rollback();
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======数据库插入失败!' . "\n", FILE_APPEND);
                file_put_contents($log_file_final, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
        } else {
            http_response_code(500);
            exit();
        }
    }

    //收款回调
    public function recharge_speedypay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'recharge_speedypay_callback.log';
        $log_file_final = APP_PATH . 'recharge_speedypay_callback_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        $data = json_decode($put, true);
        if (!empty($data['orderId'])) {
            if (!Speedypay::instance()->check_sign($data)) {
                file_put_contents($log_file, $data['orderId'] . ' ======签名校验失败!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            if ($data['orderStatus'] != 1) {
                file_put_contents($log_file, $data['orderId'] . ' ======订单状态不正确!' . "\n", FILE_APPEND);
                exit('success');
            }
            $oinfo = Db::name('xy_recharge')->find($data['orderId']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['orderId'] . ' ======订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if (floatval($data['amount']) != floatval($oinfo['num'])) {
                file_put_contents($log_file, $oinfo['id'] . ' ======金额不对!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] == 2) {
                exit('success');
            }
            if ($oinfo['status'] != 1) {
                file_put_contents($log_file, $data['orderId'] . ' ======订单已处理!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $user = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
            if (!$user) {
                file_put_contents($log_file, $data['orderId'] . ' ======用户已被删除!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $res = model('admin/Users')->recharge_success($data['orderId'], 1);
            if ($res) {
                file_put_contents($log_file, $data['orderId'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('success');
            } else {
                Db::rollback();
                file_put_contents($log_file, $data['orderId'] . ' ======数据库插入失败!' . "\n", FILE_APPEND);
                file_put_contents($log_file_final, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
        } else {
            http_response_code(500);
            exit();
        }
    }

    //代付回调
    public function payout_luxpay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'payout_luxpay_callback.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        exit('success');
    }

    //代付回调
    public function payout_sixgpay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'payout_sixgpay_callback.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        $data = !empty($_POST) ? $_POST : null;
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
        if (empty($data['tradeResult'])) {
            http_response_code(500);
            exit();
        }
        //成功
        if ($data['tradeResult'] == 1) {
            exit('success');
        } elseif ($data['tradeResult'] == 2) {
            //提现失败了 {"amount":"13.12","mchId":"dafu666","mchOrderNo":"CO2104150843274656","orderNo":"202104161204264690","tradeResult":"2","sign":"961014f7d838a09c1dced4db8153fbb1"}
            if (!Sixgpay::instance()->check_sign($data)) {
                file_put_contents($log_file, date('Y-m-d H:i:s') . ': 签名错误' . "\n", FILE_APPEND);
            }
            //处理订单逻辑
            $oinfo = Db::name('xy_deposit')->find($data['mchOrderNo']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======提现订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] != 2) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======订单状态不对!' . "\n", FILE_APPEND);
                exit('success');
            }
            file_put_contents($log_file, $data['mchOrderNo'] . ' ======开始回滚提现!' . "\n", FILE_APPEND);
            $res = model('admin/Users')->payout_rollback($oinfo);
            if ($res) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('success');
            } else {
                Db::rollback();
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======数据库操作失败!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
        }
        http_response_code(500);
        exit();
    }


    //收款回调
    public function recharge_tokpay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'recharge_tokpay_callback.log';
        $log_file_final = APP_PATH . 'recharge_tokpay_callback_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        parse_str($put, $data);
        if (!empty($data['status'])) {
            if (!Tokpay::instance()->check_sign($data)) {
                file_put_contents($log_file, $data['orderno'] . ' ======签名校验失败!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            if ($data['status'] != 'SUCCESS') {
                file_put_contents($log_file, $data['orderno'] . ' ======状态返回不成功!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            $oinfo = Db::name('xy_recharge')->find($data['orderno']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['orderno'] . ' ======订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if (floatval($data['actualamount']) != floatval($oinfo['num'])) {
                file_put_contents($log_file, $oinfo['id'] . ' ======金额不对!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] == 2) {
                exit('SUCCESS');
            }
            if ($oinfo['status'] != 1) {
                file_put_contents($log_file, $data['orderno'] . ' ======订单已处理!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $user = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
            if (!$user) {
                file_put_contents($log_file, $data['orderno'] . ' ======用户已被删除!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $res = model('admin/Users')->recharge_success($data['orderno'], 1);
            if ($res) {
                file_put_contents($log_file, $data['orderno'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('SUCCESS');
            } else {
                Db::rollback();
                file_put_contents($log_file, $data['orderno'] . ' ======数据库插入失败!' . "\n", FILE_APPEND);
                file_put_contents($log_file_final, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
        } else {
            http_response_code(500);
            exit();
        }
    }

    //代付回调
    public function payout_tokpay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'payout_tokpay_callback.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        $data = !empty($_POST) ? $_POST : null;
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
        if (empty($data['status'])) {
            http_response_code(500);
            exit();
        }
        //成功
        if ($data['status'] == 'SUCCESS') {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ': ===OK' . "\n", FILE_APPEND);
            exit('success');
        } else {
            if (!Tokpay::instance()->check_sign($data)) {
                file_put_contents($log_file, date('Y-m-d H:i:s') . ': 签名错误' . "\n", FILE_APPEND);
                exit('sign error');
            }
            //处理订单逻辑
            $oinfo = Db::name('xy_deposit')->find($data['orderno']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['orderno'] . ' ======提现订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] != 2) {
                file_put_contents($log_file, $data['orderno'] . ' ======订单状态不对!' . "\n", FILE_APPEND);
                exit('success');
            }
            file_put_contents($log_file, $data['orderno'] . ' ======开始回滚提现!' . "\n", FILE_APPEND);
            $res = model('admin/Users')->payout_rollback($oinfo);
            if ($res) {
                file_put_contents($log_file, $data['orderno'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('success');
            }
            file_put_contents($log_file, $data['orderno'] . ' ======数据库操作失败!' . "\n", FILE_APPEND);
            http_response_code(500);
            exit();
        }
    }

    //收款回调
    public function recharge_sepropay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'recharge_sepropay_callback.log';
        $log_file_final = APP_PATH . 'recharge_sepropay_callback_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        parse_str($put, $data);
        if (!is_array($data)) {
            file_put_contents($log_file, ' ======DATA ERROR!' . "\n", FILE_APPEND);
        }
        //tradeResult=1&oriAmount=1000.00&amount=1000.00&mchId=100003230&orderNo=604850898&mchOrderNo=SY2108201617237200&sign=133aad6baa4377caa78fce8d356524d8&signType=MD5&orderDate=2021-08-20+18%3A47%3A23
        if (!empty($data['tradeResult'])) {
            if (!Sepropay::instance()->check_sign($data)) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======签名校验失败!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            if ($data['tradeResult'] != '1') {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======状态返回不成功!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            $oinfo = Db::name('xy_recharge')->find($data['mchOrderNo']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if (floatval($data['amount']) != floatval($oinfo['num'])) {
                file_put_contents($log_file, $oinfo['id'] . ' ======金额不对!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] == 2) {
                exit('SUCCESS');
            }
            if ($oinfo['status'] != 1) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======订单已处理!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $user = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
            if (!$user) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======用户已被删除!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $res = model('admin/Users')->recharge_success($data['mchOrderNo'], 1);
            if ($res) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('SUCCESS');
            } else {
                Db::rollback();
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======数据库插入失败!' . "\n", FILE_APPEND);
                file_put_contents($log_file_final, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
        } else {
            file_put_contents($log_file, ' ====== ERROR!' . "\n", FILE_APPEND);
            http_response_code(500);
            exit();
        }
    }

    //代付回调
    public function payout_sepropay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'payout_sepropay_callback.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        parse_str($put, $data);
        if (empty($data['tradeResult'])) {
            http_response_code(500);
            exit();
        }
        if (!Sepropay::instance()->check_payout_sign($data)) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ': 签名错误' . "\n", FILE_APPEND);
            exit('sign error');
        }
        $oinfo = Db::name('xy_deposit')->find($data['merTransferId']);
        if (!$oinfo) {
            file_put_contents($log_file, $data['merTransferId'] . ' ======提现订单不存在!' . "\n", FILE_APPEND);
            http_response_code(500);
            exit;
        }
        Db::name('xy_deposit')->where('id', $oinfo['id'])
            ->update([
                'payout_time' => time(),
                'payout_status' => ($data['tradeResult'] == '1' && $data['respCode'] == 'SUCCESS') ? 2 : 3,
                'payout_err_msg' => '',
            ]);
        //成功
        if ($data['tradeResult'] == '1' && $data['respCode'] == 'SUCCESS') {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ': ===OK' . "\n", FILE_APPEND);
            exit('ok');
        } else {
            //处理订单逻辑
            if ($oinfo['status'] != 2) {
                file_put_contents($log_file, $data['merTransferId'] . ' ======订单状态不对!' . "\n", FILE_APPEND);
                exit('success');
            }
            file_put_contents($log_file, $data['merTransferId'] . ' ======开始回滚提现!' . "\n", FILE_APPEND);
            $res = model('admin/Users')->payout_rollback($oinfo);
            if ($res) {
                file_put_contents($log_file, $data['merTransferId'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('success');
            }
            file_put_contents($log_file, $data['merTransferId'] . ' ======数据库操作失败!' . "\n", FILE_APPEND);
            http_response_code(500);
            exit();
        }
    }

    //收款回调
    public function recharge_yulupay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'recharge_yulupay_callback.log';
        $log_file_final = APP_PATH . 'recharge_yulupay_callback_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        $data = json_decode($put, true);
        if (!empty($data['code'])) {
            if (!Yulupay::instance()->check_sign($data)) {
                file_put_contents($log_file, $data['mer_order_no'] . ' ======签名校验失败!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            if ($data['code'] != '1000') {
                file_put_contents($log_file, $data['mer_order_no'] . ' ======状态返回不成功!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            $oinfo = Db::name('xy_recharge')->find($data['mer_order_no']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['mer_order_no'] . ' ======订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if (floatval($data['amount']) != floatval($oinfo['num'])) {
                file_put_contents($log_file, $oinfo['id'] . ' ======金额不对!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] == 2) {
                exit('ok');
            }
            if ($oinfo['status'] != 1) {
                file_put_contents($log_file, $data['mer_order_no'] . ' ======订单已处理!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $user = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
            if (!$user) {
                file_put_contents($log_file, $data['mer_order_no'] . ' ======用户已被删除!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $res = model('admin/Users')->recharge_success($data['mer_order_no'], 1);
            if ($res) {
                file_put_contents($log_file, $data['mer_order_no'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('ok');
            } else {
                Db::rollback();
                file_put_contents($log_file, $data['mer_order_no'] . ' ======数据库插入失败!' . "\n", FILE_APPEND);
                file_put_contents($log_file_final, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
        } else {
            file_put_contents($log_file, ' ======数据解析失败!' . "\n", FILE_APPEND);
            http_response_code(500);
            exit();
        }
    }

    //代付回调
    public function payout_yulupay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'payout_yulupay_callback.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        $data = json_decode($put, true);
        if (empty($data['code'])) {
            http_response_code(500);
            exit();
        }
        //成功
        if ($data['code'] == '1000' && $data['status'] == 'success') {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ': ===OK' . "\n", FILE_APPEND);
            exit('ok');
        } else {
            if (!Yulupay::instance()->check_sign($data)) {
                file_put_contents($log_file, date('Y-m-d H:i:s') . ': 签名错误' . "\n", FILE_APPEND);
                exit('sign error');
            }
            //处理订单逻辑
            $oinfo = Db::name('xy_deposit')->find($data['mer_order_no']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['mer_order_no'] . ' ======提现订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] != 2) {
                file_put_contents($log_file, $data['mer_order_no'] . ' ======订单状态不对!' . "\n", FILE_APPEND);
                exit('success');
            }
            file_put_contents($log_file, $data['mer_order_no'] . ' ======开始回滚提现!' . "\n", FILE_APPEND);
            $res = model('admin/Users')->payout_rollback($oinfo);
            if ($res) {
                file_put_contents($log_file, $data['mer_order_no'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('success');
            }
            file_put_contents($log_file, $data['mer_order_no'] . ' ======数据库操作失败!' . "\n", FILE_APPEND);
            http_response_code(500);
            exit();
        }
    }

    //收款回调
    public function recharge_qeapay($type = 0)
    {
        $_GET['type'] = $type;
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'recharge_qeapay_callback.log';
        $log_file_final = APP_PATH . 'recharge_qeapay_callback_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        parse_str($put, $data);
        if (!empty($data['tradeResult'])) {
            if (!Qeapay::instance()->check_sign($data)) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======签名校验失败!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            if ($data['tradeResult'] != '1') {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======状态返回不成功!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            $oinfo = Db::name('xy_recharge')->find($data['mchOrderNo']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if (floatval($data['amount']) != floatval($oinfo['num'])) {
                file_put_contents($log_file, $oinfo['id'] . ' ======金额不对!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] == 2) {
                exit('SUCCESS');
            }
            if ($oinfo['status'] != 1) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======订单已处理!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $user = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
            if (!$user) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======用户已被删除!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $res = model('admin/Users')->recharge_success($data['mchOrderNo'], 1);
            if ($res) {
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('SUCCESS');
            } else {
                Db::rollback();
                file_put_contents($log_file, $data['mchOrderNo'] . ' ======数据库插入失败!' . "\n", FILE_APPEND);
                file_put_contents($log_file_final, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
        } else {
            http_response_code(500);
            exit();
        }
    }

    //代付回调
    public function payout_qeapay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'payout_qeapay_callback.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        parse_str($put, $data);
        if (empty($data['tradeResult'])) {
            http_response_code(500);
            exit();
        }
        if (!Qeapay::instance()->check_payout_sign($data)) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ': 签名错误' . "\n", FILE_APPEND);
            exit('sign error');
        }
        $res = $this->checkPayoutOrder([
            'status' => $data['tradeResult'] == '1' && $data['respCode'] == 'SUCCESS' ? 'SUCCESS' : 'ERROR',
            'oid' => $data['merTransferId'],
            'amount' => $data['transferAmount'],
            'msg' => !empty($data['errorMsg']) ? $data['errorMsg'] : '',
            'data' => $data,
        ], $log_file);
        if ($res) {
            echo 'success';
        } else {
            echo 'error';
        }
    }

    //收款回调
    public function recharge_ceanpay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'recharge_ceanpay_callback.log';
        $log_file_final = APP_PATH . 'recharge_ceanpay_callback_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        parse_str($put, $data);
        if (!empty($data['merordercode'])) {
            if (!Ceanpay::instance()->check_callback_sign($data)) {
                file_put_contents($log_file, $data['merordercode'] . ' ======签名校验失败!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
            $oinfo = Db::name('xy_recharge')->find($data['merordercode']);
            if (!$oinfo) {
                file_put_contents($log_file, $data['merordercode'] . ' ======订单不存在!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if (floatval($data['amount']) != floatval($oinfo['num'])) {
                file_put_contents($log_file, $oinfo['id'] . ' ======金额不对!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            if ($oinfo['status'] == 2) {
                exit('OK');
            }
            if ($oinfo['status'] != 1) {
                file_put_contents($log_file, $data['merordercode'] . ' ======订单已处理!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $user = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
            if (!$user) {
                file_put_contents($log_file, $data['merordercode'] . ' ======用户已被删除!' . "\n", FILE_APPEND);
                http_response_code(500);
                exit;
            }
            $res = model('admin/Users')->recharge_success($data['merordercode'], 1);
            if ($res) {
                file_put_contents($log_file, $data['merordercode'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
                exit('OK');
            } else {
                Db::rollback();
                file_put_contents($log_file, $data['merordercode'] . ' ======数据库插入失败!' . "\n", FILE_APPEND);
                file_put_contents($log_file_final, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
                http_response_code(500);
                exit();
            }
        } else {
            http_response_code(500);
            exit();
        }
    }

    //代付回调
    public function payout_ceanpay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'payout_ceanpay_callback.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        parse_str($put, $data);
        if (empty($data['returncode'])) {
            http_response_code(500);
            exit();
        }
        //['status'=>'SUCCESS','oid'=>'','amount'=>'','msg'=>'']
        $check = Ceanpay::instance()->check_payout_sign($data);
        if ($check) {
            file_put_contents($log_file, '======sign success' . "\n", FILE_APPEND);
        } else file_put_contents($log_file, '======sign error' . "\n", FILE_APPEND);
        $res = $this->checkPayoutOrder([
            'status' => $data['returncode'],
            'oid' => $data['merissuingcode'],
            'amount' => $data['amount'],
            'msg' => $data['message'],
            'data' => $data,
        ], $log_file);
        if ($res) exit('OK');
        exit('err');
    }

    //收款回调
    public function recharge_mxpay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'recharge_mxpay_callback.log';
        $log_file_final = APP_PATH . 'recharge_mxpay_callback_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        $put = json_decode($put, true);
        $data = Mxpay::instance()->des_params($put['callbackParams']);
        file_put_contents($log_file, '   desData: ' . $data . "\n", FILE_APPEND);
        $data = json_decode($data, true);
        if (!empty($data['merTransNo']) && !empty($data['success'])) {
            $res = $this->checkCallbackOrder([
                'amount' => $data['repayMoney'],
                'oid' => $data['merTransNo'],
                'msg' => '',
                'data' => $data,
            ], $log_file, $log_file_final);
            if ($res) {
                echo '{"success":true,"message":"Accepted"}';
            } else {
                echo '{"success":false,"message":"FAIL"}';
            }
            exit();
        }
        echo '{"success":false,"message":"FAIL"}';
        exit();
    }

    //代付回调
    public function payout_mxpay()
    {
        $put = file_get_contents('php://input');
        $log_file = APP_PATH . 'payout_mxpay_callback.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $put . "\n", FILE_APPEND);
        parse_str($put, $data);
        $put = json_decode($put, true);
        $data = Mxpay::instance()->des_params($put['callbackParams']);
        file_put_contents($log_file, '   desData: ' . $data . "\n", FILE_APPEND);
        $data = json_decode($data, true);
        if (!empty($data['merTransNo'])) {
            $res = $this->checkPayoutOrder([
                'status' => (!empty($data['success']) && $data['success'] == true) ? 'SUCCESS' : 'ERROR',
                'oid' => $data['merTransNo'],
                'amount' => $data['amount'],
                'msg' => $data['message'],
                'data' => $data
            ], $log_file);
            if ($res) {
                echo '{"success":true,"message":"Accepted"}';
            } else {
                echo '{"success":false,"message":"FAIL"}';
            }
        }
    }

    //统一代收回掉  通道 ， 渠道
    public function pay($gateway = '', $type = '')
    {
        if ($gateway == '') exit();
        $gateway = ucfirst($gateway);
        $log_file = APP_PATH . 'callback_pay_' . $gateway . '.log';
        $log_file_final = APP_PATH . 'callback_pay_' . $gateway . '_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . file_get_contents('php://input') . "\n", FILE_APPEND);
        $className = "\\app\\index\\pay\\" . $gateway;
        $payObj = new $className();
        $payout = $payObj->parsePayCallback($type);
        file_put_contents($log_file, '  ret:' . json_encode($payout, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        //处理回调逻辑
        $res = $this->checkCallbackOrder($payout, $log_file, $log_file_final);
        if ($res) {
            $payObj->payCallbackSuccess();
        } else {
            $payObj->payCallbackFail();
        }
        exit;
    }
    //收款成功 回掉公共逻辑
    //$data = ['status'=>'SUCCESS',oid=>'订单号',amount=>'金额','data'=>'原始数据 array']
    // , $log_file="xxxx.log"
    // ,$log_file_final='xxx.log'
    /**
     * 收款成功 回掉公共逻辑
     * @param $data array
     * @param $log_file string
     * @param $log_file_final string
     * @return bool
     * */
    private function checkCallbackOrder($data, $log_file, $log_file_final)
    {
        file_put_contents($log_file, 'DATA ======' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        if (!isset($data['status']) || !isset($data['oid']) ||
            !isset($data['amount']) || !isset($data['data'])) {
            //数据包格式不对
            file_put_contents($log_file, 'ERROR ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
            return false;
        }
        $oinfo = Db::name('xy_recharge')->find($data['oid']);
        if (!$oinfo) {
            file_put_contents($log_file, $data['oid'] . ' ======订单不存在!' . "\n", FILE_APPEND);
            return false;
        }
        if ($oinfo['status'] == 2) {
            return true;
        }
        //if ($oinfo['status'] != 1) {
        //    file_put_contents($log_file, $data['oid'] . ' ======订单已处理!' . "\n", FILE_APPEND);
        //    return false;
        //}
        if ($data['status'] != 'SUCCESS') {
            file_put_contents($log_file, $data['oid'] . ' ======ERROR' . "\n", FILE_APPEND);
            //更新标状态
            Db::name('xy_recharge')
                ->where('id', $oinfo['id'])
                ->update([
                    'pay_status' => 2,
                    'pay_return' => $data['status'],
                    'endtime' => time(),
                    'status' => 3
                ]);
            return false;
        }
        if (floatval($data['amount']) != floatval($oinfo['num'])) {
            //file_put_contents($log_file, $oinfo['id'] . ' ======金额不对!' . "\n", FILE_APPEND);
            //return false;
            //修改订单金额
            //$pay_com = Db::name('xy_pay')->where('name2', $oinfo['pay_name'])->value('pay_commission');
            //$pay_com = $pay_com ? floatval($pay_com) : 0;

            Db::name('xy_recharge')
                ->where('id', $oinfo['id'])
                ->update(['num' => $data['amount']]);
        }
        $user = Db::name('xy_users')->where('id', $oinfo['uid'])->find();
        if (!$user) {
            file_put_contents($log_file, $data['oid'] . ' ======用户已被删除!' . "\n", FILE_APPEND);
            return false;
        }
        $res = model('admin/Users')->recharge_success($data['oid'], 1);
        if ($res) {
            file_put_contents($log_file, $data['oid'] . ' ======SUCCESS!' . "\n", FILE_APPEND);
            return true;
        } else {
            file_put_contents($log_file, $data['oid'] . ' ======数据库插入失败!' . "\n", FILE_APPEND);
            file_put_contents($log_file_final, date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n", FILE_APPEND);
            return false;
        }
    }

    //统一代付回掉  通道，渠道
    public function payout($gateway = '', $type = '')
    {
        if ($gateway == '') exit();
        $gateway = ucfirst($gateway);
        $log_file = APP_PATH . 'callback_payout_' . $gateway . '.log';
        $log_file_final = APP_PATH . 'callback_payout_' . $gateway . '_final.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . file_get_contents('php://input') . "\n", FILE_APPEND);
        $className = "\\app\\index\\pay\\" . $gateway;
        $payObj = new $className();
        $result = $payObj->parsePayoutCallback();
        $res = $this->checkPayoutOrder($result, $log_file);
        if ($res) {
            $payObj->parsePayoutCallbackSuccess();
        } else {
            $payObj->parsePayoutCallbackFail();
        }
        exit;
    }

    //出款回掉公共逻辑==错误的情况
    //$data['status'=>'SUCCESS','oid'=>'','amount'=>'','msg'=>''] ,$log_file='xxx.log'
    private function checkPayoutOrder($data, $log_file)
    {
        file_put_contents($log_file, 'DATA ======' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        if (!isset($data['status']) || !isset($data['oid']) ||
            !isset($data['amount']) || !isset($data['data']) || !isset($data['msg'])) {
            //数据包格式不对
            file_put_contents($log_file, '======DATA ERROR' . "\n", FILE_APPEND);
            return false;
        }
        //失败了  处理订单逻辑
        $oinfo = Db::name('xy_deposit')->find($data['oid']);
        if (!$oinfo) {
            file_put_contents($log_file, $data['oid'] . ' ======提现订单不存在!' . "\n", FILE_APPEND);
            return false;
        }
        //如果订单状态不对的
        /*if ($oinfo['status'] != 1) {
            file_put_contents($log_file, $data['oid'] . ' ======订单已处理!' . "\n", FILE_APPEND);
            return true;
        }*/
        //更新数据库
        Db::name('xy_deposit')
            ->where('id', $data['oid'])
            ->update([
                'payout_time' => time(),
                'payout_status' => ($data['status'] == 'SUCCESS') ? 2 : 3,
                'payout_err_msg' => $data['msg']
            ]);
        if ($data['status'] == 'SUCCESS') {
            return true;
        }
        if ($oinfo['status'] != 2) {
            file_put_contents($log_file, $data['oid'] . ' ======订单状态不对!' . "\n", FILE_APPEND);
            return true;
        }
        file_put_contents($log_file, $data['oid'] . ' ======开始回滚提现!' . "\n", FILE_APPEND);
        $res = model('admin/Users')->payout_rollback($oinfo);
        if ($res) {
            file_put_contents($log_file, $data['oid'] . ' ===ROLLBACK===SUCCESS!' . "\n", FILE_APPEND);
            return true;
        }
        file_put_contents($log_file, $data['oid'] . ' ======数据库操作失败!' . "\n", FILE_APPEND);
        return false;
    }
}