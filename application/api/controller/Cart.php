<?php
namespace app\api\controller;
use app\api\model\CartLogic;
use app\api\model\UsersLogic;
use think\Page;
use think\Request;
use think\Verify;
use think\db;
use think\AjaxPage;
class Cart extends ApiBase {
    public $user = array();

    /**
     * 析构流函数
     */
    public function  __construct() {
        parent::__construct();
        $this->cartLogic = new CartLogic();

        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $this->user_id)->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->assign('user', $user); //存储用户信息
            // 给用户计算会员价 登录前后不一样
            if ($user) {
                $user['discount'] = (empty($user['discount'])) ? 1 : $user['discount'];
                if ($user['discount'] != 1) {
                    $c = Db::name('cart')->where(['user_id' => $this->user_id, 'prom_type' => 0])->where('member_goods_price = goods_price')->count();
                    $c && Db::name('cart')->where(['user_id' => $this->user_id, 'prom_type' => 0])->update(['member_goods_price' => ['exp', 'goods_price*' . $user['discount']]]);
                }
            }
        }
    }


    /**
     * 更新购物车，并返回计算结果
     */
    public function AsyncUpdateCart()
    {

        $cart = input('cart/a', []);
        $cartLogic = new CartLogic();
        $result = $cartLogic->AsyncUpdateCart($cart);
        $this->ajaxReturn($result);
    }

    public function getAll(){
        $cartLogic = new CartLogic();
        $cartList = $cartLogic->getCartList();//用户购物车
        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数
        $res['cartList'] = $cartList;
        $res['userCartGoodsTypeNum'] = $userCartGoodsTypeNum;
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'result' => $res]);
    }
    /**
     *  购物车加减
     */
    public function changeNum(){
        $cart_id = input('cart_id/d');
        $goods_num = input('goods_num/d');
        if (empty($cart_id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart_id,$goods_num);
        $this->ajaxReturn($result);
    }




    /**
     * 删除购物车商品
     */
    public function delete(){
        $cart_ids = I('get.cart_ids', 0);
        $cartLogic = new CartLogic();
        $result = $cartLogic->delete($cart_ids);
        if($result !== false){
            $this->ajaxReturn(['status'=>1,'msg'=>'删除成功','result'=>$result]);
        }else{
            $this->ajaxReturn(['status'=>0,'msg'=>'删除失败','result'=>$result]);
        }
    }

    /**
     * ajax 将商品加入购物车
     */
    function addCart()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d");// 商品数量
        $item_id = I("item_id/d"); // 商品规格id
        if(empty($goods_id)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'请选择要购买的商品','result'=>'']);
        }
        if(empty($goods_num)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'购买商品数量不能为0','result'=>'']);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setGoodsModel($goods_id);
        if($item_id){
            $cartLogic->setSpecGoodsPriceModel($item_id);
        }
        $cartLogic->setGoodsBuyNum($goods_num);
        $result = $cartLogic->addGoodsToCart();
        exit(json_encode($result));
    }




    /**
     * 购物车第二步确定页面
     */
    public function cart2()
    {

        $cart_status = I('cart_type/d');
        if($cart_status == 'lh_shop'){
            $goods_id = I("goods_id/d"); // 商品id
            $goods_num = I("goods_num/d");// 商品数量
            $item_id = I("item_id/d"); // 商品规格id
            if(empty($goods_id)){
                $this->ajaxReturn(['status'=>-1,'msg'=>'请选择要购买的商品','result'=>'']);
            }
            if(empty($goods_num)){
                $this->ajaxReturn(['status'=>-1,'msg'=>'购买商品数量不能为0','result'=>'']);
            }

            $cartLogic = new CartLogic();
            $cartLogic->setGoodsModel($goods_id);
            if($item_id){
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }

            $cartLogic->setGoodsBuyNum($goods_num);
            M('cart')->where(['user_id'=>$this->user_id])->save(['selected' => 0]);//先将购物车全部商品设置为不选中
            $result = $cartLogic->addGoodsToCart();
        }
        $address_id = I('address_id/d');
        $cartLogic = new CartLogic();
        $cid = I('cid/d');

        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = M('user_address')->where(['user_id'=>$this->user_id,'is_default'=>1])->find();
        }
        if(empty($address)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'请添加默认地址']);
        }else{
            $res['address'] = $address;
        }

        $cartList = $cartLogic->getCartList(1); // 获取购物车商品

        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList);
        // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的
        $couponWhere = [
            'c2.uid' => $this->user_id,
            'c1.use_end_time' => ['gt', time()],
            'c1.use_start_time' => ['lt', time()],
            'c1.condition' => ['elt', $cartPriceInfo['total_fee']]
        ];
        $couponList = Db::name('coupon')->alias('c1')
            ->field('c1.name,c1.money,c1.condition,c2.*')
            ->join('__COUPON_LIST__ c2', ' c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0', 'inner')
            ->where($couponWhere)
            ->select();
        if(!empty($cid)){
            $checkconpon = M('coupon')->field('id,name,money')->where("id", $cid)->find();    //要使用的优惠券
            $checkconpon['lid'] = I('lid/d');
        }

        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true,TPSHOP_CACHE_TIME)->select();// 物流公司
        if($cartList) {
            $orderGoods = collection($cartList)->toArray();
        }
        foreach($shippingList as $k => $v) {
            $dispatchs = calculate_price($this->user_id, $orderGoods, $v['code'], 0, $address['province'], $address['city'], $address['district']);
            if ($dispatchs['status'] !== 1) {
                $this->error($dispatchs['msg']);
            }
            $shippingList[$k]['freight'] = $dispatchs['result']['shipping_price'];
        }
        $res['couponList'] = $couponList;// 优惠券列表
        $res['shippingList'] = $shippingList;// 物流公司
        $res['cartList'] = $cartList;// 购物车的商品
        $res['cartPriceInfo'] = $cartPriceInfo; // 总计
        $res['checkconpon'] = $checkconpon;//
        exit(json_encode($res));
    }











    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3(){
        if($this->user_id == 0){
            exit(json_encode(array('status'=>-100,'msg'=>"登录超时请重新登录!",'result'=>null))); // 返回结果状态
        }
        $address_id = I("address_id/d"); //  收货地址id
        $shipping_code =  I("shipping_code"); //  物流编号
        $invoice_title = I('invoice_title'); // 发票
        $coupon_id =  I("coupon_id/d"); //  优惠券id
        $couponCode =  I("couponCode"); //  优惠券代码
        $pay_points =  I("pay_points/d",0); //  使用积分
        $user_money =  I("user_money/f",0); //  使用余额
        $user_note = trim(I('user_note'));   //买家留言
        $paypwd =  I("paypwd",''); // 支付密码

        $user_money = $user_money ? $user_money : 0;

        $cartLogic = new CartLogic();
        if($cartLogic->getUserCartOrderCount() == 0 ) {
            exit(json_encode(array('status'=>-2,'msg'=>'你的购物车没有选中商品','result'=>null))); // 返回结果状态
        }
        if(!$address_id) exit(json_encode(array('status'=>-3,'msg'=>'请先填写收货人信息','result'=>null))); // 返回结果状态
        if(!$shipping_code) exit(json_encode(array('status'=>-4,'msg'=>'请选择物流信息','result'=>null))); // 返回结果状态
        $address = M('UserAddress')->where("address_id", $address_id)->find();
        $order_goods = M('cart')->where(['user_id'=>$this->user_id,'selected'=>1])->select();
        $result = calculate_price($this->user_id,$order_goods,$shipping_code,0,$address['province'],$address['city'],$address['district'],$pay_points,$user_money,$coupon_id,$couponCode);

        if($result['status'] < 0)
            exit(json_encode($result));
        // 订单满额优惠活动
        $order_prom = get_order_promotion($result['result']['order_amount']);
        $result['result']['order_amount'] = $order_prom['order_amount'] ;
        $result['result']['order_prom_id'] = $order_prom['order_prom_id'] ;
        $result['result']['order_prom_amount'] = $order_prom['order_prom_amount'] ;

        $car_price = array(
            'postFee'      => $result['result']['shipping_price'], // 物流费
            'couponFee'    => $result['result']['coupon_price'], // 优惠券
            'balance'      => $user_money, // 余额
            'pointsFee'    => $pay_points, // 积分
            'payables'     => $result['result']['order_amount'], // 应付金额
            'goodsFee'     => $result['result']['goods_price'],// 商品价格
            'order_prom_id' => $result['result']['order_prom_id'], // 订单优惠活动id
            'order_prom_amount' => $result['result']['order_prom_amount'], // 订单优惠活动优惠了多少钱
        );

        // 提交订单
        if($_REQUEST['act'] == 'submit_order') {

            $pay_name = '';
            if (!empty($pay_points) || !empty($user_money)) {
                if ($this->user['is_lock'] == 1) {
                    exit(json_encode(array('status'=>-5,'msg'=>"账号异常已被锁定，不能使用余额支付！",'result'=>null))); // 用户被冻结不能使用余额支付
                }
                if (empty($this->user['paypwd'])) {
                    exit(json_encode(array('status'=>-6,'msg'=>'请先设置支付密码','result'=>null)));
                }
                if (empty($paypwd)) {
                    exit(json_encode(array('status'=>-7,'msg'=>'请输入支付密码','result'=>null)));
                }
                if (encrypt($paypwd) !== $this->user['paypwd']) {
                    exit(json_encode(array('status'=>-8,'msg'=>'支付密码错误','result'=>null)));
                }
                $pay_name = $user_money ? '余额支付' : '积分兑换';
            }
            if (!empty($pay_points) || !empty($user_money)) {
                if ($this->user['is_lock'] == 1) {
                    exit(json_encode(array('status'=>-5,'msg'=>"账号异常已被锁定，不能使用余额支付！",'result'=>null))); // 用户被冻结不能使用余额支付
                }
                $pay_name = $user_money ? '余额支付' : '积分兑换';
            }

            $orderLogic = new \app\common\logic\OrderLogic();
            $result = $orderLogic->addOrder($this->user_id,$address_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$user_note,$pay_name); // 添加订单
            exit(json_encode($result));
        }
        $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price); // 返回结果状态
        exit(json_encode($return_arr));
    }

    /*
        * 订单支付页面
    */
    public function cart4(){

        $order_id = I('order_id/d');
        $order_where = ['user_id'=>$this->user_id,'order_id'=>$order_id];
        $order = M('Order')->where($order_where)->find();
        if($order['order_status'] == 3){
            exit(json_encode(['status'=>1,'msg'=>'该订单已取消']));
        }

        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if($order['pay_status'] == 1){
            exit(json_encode(['status'=>1,'msg'=>'订单已经支付过了']));
        }
        $payment_where['type'] = 'payment';
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            //微信浏览器
            if($order['order_prom_type'] == 4 || $order['order_prom_type'] == 1){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            }else{
                $payment_where['code'] = array('in',array('weixin','cod'));
            }
        }else{
            if($order['order_prom_type'] == 4 || $order['order_prom_type'] == 1){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq','cod');
            }
            $payment_where['scene'] = array('in',array('0','1','3','4'));
        }

        if($order['order_prom_type'] != 4){
            $userlogic = new UsersLogic();
            $res = $userlogic->abolishOrder($order['user_id'],$order['order_id'],$order['add_time']);  //检测是否超时没支付
            if($res['status']==1)
                exit(json_encode(['status'=>1,'msg'=>'订单超时未支付已自动取消']));
        }
        $payment_where['status'] = 1;
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        if($order['order_prom_type'] == 4 || in_array(1,$orderGoodsPromType)){
            $payment_where['code'] = array('neq','cod');
        }
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            //判断当前浏览器显示支付方式
            if(($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())){
                unset($paymentList[$key]);
            }
        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price); // 返回结果状态
       $res['paymentList']=$paymentList;
       $res['bank_img']=$bank_img;
       $res['order']=$order;
       $res['bankCodeList']=$bankCodeList;
       $res['pay_date'] = date('Y-m-d', strtotime("+1 day"));
       $this->ajaxReturn(['status'=>1,'msg'=>'计算成功','result'=>$res]);

    }


}



























