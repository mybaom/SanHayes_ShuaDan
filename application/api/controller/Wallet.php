<?php


namespace app\api\controller;

use think\Db;

class Wallet extends Base
{
    // 添加钱包信息
    public function saveWallet()
    {
        $uid = $this->_uid;
        //钱包网络  分为  TRC20  BTC  ETH
        $network = input('post.network/s', '');
        // 姓名
        $username = input('post.name', '');
        // 钱包地址
        $address = input('post.wallet', '');

        $data['full_name'] = $username;
        $data['network'] = $network;
        $data['address'] = $address;
        $walletInfo = Db::name('user_wallet')->where('uid', $uid)->find();
        if ($walletInfo) {
            // $res = Db::name('user_wallet')->where('id', $walletInfo['id'])->update($data);
        } else {
            $data['uid'] = $uid;
            $data['create_date'] = date("Y-m-d H:m:i");
            $res = Db::name('user_wallet')->insert($data);
        }
        $jo["code"]=$res!==false?0:1;
        $jo['info']=$res!==false?"Operation successful":'Fail';
        return $this->success($jo['info'],'',$jo['code']);
    }

    // 钱包信息
    public function getWalletInfo()
    {
        $infos = Db::name('user_wallet')->where('uid', $this->_uid)->find();
        return $this->success('success', $infos);
    }
}