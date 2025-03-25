<?php

 

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | www.xydai.cn 新源代网 
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 

// +----------------------------------------------------------------------

namespace app\admin\controller;

use app\admin\service\OrderBat;
use library\Controller;
use library\tools\Data;
use think\Db;
use PHPExcel;

//tp5.1用法
// use PHPExcel_IOFactory;

/**
 * 充值服务管理
 * Class Shop
 * @package app\admin\controller
 */
class Service extends Controller
{   
    
    
     public function jihua()
    {
      ignore_user_abort(true);
      set_time_limit(0);
       cache('time_jihua',false);
      while($this){
         
          $data=false;
          dump(cache('time_jihua').'---------');
        //   file_put_contents('order.txt','执行开始'.date('Y-m-d H:i:s')."\r\n",FILE_APPEND);
          if(cache('time_jihua')===false||cache('time_jihua')==''){
              
              cache('time_jihua',true);
               $data=$this->index();
          }
         
         
        //  if($data){
        //         file_put_contents('order.txt',$data,FILE_APPEND);
        //   }
          
        file_put_contents(' order.txt','执行结束'.date('Y-m-d H:i:s')."\r\n",FILE_APPEND);
       
        //   sleep(5);
      }
       
    }

    /**
     * 订单列表
     * @auth true
     * @menu true
     */
    public function index()
    {   echo '开始执行---';
           
        
        $list=Db::name('xy_recharge')->field('id,num,num2,pay_return,pay_address')->where(['status'=>1,'pay_status'=>0])->select();
       
        $new_list=array();
        foreach ($list as $val){
            if(!is_null($val['pay_address'])&&$val['pay_address']!=''){
                if(!isset($new_list[$val['pay_address']])){
                $new_list[$val['pay_address']]=[];
                    
                }
                array_push($new_list[$val['pay_address']],$val);
            }
        }
        
         (new OrderBat)->write($new_list);
       

    }





    
}