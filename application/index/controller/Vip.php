<?php
namespace app\index\controller;

use library\Controller;
use think\Db;

class Vip extends Base
{
    //   /vip/index
    public function index()
    {
        return $this->fetch();
    }

}
