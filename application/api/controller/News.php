<?php

namespace app\api\controller;

use app\api\model\NewsLogic;
use think\Page;
use think\Request;
use think\Verify;
use think\db;

class News extends ApiBase
{

    /**
     * 获取新闻分类
     */
    public function newsCates()
    {
        $list = M('news_cat')
            ->field('cat_id,cat_name')
            ->select();
        $this->ajaxReturn(array('status' => 1, 'msg' => '请求成功成功', 'result' => $list));
    }
    /**
     * 获取新闻列表
     */
    public function newList(){
        $tags = I('tags',1);
        $cat_id = I('cat_id');
        $p = I('pagestart',0);
        $d = I('speed',5);
        $newsLogic = new NewsLogic();
        $data = $newsLogic->news_list($tags,$cat_id,$p,$d);
        $this->ajaxReturn($data);
    }
    /**
     * 获取新闻详情
     */
        public function newInfo(){
        $newId = I('new_id');
        $newsLogic = new NewsLogic();
        $data = $newsLogic->news_detail($newId);
        $this->ajaxReturn($data);
    }


}