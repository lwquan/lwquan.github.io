<?php

namespace app\api\controller;

use app\api\model\UsersLogic;
//use app\common;
use think\Page;
use think\Request;
use think\Verify;
use think\db;

class Index extends ApiBase
{

    /**
     * 首页banner图
     */
    public function getbanner(){
        $banner_list = M('ad')
            ->where("pid",82)
            ->limit(5)->select();

        $res= array('status'=>1, 'msg'=>'操作成功',"result"=>$banner_list);
        exit(json_encode($res));
    }








}