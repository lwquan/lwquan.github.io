<?php

namespace app\api\controller;

use app\api\model\NewsLogic;
use think\Page;
use think\Request;
use think\Verify;
use think\db;

class Ajaxdata extends ApiBase
{
    /**
     * 操作ad表
     */
    public function getad(){
        $pid = input("get.pid")?input("get.pid"):537;
        $nums = input("get.nums")?input("get.nums"):5;
        $banner_list = M('ad')
            ->field('ad_name,ad_code,ad_id')
            ->where("pid",$pid)
            ->limit($nums)->select();
        $res= array('status'=>1, 'msg'=>'操作成功',"result"=>$banner_list);

        exit(json_encode($res));
    }
    //首页推荐商品
    public function ajaxGetMore(){
        $type = I('type','is_recommend');
        $p = I('pagestart',0);
        $d = I('speed');
        $favourite_goods = M('goods')
//            ->field("goods_id,cat_id,goods_name,original_img,shop_price")
                ->where($type."=1 and is_on_sale=1")
            ->order('goods_id DESC')
            ->page($p,$d)->cache(true,TPSHOP_CACHE_TIME)
            ->select();
        if($favourite_goods){
            $res= array('status'=>1, 'msg'=>'操作成功',"result"=>$favourite_goods);
            $this->ajaxReturn($res);
        }
        $res= array('status'=>0, 'msg'=>'获取失败');
        exit(json_encode($res));
    }
    /*
    *获取省市区三级联动
     */
    public function ajaxRegionList(){
        $first_dir = dirname(dirname(dirname(dirname(__FILE__))));
        $file_name = $first_dir."/runtime/threelevel/threelevel.txt";
        //如果省市区三级联动缓存存在，则直接读取缓存
        if (file_exists($file_name)) {
            $cache_data = file_get_contents($file_name);
            if (!empty($cache_data)){
                $cache_data = json_decode($cache_data, true);
                if ($cache_data) {
                    //判断是否过期
                    $time = time();
                    if($cache_data['time'] + $cache_data['expire'] > $time){
                        $this->ajaxReturn($cache_data['data']);
                    }
                }
            }
        }
        //获取省
        $p = M('region')->Field('name as label,id as value')->where(array('parent_id' => 0, 'level' => 1))->select();
        $three_level = $two_level = array();
        $i = 0;
        //获取市
        foreach ($p as $k => $v) {
            $three_level[$i] = $v;
            $two_level = M('region')->Field('name as label,id as value')->where(array('parent_id' => $v['value'], 'level' => 2))->select();
            $j = 0;
            $three_level[$i]['children'] = $two_level;
            //获取区
            foreach ($three_level[$i]['children'] as $kk => $vv) {

                $three_level[$i]['children'][$kk]['children'] = M('region')->Field('name as label,id as value')->where(array('parent_id' => $vv['value'], 'level' => 3))->select();
            }

            $j++;

            $i++;
        }

        if(!empty($three_level)){

            $cache_data = array('data' => $three_level, 'time' => time(), 'expire' => 804600);
            $cache_data = json_encode($cache_data);
            if (!file_exists($file_name)) {
                mkdir($first_dir."/runtime/threelevel", 0777, true);
            }
            $put_result = file_put_contents($file_name, $cache_data);
            $this->ajaxReturn($three_level);
        }
        $this->ajaxReturn(array('status'=>0,"msg"=>"获取失败！",'result'=>''));
    }
    public function ajaxPaymentList(){
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');
        $this->ajaxReturn($paymentList);
    }
}