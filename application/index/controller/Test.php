<?php

namespace app\index\controller;

use app\index\pay\Nibpay;
use app\index\pay\Seapay;
use library\Controller;
use think\Db;

class Test extends Controller
{
    public function index()
    {
        var_dump(model('admin/Users')->get_agent_id());
    }

    public function uids()
    {
        $list = Db::name('xy_users')->field('id')->select();
        foreach ($list as $v) {
            model('admin/Users')->update_user_invites($v['id']);
        }
        echo 'suc';
    }

    public function sync_goods()
    {
        $result = [];
        $pageSize = 100;
        for ($i = 0; $i < 100; $i++) {
            $data = file_get_contents('https://my.xiapibuy.com/api/v4/search/search_items?' .
                'by=relevancy&keyword=0&limit=' . $pageSize . '&newest=' . ($pageSize * $i) . '&order=desc&' .
                'page_type=search&scenario=PAGE_GLOBAL_SEARCH&version=2&lang=en');
            $data = json_decode($data, true);
            foreach ($data['items'] as $val) {
                /*$result[] = [
                    'title' => $val['item_basic']['name'],
                    'price' => sprintf("%.2f", $val['item_basic']['price'] / 10000),
                    'image' => 'https://cf.shopee.com.my/file/' . $val['item_basic']['image'],
                ];*/
                Db::name('xy_goods_list')
                    ->insert([
                        'shop_name' => $val['item_basic']['name'],
                        'goods_name' => $val['item_basic']['name'],
                        'goods_info' => $val['item_basic']['name'],
                        'goods_price' => sprintf("%.2f", $val['item_basic']['price'] / 10000),
                        'goods_pic' => 'https://cf.shopee.com.my/file/' . $val['item_basic']['image'],
                        'addtime' => time(),
                        'status' => 1,
                        'cid' => 1
                    ]);
            }
        }
        echo 'success';
    }



    public function country()
    {
        //$pay = new Nibpay();
        //$pay->getBank();
        $pay = new Seapay();
        $pay->getBankList();
    }
}