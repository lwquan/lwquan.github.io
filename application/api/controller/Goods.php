<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */
namespace app\api\controller;
use app\api\model\GoodsLogic;
use app\common\logic\ActivityLogic;
use think\Page;
use think\Request;
use think\Verify;
use think\db;
use think\AjaxPage;
class Goods extends ApiBase {





    /**
     * 分类列表显示
     */
    public function categoryList(){
        $goodsLogic = new GoodsLogic();
        $goods_category_tree = $goodsLogic->get_category_list();
        $res = array('status'=>1,'msg'=>'请求成功','result'=>$goods_category_tree);
        $this->ajaxReturn($res);
    }
    /**
     * 商品搜索列表页
     */
    public function search(){
        $filter_param = array(); // 帅选数组
        $id = I('get.id/d',0); // 当前分类id
        $brand_id = I('brand_id/d',0);
        $sort = I('sort','goods_id'); // 排序
        $sort_asc = I('sort_asc','asc'); // 排序
        $price = I('price',''); // 价钱
        $p = I('pagestart',0);
        $d = I('speed',5);
        $start_price = trim(I('start_price','0')); // 输入框价钱
        $end_price = trim(I('end_price','0')); // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入帅选条件中
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $price  && ($filter_param['price'] = $price); //加入帅选条件中
        $q = urldecode(trim(I('key',''))); // 关键字搜索
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中
        $qtype = I('qtype','');
        $where  = array('is_on_sale' => 1);
        if($qtype){
            $filter_param['qtype'] = $qtype;
            $where[$qtype] = 1;
        }
        if($q) $where['goods_name'] = array('like','%'.$q.'%');

        $goodsLogic = new GoodsLogic();
        $filter_goods_id = M('goods')->where($where)->cache(true)->getField("goods_id",true);

        // 过滤帅选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'search'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'search'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'search'); // 获取指定分类下的帅选品牌

        $count = count($filter_goods_id);
        if($count > 0)
        {
            $goods_list = M('goods')->where("goods_id", "in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($p,$d)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
//        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组

        $data['goods_list'] = $goods_list;
//        $data['goods_category'] = $goods_category;
        $data['goods_images'] = $goods_images;  // 相册图片
        $data['filter_menu'] = $filter_menu;  // 帅选菜单
        $data['filter_brand'] = $filter_brand;// 列表页帅选属性 - 商品品牌
        $data['filter_price'] = $filter_price;// 帅选的价格期间
        $data['filter_param'] = $filter_param; // 帅选条件
        $res = array('status'=>1,'msg'=>'请求成功','result'=>$data);
        $this->ajaxReturn($res);
    }


    /**
     * 领取优惠券
     */
    public function couponList(){
        $p = input('p', 1);
        $cat_id = input('cat_id', 0);
        $goods_id = input('goods_id', 0);
        $activityLogic = new ActivityLogic();
        $result = $activityLogic->getCouponCenterList($cat_id,$this->user_id, $p,$goods_id);
        $return = array(
            'status' => 1,
            'msg' => '获取成功',
            'result' => $result ,
        );
        $this->ajaxReturn($return);
    }




    /**
     * 商品列表页
     */
    public function goodsList(){
        $filter_param = array(); // 帅选数组
        $id = I("get.id/d",0); // 当前分类id
        $brand_id = I('get.brand_id/d',0);

        $spec = I('spec',0); // 规格
        $attr = I('attr',''); // 属性

        $sort = I('sort','goods_id'); // 排序
        $sort_asc = I('sort_asc','asc'); // 排序
        $price = I('price',''); // 价钱
        $start_price = trim(I('start_price','0')); // 输入框价钱
        $end_price = trim(I('end_price','0')); // 输入框价钱
        $p = I('pagestart',0);
        $d = I('speed',5);
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入帅选条件中
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $spec  && ($filter_param['spec'] = $spec); //加入帅选条件中
        $attr  && ($filter_param['attr'] = $attr); //加入帅选条件中
        $price  && ($filter_param['price'] = $price); //加入帅选条件中
        $q = urldecode(trim(I('key',''))); // 关键字搜索
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中
        $where  = array('is_on_sale' => 1);
        if($q) $where['goods_name'] = array('like','%'.$q.'%');


        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
        // 分类菜单显示
        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);

        // 帅选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson ($id);

        $filter_goods_id = M('goods')->where("is_on_sale=1")->where($where)->where("cat_id", "in" ,implode(',', $cat_id_arr))->cache(true)->getField("goods_id",true);

        // 过滤帅选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        if($spec)// 规格
        {
            $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个帅选条件的结果 的交集
        }
        if($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_3); // 获取多个帅选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
//        $sel =I('sel');
//        if($sel)
//        {
//            $goods_id_4 = $goodsLogic->getFilterSelected($sel,$cat_id_arr);
//            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
//        }
        $typeArr = I('typeArr','');

        if($typeArr){
            $goods_id_5 = $goodsLogic->getFilterTypeSelected($typeArr,$cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_5);
        }
//        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'goodsList'); // 获取显示的帅选菜单
//        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'goodsList'); // 帅选的价格期间
//        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'goodsList'); // 获取指定分类下的帅选品牌
//        $filter_spec  = $goodsLogic->get_filter_spec($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选规格
//        $filter_attr  = $goodsLogic->get_filter_attr($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选属性

        $count = count($filter_goods_id);
        if($count > 0)
        {
            $goods_list = M('goods')->where("goods_id","in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($p,$d)->select();
//            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
//            if($filter_goods_id2)
//                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
        $data['goods_list'] = $goods_list;
//        $data['goods_images'] = $goods_images;  // 相册图片
//        $data['filter_menu'] = $filter_menu;  // 帅选菜单
//        $data['filter_spec'] = $filter_spec;  // 帅选规格
//        $data['filter_attr'] = $filter_attr; // 帅选属性
//        $data['filter_brand'] = $filter_brand;// 列表页帅选属性 - 商品品牌
//        $data['filter_price'] = $filter_price;// 帅选的价格期间
//        $data['goodsCate'] = $goodsCate;
//        $data['cateArr'] = $cateArr;
//        $data['filter_param'] = $filter_param; // 帅选条件
//        $data['cat_id'] = $id;
//        $data['sort_asc'] = $sort_asc == 'asc' ? 'desc' : 'asc';
        $res = array('status'=>1,'msg'=>'请求成功','result'=>$data);
        $this->ajaxReturn($res);
    }


    /**
     * 商品列表筛选页
     */
    public function filtrateList(){
        $filter_param = array(); // 帅选数组
        $id = I("get.id/d",''); // 当前分类id
        $q = urldecode(trim(I('key',''))); // 关键字搜索
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中
        $where  = array('is_on_sale' => 1);
        if($q) $where['goods_name'] = array('like','%'.$q.'%');
        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
        if(empty($id)) {
            $goodsLogic = new GoodsLogic();
            $cateArr = $goodsLogic->get_category_list();
        }

        else {
            // 分类菜单显示
            $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
            $cateArr = $goodsLogic->get_goods_cate($goodsCate);
        }
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆


        // 帅选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson ($id);

        $filter_goods_id = M('goods')->where("is_on_sale=1")->where($where)->where("cat_id", "in" ,implode(',', $cat_id_arr))->cache(true)->getField("goods_id",true);

        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'goodsList'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'goodsList'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'goodsList'); // 获取指定分类下的帅选品牌
        $filter_attr  = $goodsLogic->get_filter_attr($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选属性

        $count = count($filter_goods_id);
        $data['filter_menu'] = $filter_menu;  // 帅选菜单
        $data['filter_attr'] = $filter_attr; // 帅选属性
        $data['filter_brand'] = $filter_brand;// 列表页帅选属性 - 商品品牌
        $data['filter_price'] = $filter_price;// 帅选的价格期间
        $data['cateArr'] = $cateArr;
        $data['filter_param'] = $filter_param; // 帅选条件
        $res = array('status'=>1,'msg'=>'请求成功','result'=>$data);
        $this->ajaxReturn($res);
    }



    /**
     * 商品详情页
     */
    public function goodsInfo(){
        $goodsLogic = new GoodsLogic();
        $goods_id = I("get.id/d")?I("get.id/d"):143;

        $goodsModel = new \app\common\model\Goods();
        $goods = $goodsModel::get($goods_id);
        if(empty($goods) || ($goods['is_on_sale'] == 0)){
            $res = array('status'=>0,'msg'=>'此商品不存在或者已下架');
            $this->ajaxReturn($res);
        }
        $goodsPromFactory = new \app\common\logic\GoodsPromFactory();
        if (!empty($goods['prom_id']) && $goodsPromFactory->checkPromType($goods['prom_type'])) {
            $goodsPromLogic = $goodsPromFactory->makeModule($goods, null);//这里会自动更新商品活动状态，所以商品需要重新查询
            $goods = $goodsPromLogic->getGoodsInfo();//上面更新商品信息后需要查询
        }
        if($goods['brand_id']){
            $brnad = M('brand')->where("id", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        if ($this->user_id) {
            $goodsLogic->add_visit_log($this->user_idsel, $goods);
        }
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)->select(); // 商品 图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
		$filter_spec = $goodsLogic->get_spec($goods_id);
        $spec_goods_price  = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count,item_id"); // 规格 对应 价格 库存表
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $data['spec_goods_price'] = $spec_goods_price; // 规格 对应 价格 库存表
        $data['spec_goods_price'] = json_encode($spec_goods_price,true); // 规格 对应 价格 库存表
      	$goods['sale_num'] = M('order_goods')->where(['goods_id'=>$goods_id,'is_send'=>1])->count();
      	//当前用户收藏
        $user_id = cookie('user_id');
        $collect = M('goods_collect')->where(array("goods_id"=>$goods_id ,"user_id"=>$user_id))->count();
        $goods_collect_count = M('goods_collect')->where(array("goods_id"=>$goods_id))->count(); //商品收藏数
        $data['collect'] = $collect;
        $data['commentStatistics'] = $commentStatistics;//评论概览
        $data['goods_attribute'] = $goods_attribute;//属性值
        $data['goods_attr_list'] = $goods_attr_list;//属性列表
        $data['filter_spec'] = $filter_spec;//规格参数
        $data['goods_images_list'] = $goods_images_list;//商品缩略图
        $data['goods'] = $goods->toArray();
        $data['goods']['goods_content'] = htmlspecialchars_decode($data['goods']['goods_content']);
        $data['goods_collect_count'] = $goods_collect_count;//商品收藏人数
//        dump($data);
        $res = array('status'=>1,'msg'=>'请求成功','result'=>$data);
        $this->ajaxReturn($res);
    }

    /**
     * 商品物流配送和运费
     */
    public function dispatching()
    {
        $goods_id = I('goods_id/d');//143
        $region_id = I('region_id/d')?I('region_id/d'):3;
        $goods_logic = new GoodsLogic();
        $dispatching_data = $goods_logic->getGoodsDispatching($goods_id,$region_id);
//        $region_list = get_region_list();

        $this->ajaxReturn($dispatching_data);
    }

    /*
     * ajax获取商品评论
     */
    public function ajaxComment()
    {
        $goods_id = I("goods_id/d", 0);
        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评
        $p = I('pagestart',0);
        $d = I('speed');
        if ($commentType == 5) {
            $where = array(
                'goods_id' => $goods_id, 'parent_id' => 0, 'img' => ['<>', ''],'is_show'=>1
            );
        } else {
            $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
            $where = array('is_show'=>1,'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[$commentType]]);
        }

        $count = M('Comment')->where($where)->count();
        $list = M('Comment')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)
            ->order("add_time desc")
            ->limit($p,$d)
            ->select();
        $replyList = M('Comment')->where(['goods_id' => $goods_id, 'parent_id' => ['>', 0]])->order("add_time desc")->select();
        foreach ($list as $k => $v) {
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
            $replyList[$v['comment_id']] = M('Comment')->where(['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => $v['comment_id']])->order("add_time desc")->select();
        }
        $res['goods_id'] = $goods_id;//商品id
        $res['commentlist'] = $list;// 商品评论
        $res['commentType'] = $commentType;// 1 全部 2好评 3 中评 4差评 5晒图
        $res['replyList'] = $replyList; // 管理员回复
        $res['count'] = $count; // 总条数
        $res['page_count'] = $page_count; // 页数
        $res['current_count'] = $page_count * I('p'); // 当前条
        $res['p'] = I('p'); // 当前条
//        dump($res);
        $this->ajaxReturn($res);
    }
//    选择属性更新价格与库存
    public function activity(){
        $goods_id = input('goods_id/d');//商品id
        $item_id = input('item_id/d');//规格id
        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id,'',true);
        $goodsPromFactory = new app\common\logic\GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($goods['prom_type'])) {
            //这里会自动更新商品活动状态，所以商品需要重新查询
            if($item_id){
                $specGoodsPrice = SpecGoodsPrice::get($item_id,'',true);
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,$specGoodsPrice);
            }else{
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,null);
            }
            //检查活动是否有效
            if($goodsPromLogic->checkActivityIsAble()){
                $goods = $goodsPromLogic->getActivityGoodsInfo();
                $goods['activity_is_on'] = 1;
                $this->ajaxReturn(['status'=>1,'msg'=>'该商品参与活动','result'=>['goods'=>$goods]]);
            }else{
                $goods['activity_is_on'] = 0;
                $this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
            }
        }
        dump($goods);
        $this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
    }

    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods(){
        $goods_id = I('goods_id/d');
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $goodsLogic = new GoodsLogic();
        $result = $goodsLogic->collect_goods($this->user_id,$goods_id);
        exit(json_encode($result));
    }


    /**
     *  点赞
     * @author lxl
     * @time  17-4-20
     * 拷多商家Order控制器
     */
    public function ajaxZan()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $comment_id = I('post.comment_id/d');
        $user_id = $this->user_id;
        $comment_info = M('comment')->where(array('comment_id' => $comment_id))->find();  //获取点赞用户ID
        $comment_user_id_array = explode(',', $comment_info['zan_userid']);
        if (in_array($user_id, $comment_user_id_array)) {  //判断用户有没点赞过
            $this->ajaxReturn(array('status'=>1,'msg'=>"请不要重复点赞"));
        } else {
            array_push($comment_user_id_array, $user_id);  //加入用户ID
            $comment_user_id_string = implode(',', $comment_user_id_array);
            $comment_data['zan_num'] = $comment_info['zan_num'] + 1;  //点赞数量加1
            $comment_data['zan_userid'] = $comment_user_id_string;
            M('comment')->where(array('comment_id' => $comment_id))->save($comment_data);
            $this->ajaxReturn(array('status'=>1,'msg'=>"点赞成功"));
        }

    }






}