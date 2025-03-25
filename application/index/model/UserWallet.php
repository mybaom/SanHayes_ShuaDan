<?php

namespace app\index\model;

use Cassandra\Date;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;
use think\Db;

class UserWallet extends Model
{
    protected $table = 'user_wallet';

    public function insert($address,$phone,$uid,$full_name){
        Db::name('user_wallet')->insert([
            'uid' => $uid,
            'address' => $address,
            'phone' => $phone,
            'full_name'=>$full_name,
            'create_date' => date("Y-m-d H:m:i")
        ]);
    }



}