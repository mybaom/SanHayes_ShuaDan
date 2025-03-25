<?php

namespace app\api\controller;

use app\http\middleware\Auth;
use think\Db;
use think\Request;

/**
 * 应用入口
 * Class Index
 * @package app\index\controller
 */
class qingli
{
    /**
     * 返回首页信息
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
   
    public function qingli(){
        Db::name('xy_users')->where('status', 1)->where('deal_count','>', 0)->update(['goods_id_arr' => '', 'start' => 0, 'deal_count' => 0]);
        var_dump("ok");die;
    }
}
