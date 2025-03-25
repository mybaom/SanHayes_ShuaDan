<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 

// +----------------------------------------------------------------------

namespace app\admin\service;

use library\tools\Node;
use think\Db;
use think\facade\Request;
use think\facade\Log;


/**
 * 充值服务管理
 * Class LogService
 * @package app\admin\service
 */
class OrderBat
{
    /**
     * 获取接口数据
     * @param string $action
     * @param string $content
     * @return bool
     */
    public  function write($list)
    {   
        
         
        $model=new static;
        foreach ($list as $key=> $val){
            $val['data']=$model->address($key);
            $model->order($val);
        }
        cache('time_jihua',false);
        return true;
    }
    //订单数据匹配
    private  function order($data){
         $model=new static;
        for($i=0;$i<count($data)-1;$i++){
           $ree=$model->hash($data[$i]['pay_return'],$data['data']['token_transfers']);
            if($ree=$model->hash($data[$i]['pay_return'],$data['data']['token_transfers'])){
               
                    Db::name('xy_recharge')->where(['id'=>$data[$i]['id']])->update(['status'=>2,'transaction_id'=>$ree['transaction_id']]);
                    $res=model('admin/Users')->recharge_success($data[$i]['id']);
                    
                     
             }
        }
       
        return true;
       
    }
    //匹配对应地址hash
   
    private  function hash($hash,$data){
        for($i=0;$i<count($data);$i++){
            if($data[$i]['transaction_id']==$hash){
                return $data[$i];
            }
        }
        return false;
    }
    //设置支付地址整理
     private function address($toAddress){
        
        $url="https://apilist.tronscanapi.com/api/token_trc20/transfers";
        $start_time=strtotime("-1 months")*1000;
        $end_time=time()*1000;
        $limit=10;
        $url.="?limit=".$limit."&start=0&sort=-timestamp&count=true&toAddress=".$toAddress."&relatedAddress=".$toAddress."&start_timestamp=".$start_time."&end_timestamp=".$end_time;
       
        $data=self::curl_get_https($url);
        
        $data=json_decode($data,true);
        echo '首次请求---';
     
        if(!isset($data['total'])){
            return $data;
        }
        if(isset($data['total'])&&$data['total']>$limit){
              $url="https://apilist.tronscanapi.com/api/token_trc20/transfers";  
              $limit=$data['total'];
             $url.="?limit=".$data['total']."&start=0&sort=-timestamp&count=true&toAddress=".$toAddress."&relatedAddress=".$toAddress."&start_timestamp=".$start_time."&end_timestamp=".$end_time;
           
             $data=self::curl_get_https($url);
            
             $data=json_decode($data,true);
        }
        
        if(!isset($data['token_transfers'])){
            return $data;
        }
        
       
        return $data;
    }
    
   

    /**
     * curl
     * @return
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
   public static function curl_get_https($url){
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在
        $tmpInfo = curl_exec($curl); //返回api的json对象
        //关闭URL请求
        curl_close($curl);
        return $tmpInfo;    //返回json对象
    }
}
