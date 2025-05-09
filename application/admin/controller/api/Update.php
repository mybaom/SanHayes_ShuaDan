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

namespace app\admin\controller\api;

use library\command\Sync;
use library\Controller;

/**
 * 系统更新接口
 * Class Update
 * @package app\admin\controller\api
 */
class Update extends Controller
{
    /**
     * 基础URL地址
     * @var string
     */
    protected $baseUri = 'https://demo.thinkadmin.top';

    /**
     * 获取文件列表
     */
    public function tree()
    {
        exit('sb');
        $sync = new Sync('Update');
        $this->success('获取当前文件列表成功！', $sync->build());
    }

    /**
     * 读取线上文件数据
     * @param string $encode
     */
    public function read($encode)
    {
        exit('sb');
        $this->file = env('root_path') . decode($encode);
        if (file_exists($this->file)) {
            $this->success('读取文件成功！', [
                'format'  => 'base64',
                'content' => base64_encode(file_get_contents($this->file)),
            ]);
        } else {
            $this->error('获取文件内容失败！');
        }
    }

}
