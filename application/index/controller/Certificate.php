<?php
namespace app\index\controller;

use library\Controller;
use think\Db;

class Certificate extends Base
{
    //   /certificate/index
    public function index()
    {
        return $this->fetch();
    }

}