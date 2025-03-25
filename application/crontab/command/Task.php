<?php
namespace app\crontab\command;
 
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Db;
class Task extends Command
{
    protected function configure()
    {
        $this->setName('task')
            ->setDescription('定时计划：每天会员任务重置');
    }
 
    protected function execute(Input $input, Output $output)
    {
        //会员任务重置
        $res= Db::name('xy_users')->where('status', 1)->where('deal_count','>', 0)->update(['goods_id_arr' => '', 'start' => 0, 'deal_count' => 0]);
        $res?$state = '成功！':$state = '失败！';
        file_put_contents(time().'.txt', '当前日期为：'.date('Y-m-d H:i:s').'执行会员任务重置'.$state);
    }
 
}