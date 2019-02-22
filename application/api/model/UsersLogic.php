<?php

namespace app\api\model;

use think\Model;
use think\Db;

class UsersLogic extends Model
{


    /**
     * @time 2016/09/01
     * @author dyr
     * 设置用户系统消息已读
     */
    public function setSysMessageForRead()
    {
        $user_info = session('user');
        if (!empty($user_info['user_id'])) {
            $data['status'] = 1;
            M('user_message')->where(array('user_id' => $user_info['user_id'], 'category' => 0))->save($data);
        }
    }
    /**
     * 获取商品收藏列表
     * @param $user_id  用户id
     */
    public function get_goods_collect($user_id,$p,$d){
        $count = $this->getGoodsCollectNum($user_id);
        //获取我的收藏列表
        $result = M('goods_collect')->alias('c')
            ->field('c.collect_id,c.add_time,g.goods_id,g.goods_name,g.shop_price,g.is_on_sale,g.store_count,g.cat_id ')
            ->join('goods g','g.goods_id = c.goods_id','INNER')
            ->where("c.user_id = $user_id")
            ->limit($p,$d)
            ->select();
        $return['status'] = 1;
        $return['msg'] = '获取成功';
        $return['result'] = $result;
        $return['show'] = $show;
        return $return;
    }
    /**
     * 添加评论
     * @param $add
     * @return array
     */
    public function add_comment($add){
        if(!$add['order_id'] || !$add['goods_id'])
            return array('status'=>-1,'msg'=>'非法操作','result'=>'');



        //检查订单是否已完成
        $order = M('order')->field('order_status')->where("order_id", $add['order_id'])->where('user_id', $add['user_id'])->find();
        if($order['order_status'] != 2)
            return array('status'=>-1,'msg'=>'该笔订单还未确认收货','result'=>'');

        //检查是否已评论过
        $goods = M('comment')->where(['order_id'=>$add['order_id'],'goods_id'=>$add['goods_id']])->find();
        if($goods)
            return array('status'=>-1,'msg'=>'您已经评论过该商品','result'=>'');

        $row = M('comment')->add($add);
        if($row)
        {
            //更新订单商品表状态
            M('order_goods')->where(array('goods_id'=>$add['goods_id'],'order_id'=>$add['order_id']))->save(array('is_comment'=>1));
            M('goods')->where(array('goods_id'=>$add['goods_id']))->setInc('comment_count',1); // 评论数加一
            // 查看这个订单是否全部已经评论,如果全部评论了 修改整个订单评论状态
            $comment_count   = M('order_goods')->where("order_id", $add['order_id'])->where('is_comment', 0)->count();
            if($comment_count == 0) // 如果所有的商品都已经评价了 订单状态改成已评价
            {
                M('order')->where("order_id",$add['order_id'])->save(array('order_status'=>4));
            }
            return array('status'=>1,'msg'=>'评论成功','result'=>'');
        }
        return array('status'=>-1,'msg'=>'评论失败','result'=>'');
    }

    /**
     * 获取评论列表
     * @param $user_id 用户id
     * @param $status  状态 0 未评论 1 已评论 ,其他 全部
     * @return mixed
     */
    public function getComment($user_id, $status = 2,$p,$d)
    {
        if ($status == 1) {
            //已评论
            $query = M('comment')->alias('c')
                ->join('__ORDER__ o', 'o.order_id = c.order_id')
                ->join('__ORDER_GOODS__ og','c.goods_id = og.goods_id AND c.order_id = og.order_id AND og.is_comment=1')
                ->where('c.user_id', $user_id);
            $query2 = clone($query);
            $commented_count = $query->count();
            $page = new \think\Page($commented_count, 10);
            $comment_list = $query2->field('og.*,o.*')
                ->order('c.add_time', 'desc')
                ->limit($page->firstRow, $page->listRows)
                ->select();
        } else {
            $comment_where = ['og.is_send'=>1];
            if ($status == 0) {
                $comment_where['og.is_comment'] = 0;
            }
            $query = M('order_goods')->alias('og')
                ->join('__ORDER__ o',"o.order_id = og.order_id AND o.user_id=$user_id AND o.order_status IN (2,4)")
                ->where($comment_where);
            $query2 = clone($query);
            $comment_count = $query->count();
            $comment_list = $query2->field('og.*,o.*')
                ->order('o.order_id', 'desc')
                ->limit($p,$d)
                ->select();
        }
        $return['result'] = $comment_list;
        return $return;
    }


    /**
     * 发送验证码: 该方法只用来发送邮件验证码, 短信验证码不再走该方法
     * @param $sender 接收人
     * @param $type 发送类型
     * @return json
     */
    public function send_email_code($sender){
        $sms_time_out = tpCache('sms.sms_time_out');
        $sms_time_out = $sms_time_out ? $sms_time_out : 180;
        //获取上一次的发送时间
        $send = session('validate_code');
        if(!empty($send) && $send['time'] > time() && $send['sender'] == $sender){
            //在有效期范围内 相同号码不再发送
            $res = array('status'=>-1,'msg'=>'规定时间内,不要重复发送验证码');
            return $res;
        }
        $code =  mt_rand(1000,9999);
        //检查是否邮箱格式
        if(!check_email($sender)){
            $res = array('status'=>-1,'msg'=>'邮箱码格式有误');
            return $res;
        }
        $data = D("users") -> field("email") -> where(array(
            "email" => $sender
        )) -> find();
        if($data){
            return array("status" => 0,"msg" => '邮箱已存在');
        }
        $send = send_email($sender,'验证码','您好，你的验证码是：'.$code);
        if($send['status'] == 1){
            $info['code'] = $code;
            $info['sender'] = $sender;
            $info['is_check'] = 0;
            $info['time'] = time() + $sms_time_out; //有效验证时间
            session('validate_code',$info);
            $res = array('status'=>1,'msg'=>'验证码已发送，请注意查收');
        }else{
            $res = $send;
        }
        return $res;
    }

    /*
      * 登陆
      */
    public function login($username,$password){
        $result = array();
        if(!$username || !$password)
            $result= array('status'=>0,'msg'=>'请填写账号或密码');
        $user = M('users')->where("user_name",$username)->find();
        if(!$user){
            $result = array('status'=>-1,'msg'=>'账号不存在!');
        }elseif(encrypt($password) != $user['password']){
            $result = array('status'=>-2,'msg'=>'密码错误!');
        }elseif($user['is_lock'] == 1){
            $result = array('status'=>-3,'msg'=>'账号异常已被锁定！！！');
        }else{
            //查询用户信息之后, 查询用户的登记昵称
            $levelId = $user['level'];
            $levelName = M("user_level")->where("level_id", $levelId)->getField("level_name");
            $user['level_name'] = $levelName;

            $result = array('status'=>1,'msg'=>'登陆成功','result'=>$user);
        }
        return $result;
    }
    /**
     * 注册
     * @param $username  邮箱或手机
     * @param $password  密码
     * @param $password2 确认密码
     * @return array
     */
    public function reg($username,$password,$password2, $push_id=0,$invite=array(),$email){
        $is_validated = 0 ;
//        if(check_email($username)){
//            $is_validated = 1;
//            $map['email_validated'] = 1;
//            $map['nickname'] = $map['email'] = $username; //邮箱注册
//        }
//        if(check_mobile($username)){
//            $is_validated = 1;
//            $map['mobile_validated'] = 1;
//            $map['nickname'] = $map['mobile'] = $username; //手机注册
//        }

        if(!$username)
            return array('status'=>-1,'msg'=>'用户名不能为空');

        if(!$username || !$password)
            return array('status'=>-1,'msg'=>'请输入用户名或密码');

        //验证两次密码是否匹配
        if($password2 != $password)
            return array('status'=>-1,'msg'=>'两次输入密码不一致');
        if(!$email)
            return array('status'=>-1,'msg'=>'邮箱不能为空');
        //验证是否存在用户名
        if(get_user_info($username,5))
            return array('status'=>-1,'msg'=>'用户名已存在');

        $map['user_name'] = $map['nickname'] = $username;
        $map['password'] = encrypt($password);
        $map['email_validated'] == 1;
        $map['email'] = $email;
        $map['reg_time'] = time();
        if(!empty($invite)){
            $map['first_leader'] = $invite['user_id']; // 推荐人id
        }

        // 如果找到他老爸还要找他爷爷他祖父等
        if($map['first_leader'])
        {
            $first_leader = M('users')->where("user_id", $map['first_leader'])->find();
            $map['second_leader'] = $first_leader['first_leader'];
            $map['third_leader'] = $first_leader['second_leader'];
            //他上线分销的下线人数要加1
            M('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
            M('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
            M('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');
        }else
        {
            $map['first_leader'] = 0;
        }

        if(is_array($invite) && !empty($invite)){
            $map['first_leader'] = $invite['user_id'];
            $map['second_leader'] = $invite['first_leader'];
            $map['third_leader'] = $invite['second_leader'];
        }



        // 成为分销商条件
        $distribut_condition = tpCache('distribut.condition');
        if($distribut_condition == 0)  // 直接成为分销商, 每个人都可以做分销
            $map['is_distribut']  = 1;
        $map['push_id'] = $push_id; //推送id
        $map['token'] = md5(time().mt_rand(1,999999999));
        $map['last_login'] = time();

        $user_id = M('users')->insertGetId($map);
        if($user_id === false)
            return array('status'=>-1,'msg'=>'注册失败');

        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        if($pay_points > 0){
            accountLog($user_id, 0,$pay_points, '会员注册赠送积分'); // 记录日志流水
        }
        $user = M('users')->where("user_id", $user_id)->find();
        return array('status'=>1,'msg'=>'注册成功','result'=>$user);
    }
    /**
     * 检查短信/邮件验证码验证码
     * @param unknown $code
     * @param unknown $sender
     * @param unknown $session_id
     * @return multitype:number string
     */
    public function check_validate_code($code, $sender, $type ='email', $session_id=0 ,$scene = -1){

        $timeOut = time();
        $inValid = true;  //验证码失效

        //短信发送否开启
        //-1:用户没有发送短信
        //空:发送验证码关闭
        $sms_status = checkEnableSendSms($scene);

        //邮件证码是否开启
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');



        if($type == 'email'){
            if(!$reg_smtp_enable){//发生邮件功能关闭
                $validate_code = session('validate_code');
                $validate_code['sender'] = $sender;
                $validate_code['is_check'] = 1;//标示验证通过
                session('validate_code',$validate_code);
                return array('status'=>1,'msg'=>'邮件验证码功能关闭, 无需校验验证码');
            }

            $data = D("users") -> field("email") -> where(array(
                "email" => $sender
            )) -> find();
            if($data){
                return array("status" => 0,"msg" => '邮箱已存在');
            }

            if(!$code)return array('status'=>-1,'msg'=>'请输入邮件验证码');
            //邮件
            $data = session('validate_code');
            $timeOut = $data['time'];
            if($data['code'] != $code || $data['sender']!=$sender){
                $inValid = false;
            }
        }
        if(empty($data)){
            $res = array('status'=>-1,'msg'=>'请先获取验证码');
        }elseif($timeOut < time()){
            $res = array('status'=>-1,'msg'=>'验证码已超时失效');
        }elseif(!$inValid)
        {
            $res = array('status'=>-1,'msg'=>'验证失败,验证码有误');
        }else{
            $data['is_check'] = 1; //标示验证通过
            session('validate_code',$data);
            $res = array('status'=>1,'msg'=>'验证成功');
        }
        return $res;
    }


    /**
     * 修改密码
     * @param $user_id  用户id
     * @param $old_password  旧密码
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     * @param bool|true $is_update
     * @return array
     */
    public function password($user_id,$old_password,$new_password,$confirm_password,$is_update=true){

        $user = M('users')->where('user_id', $user_id)->find();
        if(strlen($new_password) < 6)
            return array('status'=>-1,'msg'=>'密码不能低于6位字符','result'=>'');
        if($new_password != $confirm_password)
            return array('status'=>-1,'msg'=>'两次密码输入不一致','result'=>'');
        if($new_password == $old_password)
            return array('status'=>-1,'msg'=>'新旧密码一致','result'=>'');
        //验证原密码
        if($is_update && ($user['password'] != '' && encrypt($old_password) != $user['password']))
            return array('status'=>-1,'msg'=>'密码验证失败','result'=>'');
        $row = M('users')->where("user_id", $user_id)->save(array('password'=>encrypt($new_password)));
        if(!$row)
            return array('status'=>-1,'msg'=>'修改失败','result'=>'');
        return array('status'=>1,'msg'=>'修改成功','result'=>'');
    }

    /*
          * 获取当前登录用户信息
          */
    public function get_info($user_id)
    {
        if (!$user_id) {
            return array('status'=>-1, 'msg'=>'缺少参数');
        }

        $user = M('users')->where('user_id', $user_id)->find();
        if (!$user) {
            return false;
        }

        if (!$user_id) {
            return array('status'=>-1, 'msg'=>'缺少参数');
        }

        $user = M('users')->where('user_id', $user_id)->find();
        if (!$user) {
            return false;
        }
        $levelId = $user['level'];
        $user['levelName'] = M("user_level")->where("level_id", $levelId)->getField("level_name");
        $activityLogic = new \app\common\logic\ActivityLogic;             //获取能使用优惠券个数
        $user['coupon_count'] = $activityLogic->getUserCouponNum($user_id, 0);

        $user['collect_count'] = $this->getGoodsCollectNum($user_id);; //获取收藏数量
        $user['return_count'] = M('return_goods')->where("user_id=$user_id and status<2")->count();   //退换货数量

        $user['waitPay']     = M('order')->where("user_id = :user_id ".C('WAITPAY'))->bind(['user_id'=>$user_id])->count(); //待付款数量
        $user['waitSend']    = M('order')->where("user_id = :user_id ".C('WAITSEND'))->bind(['user_id'=>$user_id])->count(); //待发货数量
        $user['waitReceive'] = M('order')->where("user_id = :user_id ".C('WAITRECEIVE'))->bind(['user_id'=>$user_id])->count(); //待收货数量
        $user['order_count'] = $user['waitPay'] + $user['waitSend'] + $user['waitReceive'];

        $commentLogic = new \app\common\logic\CommentLogic;
        $user['comment_count'] = $commentLogic->getHadCommentNum($user_id); //已评论数
        $user['uncomment_count'] = $commentLogic->getWaitCommentNum($user_id); //待评论数

        return ['status' => 1, 'msg' => '获取成功', 'result' => $user];
    }
    public function getGoodsCollectNum($user_id)
    {
        $count = M('goods_collect')->alias('c')
            ->join('goods g','g.goods_id = c.goods_id','INNER')
            ->where('user_id', $user_id)
            ->count();
        return $count;
    }
    /**
     * 地址添加/编辑
     * @param $user_id 用户id
     * @param $user_id 地址id(编辑时需传入)
     * @return array
     */
    public function add_address($user_id,$address_id=0,$data){
        $post = $data;

        if($address_id == 0)
        {
            $c = M('UserAddress')->where("user_id", $user_id)->count();
            if($c >= 20)
                return array('status'=>-1,'msg'=>'最多只能添加20个收货地址','result'=>'');
        }

        //检查手机格式
        if($post['consignee'] == '')
            return array('status'=>-1,'msg'=>'收货人不能为空','result'=>'');
        if(!$post['province'] || !$post['city'] || !$post['district'])
            return array('status'=>-1,'msg'=>'所在地区不能为空','result'=>'');
        if(!$post['address'])
            return array('status'=>-1,'msg'=>'地址不能为空','result'=>'');
        if(!check_mobile($post['mobile']))
            return array('status'=>-1,'msg'=>'手机号码格式有误','result'=>'');

        //编辑模式
        if($address_id > 0){

            $address = M('user_address')->where(array('address_id'=>$address_id,'user_id'=> $user_id))->find();
            if($post['is_default'] == 1 && $address['is_default'] != 1)
                M('user_address')->where(array('user_id'=>$user_id))->save(array('is_default'=>0));
            $row = M('user_address')->where(array('address_id'=>$address_id,'user_id'=> $user_id))->save($post);
            if(!$row)
                return array('status'=>2,'msg'=>'操作完成','result'=>'');
            return array('status'=>1,'msg'=>'编辑成功','result'=>'');
        }


        //添加模式
        $post['user_id'] = $user_id;

        // 如果目前只有一个收货地址则改为默认收货地址
        $c = M('user_address')->where("user_id", $post['user_id'])->count();
        if($c == 0)  $post['is_default'] = 1;

        $address_id = M('user_address')->add($post);
        //如果设为默认地址
        $insert_id = DB::name('user_address')->getLastInsID();
        $map['user_id'] = $user_id;
        $map['address_id'] = array('neq',$insert_id);

        if($post['is_default'] == 1)
            M('user_address')->where($map)->save(array('is_default'=>0));
        if(!$address_id)
            return array('status'=>-1,'msg'=>'添加失败','result'=>'');


        return array('status'=>1,'msg'=>'添加成功','result'=>$address_id);
    }

    /**
     * 更新用户信息
     * @param $user_id
     * @param $post  要更新的信息
     * @return bool
     */
    public function update_info($user_id,$post=array()){
        $model = M('users')->where("user_id", $user_id);
        $row = $model->setField($post);
        if($row === false)
            return false;
        return true;
    }
    /*
    * 获取订单商品
    */
    public function get_order_goods($order_id){
        $sql = "SELECT og.*,g.commission FROM __PREFIX__order_goods og LEFT JOIN __PREFIX__goods g ON g.goods_id = og.goods_id WHERE order_id = :order_id";
        $bind['order_id'] = $order_id;
        $goods_list = DB::query($sql,$bind);

        $return['status'] = 1;
        $return['msg'] = '';
        $return['result'] = $goods_list;
        return $return;
    }
    /**
     * 取消订单 lxl 2017-4-29
     * @param $user_id  用户ID
     * @param $order_id 订单ID
     * @param string $action_note 操作备注
     * @return array
     */
    public function cancel_order($user_id,$order_id){
        $order = M('order')->where(array('order_id'=>$order_id,'user_id'=>$user_id))->find();
        //检查是否未支付订单 已支付联系客服处理退款
        if(empty($order))
            return array('status'=>-1,'msg'=>'订单不存在','result'=>'');
        if($order['order_status'] == 3){
            return array('status'=>-1,'msg'=>'该订单已取消','result'=>'');
        }
        //检查是否未支付的订单
        if($order['pay_status'] > 0 || $order['order_status'] > 0)
            return array('status'=>-1,'msg'=>'支付状态或订单状态不允许','result'=>'');
        //获取记录表信息
        //$log = M('account_log')->where(array('order_id'=>$order_id))->find();
        //有余额支付的情况
        if($order['user_money'] > 0 || $order['integral'] > 0){
            accountLog($user_id,$order['user_money'],$order['integral'],"订单取消，退回{$order['user_money']}元,{$order['integral']}积分");
        }

        if($order['coupon_price'] >0){
            $res = array('use_time'=>0,'status'=>0,'order_id'=>0);
            M('coupon_list')->where(array('order_id'=>$order_id,'uid'=>$user_id))->save($res);
        }

        $row = M('order')->where(array('order_id'=>$order_id,'user_id'=>$user_id))->save(array('order_status'=>3));

        $data['order_id'] = $order_id;
        $data['action_user'] = 0;
        $data['order_status'] = 3;
        $data['pay_status'] = $order['pay_status'];
        $data['shipping_status'] = $order['shipping_status'];
        $data['log_time'] = time();
        $data['status_desc'] = '用户取消订单';

        M('order_action')->add($data);//订单操作记录

        if(!$row)
            return array('status'=>-1,'msg'=>'操作失败','result'=>'');
        return array('status'=>1,'msg'=>'操作成功');

    }
    /**
     * 设置支付密码
     * @param $user_id  用户id
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     */
    public function paypwd($user_id,$new_password,$confirm_password){
        if(strlen($new_password) < 6)
            return array('status'=>-1,'msg'=>'密码不能低于6位字符','result'=>'');
        if($new_password != $confirm_password)
            return array('status'=>-1,'msg'=>'两次密码输入不一致','result'=>'');
        $row = M('users')->where("user_id",$user_id)->update(array('paypwd'=>encrypt($new_password)));
        if(!$row){
            return array('status'=>-1,'msg'=>'修改失败','result'=>'');
        }
        return array('status'=>1,'msg'=>'修改成功','result'=>'');
    }
    /**
     * 自动取消订单
     * @author lxl 2014-4-29
     * @param $order_id         订单id
     * @param $user_id  用户ID
     * @param $orderAddTime 订单添加时间
     * @param $setTime  自动取消时间/天 默认1天
     */
    public function  abolishOrder($user_id,$order_id,$orderAddTime='',$setTime=1){
        $abolishtime = strtotime("-$setTime day");
        if($orderAddTime<$abolishtime) {
            $action_note = '超过' . $setTime . '天未支付自动取消';
            $result = $this->cancel_order($user_id,$order_id,$action_note);
            return $result;
        }
    }
    /**
     * 账户明细
     */
    public function account($user_id, $type='all',$p,$d){
        if($type == 'all'){
            $count = M('account_log')->where("user_money!=0 and user_id=" . $user_id)->count();

            $account_log = M('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("user_money!=0 and user_id=" . $user_id)
                ->order('log_id desc')->limit($p,$d)->select();
        }else{
            $where = $type=='plus' ? " and user_money>0 " : " and user_money<0 ";
            $count = M('account_log')->where("user_id=" . $user_id.$where)->count();
            $account_log = Db::name('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("user_id=" . $user_id.$where)
                ->order('log_id desc')->limit($p,$d)->select();
        }

        $result['account_log'] = $account_log;
        return $result;
    }
    /*
     * 获取优惠券
     */
    public function get_coupon($user_id, $type =0,$p =0,$d =5, $orderBy = null,$order_money = 0)
    {
        $activityLogic = new \app\common\logic\ActivityLogic;
        $count = $activityLogic->getUserCouponNum($user_id, $type, $orderBy,$order_money );
        $list = $activityLogic->getUserCouponList($p, $d, $user_id, $type, $orderBy,$order_money);
        $return['status'] = 1;
        $return['msg'] = '获取成功';
        $return['result'] = $list;
        return $return;
    }
    /**
     * 确定订单的使用优惠券
     * @author lxl
     * @time 2017
     */
    public function checkcoupon()
    {
        $type = input('type');
        $now = time();
        $cartLogic = new CartLogic();
        // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList(1);//获取购物车商品
        $cartTotalPrice = array_sum(array_map(function($val){return $val['total_fee'];}, $cartList));//商品优惠总价
        $where = '';
        if(empty($type)){
            $where = " c2.uid = {$this->user_id} and {$now} < c1.use_end_time and {$now} > c1.use_start_time and c1.condition <= {$cartTotalPrice} ";
        }
        if($type == 1){
            $where = " c2.uid = {$this->user_id} and c1.use_end_time < {$now} or c1.use_start_time > {$now} or {$cartTotalPrice}  < c1.condition ";
        }
        $coupon_list = DB::name('coupon')
            ->alias('c1')
            ->field('c1.name,c1.money,c1.condition,c1.use_end_time, c2.*')
            ->join('coupon_list c2','c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0','LEFT')
            ->where($where)
            ->select();
        $this->assign('coupon_list', $coupon_list); // 优惠券列表
        return $this->fetch();
    }
    /**
     * 积分明细
     */
    public function points($user_id, $type='all',$p,$d)
    {

        if($type == 'all'){
            $count = M('account_log')->where("user_id=" . $user_id ." and pay_points!=0 ")->count();
            $account_log = M('account_log')->where("user_id=" . $user_id." and pay_points!=0 ")->order('log_id desc')->limit($p,$d)->select();

        }else{
            $where = $type=='plus' ? " and pay_points>0 " : " and pay_points<0 ";
            $count = M('account_log')->where("user_id=" . $user_id.$where)->count();
            $account_log = M('account_log')->where("user_id=" . $user_id.$where)->order('log_id desc')->limit($p,$d)->select();
        }

        $result['account_log'] = $account_log;
        return $result;
    }
}