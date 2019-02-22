<?php

namespace app\api\controller;

use app\api\model\UsersLogic;
use app\common\logic\MessageLogic;
use app\common\logic\CartLogic;
use app\common\logic\OrderLogic;
use app\common;
use think\Page;
use think\Request;
use think\Verify;
use think\db;

class User extends ApiBase
{

    public $user = array();
    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 前端发送短信方法: APP/WAP/PC 共用发送方法
     */
    public function send_validate_code(){

        $sender = I('email');
        //发送邮件验证码
        $logic = new UsersLogic();
        $res = $logic->send_email_code($sender);
        $this->ajaxReturn($res);
    }



    /**
     * 验证码获取
     */
    public function verify()
    {

        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 30,
            'length' => 4,
            'imageH' =>  60,
            'imageW' =>  300,
            'fontttf' => '5.ttf',
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }
    /*
    * 登录
    */
    public function login(){
        $res = $this->validate(input('get.'),[
            'username' => 'require',
            'password' => 'require',
        ]);

        //验证码验证
        if (isset($_GET['verify_code'])) {
            $verify_code = I('get.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_login')) {
                $res = array('status' => 0, 'msg' => '验证码错误');
                exit(json_encode($res));
            }
        }
        if ((int)$res === 1) {
            $logic = new UsersLogic();;
            $res = $logic->login(input('get.username'), input('get.password'));
            session('user', $res['result']);
            if ($res['status'] == 1) {
                $this->user_id = $res['result']['user_id'];
            }
        }
        else {
            $res= array('status'=>0,'msg'=>'请填写账号或密码');
        }
        exit(json_encode($res));
    }
    /**
     *  注册
     */
    public function reg()
    {
        // cache可以用于select、find和getField方法，以及其衍生方法，使用
        // cache方法后，在缓存有效期之内不会再次进行数据库查询操作，而是直
        // 接获取缓存中的数据，关于数据缓存的类型和设置可以参考缓存部分。
        $res = $this->validate(input('post.'),[
            'username' => 'require',
            'password' => 'require',
            'password2' => 'require'
        ]);
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
        $username = input('post.username');
        $password = input('post.password');
        $password2 = input('post.password2');
        $email = input('post.email');
        $code = I('post.email_code', '');
        $logic = new UsersLogic();
        //是否开启注册邮箱验证码机制
        if(check_email($email)){
            if($reg_smtp_enable){
                //邮件功能未关闭
                $check_code = $logic->check_validate_code($code, $email);

                if($check_code['status'] != 1){
                    $this->ajaxReturn($check_code);
                }
            }
        }
        if ((int)$res === 1) {
            $invite = input("post.invite");//邀请人可有可无
            if(!empty($invite)){
                $invite = get_user_info($invite,2);//根据手机号查找邀请人
            }
            $res = $logic->reg($username, $password, $password2,0,$invite,$email);
        }
        else {
            $res= array('status'=>0,'msg'=>$res);
        }
        exit(json_encode($res));
    }

    /*
      * 密码修改
      */
    public function password()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        if (IS_POST) {
            $logic = new UsersLogic();
            $user_id = $this->user_id;
            $userLogic = new UsersLogic();
            $data = $userLogic->password($user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
            if ($data['status'] == -1)
                $this->ajaxReturn(['status'=>-1,'msg'=>$data['msg']]);
            $this->ajaxReturn(['status'=>1,'msg'=>$data['msg']]);
            exit;
        }
    }
    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();

        $new_password = trim(I('new_password'));
        $confirm_password = trim(I('confirm_password'));
        $oldpaypwd = trim(I('old_password'));
        //以前设置过就得验证原来密码
        if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
            $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>'']);
        }
        $userLogic = new UsersLogic();
        $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
        $this->ajaxReturn($data);
    }


    /*
      * 忘记密码
      */
    public function forget_pwd()
    {

        $username = I('username');
        $email = I('email');
        $code = I('email_code');
        $new_password = I('new_password');
        $confirm_password = I('confirm_password');
        $logic = new UsersLogic();
        if (IS_POST) {
            if (!empty($username)) {
                if(!$new_password || !$confirm_password) {
                    $this->ajaxReturn(['status'=>-1,'msg'=>'密码不能为空']);
                }
                if (!check_email($email)) {
                    $this->ajaxReturn(['status'=>-1,'msg'=>'请输入正确邮箱']);
                }
                else {
                    $check_code = $logic->check_validate_code($code, $email);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
                $user = M('users')->where("user_name", $username)->whereOr('email', $email)->find();

                if ($user) {
                    if ($new_password != $confirm_password) {
                        $this->ajaxReturn(['status'=>-1,'msg'=>'两次密码不一致']);
                    }
                    M('users')->where("user_id", $user['user_id'])->save(array('password' => encrypt($new_password)));
                    session('validate_code', null);
                    $this->ajaxReturn(['status'=>1,'msg'=>'新密码已设置行牢记新密码']);
                } else {
                    $this->ajaxReturn(['status'=>-1,'msg'=>'用户名不存在，请检查']);
                }
            }
        }
    }

    /*
         * 个人信息
         */
    public function userinfo()
    {

        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $user_id = $this->user_id;
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($user_id); // 获取用户信息
        $user_info = $user_info['result'];

        $res= array('status'=>1,'msg'=>"操作成功",'result' =>$user_info);
        //修改个人信息
        if (input("post.")) {

            //上传头像
            $user_id = $this->user_id;

            if ($_FILES['head_pic']['tmp_name']) {
                $file = $this->request->file('head_pic');
                $validate = ['size'=>1024 * 1024 * 3,'ext'=>'jpg,png,gif,jpeg'];
                $dir = 'public/upload/head_pic/';
                if (!($_exists = file_exists($dir))){
                    $isMk = mkdir($dir);
                }
                $parentDir = date('Ymd');
                $info = $file->validate($validate)->move($dir, true);
                if($info){
                    $post['head_pic'] = '/'.$dir.$parentDir.'/'.$info->getFilename();
                }else{
                    $res= array('status'=>0,'msg'=>$file->getError());
                    $this->ajaxReturn($res);
                }
            }


            input('post.nickname') ? $post['nickname'] = input('post.nickname') : false; //昵称
            input('post.qq') ? $post['qq'] = input('post.qq') : false;  //QQ号码
            input('post.head_pic') ? $post['head_pic'] = input('post.head_pic') : false; //头像地址
            input('post.sex') ? $post['sex'] = input('post.sex') : $post['sex'] = 0;  // 性别
            input('post.birthday') ? $post['birthday'] = strtotime(input('post.birthday')) : false;  // 生日
            input('post.province') ? $post['province'] = input('post.province') : false;  //省份
            input('post.city') ? $post['city'] = input('post.city') : false;  // 城市
            input('post.district') ? $post['district'] = input('post.district') : false;  //地区
            input('post.email') ? $post['email'] = input('post.email') : false; //邮箱
            input('post.mobile') ? $post['mobile'] = input('post.mobile') : false; //手机
            $email = input('post.email');
            $mobile = input('post.mobile');
            $scene = I('post.scene');

            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>',$user_id]])->count();
                $res= array('status'=>0,'msg'=>"手机已被使用");
                $c && $this->ajaxReturn($res);
            }
            if(!$userLogic->update_info($user_id, $post)) {
                $res= array('status'=>0,'msg'=>"保存失败");
                $this->ajaxReturn($res);
            }
            $res= array('status'=>1,'msg'=>"操作成功");
            $this->ajaxReturn($res);
            exit;
        }
        $this->ajaxReturn($res);
        exit;

    }

    /*
     * app端登出
     */
    public function logout()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $user_id = $this->user_id;

        M('users')->where(["user_id" => $user_id])->save(['last_login' => 0, 'token' => '']);
        $res= array('status'=>1, 'msg'=>'退出账户成功');
        exit(json_encode($res));
    }



    /*
    * 评论晒单
    */
    public function comment()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $user_id = $this->user_id;
        $status = I('get.status');
        $p = I('pagestart',0);
        $d = I('speed',5);
        $logic = new UsersLogic();
        $result = $logic->getComment($user_id, $status,$p,$d); //获取评论列表
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$result));
    }

    /*
        *添加评论
        */
    public function add_comment()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        if (IS_POST) {

            // 晒图片
            $files = request()->file('comment_img_file');
            $save_url = 'public/upload/comment/' . date('Y', time()) . '/' . date('m-d', time());

            foreach ($files as $file) {
                // 移动到框架应用根目录/public/uploads/ 目录下
                $info = $file->rule('uniqid')->validate(['size' => 1024 * 1024 * 3, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    // 输出 jpg
                    $comment_img[] = '/'.$save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->error($file->getError());
                }
            }
            if (!empty($comment_img)) {
                $add['img'] = serialize($comment_img);
            }

            $user_info = session('user');

            $logic = new UsersLogic();
                $add['goods_id'] = I('goods_id/d');
            $add['email'] = $user_info['email'];
            $hide_username = I('hide_username');

            if (empty($hide_username)) {
                $add['username'] = $user_info['user_name'];
            }


            $add['is_anonymous'] = $hide_username;  //是否匿名评价:0不是\1是
            $add['order_id'] = I('order_id/d');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            $add['is'] = I('goods_rank');
            //$add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['add_time'] = time();
            $add['ip_address'] = request()->ip();
            $add['user_id'] = $this->user_id;
            //添加评论
            $row = $logic->add_comment($add);
            if ($row['status'] == 1) {
                $this->ajaxReturn(array('status'=>1,'msg'=>"评论成功"));
                exit();
            } else {
                $this->ajaxReturn($row['msg']);
            }
        }
        $rec_id = I('rec_id/d');
        $order_goods = M('order_goods')->where("rec_id", $rec_id)->find();
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$order_goods));
    }

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $userLogic = new UsersLogic();
        $p = I('pagestart',0);
        $d = I('speed',5);
        $data = $userLogic->get_goods_collect($this->user_id,$p,$d);
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$data['result']));
    }
    /*
         *取消收藏
         */
    public function cancel_collect()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
            $collect_id = I('collect_id/d');
        $user_id = $this->user_id;
        if (M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            $this->ajaxReturn(array('status'=>1,'msg'=>"取消收藏成功"));
        } else {
            $this->ajaxReturn(array('status'=>-1,'msg'=>"取消收藏失败"));
        }
    }

    /**
     * ajax用户消息通知请求
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_message_notice()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $type = I('type', 0);
        $user_logic = new UsersLogic();
        $message_model = new MessageLogic();
        if ($type == 1) {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();
            $user_logic->setSysMessageForRead();
        } else if ($type == 2) {
            //活动消息：后续开发
            $user_sys_message = array();
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }
        $this->ajaxReturn(array('status'=>1,'msg'=>"获取成功","result"=>$user_sys_message));

    }



    /**
     * 浏览记录
     */
    public function visit_log()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $count = M('goods_visit')->where('user_id', $this->user_id)->count();
        $p = I('pagestart',0);
        $d = I('speed',5);
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $this->user_id)
            ->order('v.visittime desc')
            ->limit($p, $d)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$visit_list));
    }

    /**
     * 删除浏览记录
     */
    public function del_visit_log()
    {

        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $visit_ids = I('get.visit_ids', 0);
        $row = M('goods_visit')->where('visit_id','IN', $visit_ids)->delete();

        if(!$row) {
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作失败"));
        } else {
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"));
        }
    }

    /**
     * 清空浏览记录
     */
    public function clear_visit_log()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $row = M('goods_visit')->where('user_id', $this->user_id)->delete();

        if(!$row) {
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作失败"));
        } else {
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"));
        }
    }


    /*
     * 用户地址列表
     */
    public function address_list()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $address_lists = get_user_address_list($this->user_id);
        $res['lists'] = $address_lists;
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$res));
    }
    /*
     * 设置默认收货地址
     */
        public function set_default()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $id = I('get.address_id/d');
        $user_id = $this->user_id;
        M('user_address')->where(array('user_id' => $user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $id))->save(array('is_default' => 1));
        $this->ajaxReturn(array('status'=>1,'msg'=>"修改成功"));
    }
    /*
     * 地址编辑
     */
    public function edit_address()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $id = I('address_id/d');
        $user_id = $this->user_id;
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $user_id))->find();
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->add_address($user_id, $id, I('post.'));
            $this->ajaxReturn($data);
            exit();
        }
        $res['address'] =$address;
        $this->ajaxReturn($res);

    }
    /*
     * 添加地址
     */
    public function add_address()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        if (IS_POST) {
            $user_id = $this->user_id;
            $post_data = input('post');
            $logic = new UsersLogic();
            $data = $logic->add_address($user_id, 0, I('post.'));
            if ($data['status'] != 1){
                $this->ajaxReturn($data);
                exit();
            }
            $this->ajaxReturn($data);
        }
    }
    /*
     * 地址删除
     */
    public function del_address()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $id = I("address_id/d");
        $user_id = $this->user_id;
        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->ajaxReturn(array('status'=>-1,'msg'=>'删除失败'));
        else
            $this->ajaxReturn(array('status'=>1,'msg'=>'删除成功'));
    }

    /*
     * 订单列表
     */
    public function order_list()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $where = ' user_id=' .$this->user_id;
        $p = I('pagestart',0);
        $d = I('speed',5);

        //条件搜索
        if(I('get.type')){
            $where .= C(strtoupper(I('get.type')));

        }
        $count = M('order')->where($where)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->limit($p,$d)->select();


        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
        }
        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = '';
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];

                $order_list[$key]['goods_list'][$kk]['shop_img'] = goods_thum_images($vv['goods_id'],200,200);

            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;

//
        }

        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$order_list));
    }


    /**
     * 确定收货成功
     */
    public function order_confirm()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $id = I('get.id/d', 0);
        $data = confirm_order($id, $this->user_id);
        if ($data['status'] != 1) {
            $this->ajaxReturn(array('status'=>-1,'msg'=>$data['msg']));
        } else {
            $model = new UsersLogic();
            $order_goods = $model->get_order_goods($id);
            $this->ajaxReturn(array('status'=>1,'msg'=>"确认收货成功","result"=>$order_goods));
        }
    }
    /**
     * 申请退货
     */
    public function return_goods()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $rec_id = I('rec_id',0);
        $act = I('act');

        $return_goods = M('return_goods')->where(array('rec_id'=>$rec_id))->find();
        if(!empty($return_goods))
        {
            $this->ajaxReturn(array('status'=>-1,'msg'=>"已经提交过退货申请"));
        }

        $order_goods = M('order_goods')->where(array('rec_id'=>$rec_id))->find();

        $order = M('order')->where(array('order_id'=>$order_goods['order_id'],'user_id'=>$this->user_id))->find();



        if(empty($order)) $this->ajaxReturn(array('status'=>-1,'msg'=>"非法操作"));


        if($act == "submit_form")
        {
            $model = new OrderLogic();
            $res = $model->addReturnGoods($rec_id,$order);  //申请售后
            if($res['status']==1)$this->ajaxReturn(array('status'=>1,'msg'=>$res['msg'],"result"=>$res));
            $this->ajaxReturn(array('status'=>-1,'msg'=>$res['msg']));
        }
        $region_id[] = tpCache('shop_info.province');
        $region_id[] = tpCache('shop_info.city');
        $region_id[] = tpCache('shop_info.district');
        $region_id[] = 0;
        $return_address = M('region')->where("id in (".implode(',', $region_id).")")->getField('id,name');
        $order_info = array_merge($order,$order_goods);  //合并数组


        $tpshop_config = array();
        $tp_config = M('config')->cache(true,TPSHOP_CACHE_TIME)->select();
        foreach($tp_config as $k => $v)
        {
            if($v['name'] == 'hot_keywords'){
                $tpshop_config['hot_keywords'] = explode('|', $v['value']);
            }
            $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
        }


        $res['return_address'] = $return_address;
        $res['goods'] = $order_goods;
        $res['order'] = $order;
        $res['config'] = array(
            "shop_info_address"=>$tpshop_config['shop_info_address'],
            "shop_info_phone"=>$tpshop_config['shop_info_phone'],
            "time"=>"（周一至周五）08:00-19:00"
        );
        $this->ajaxReturn(array('status'=>1,'msg'=>"请求成功","result"=>$res));
    }


    /**
     * 退换货列表
     */
    public function return_goods_list()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $p = I('pagestart/d',0);
        $d = I('speed/d',5);
        //退换货商品信息
        $count = M('return_goods')->where("user_id", $this->user_id)->count();
        $list = M('return_goods')->where("user_id", $this->user_id)->order("id desc")->limit($p,$d)->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');  //获取商品ID
        if (!empty($goods_id_arr)){
            $goodsList = M('goods')->where("goods_id", "in", implode(',', $goods_id_arr))->getField('goods_id,goods_name');
        }
        $state = C('REFUND_STATUS');
        $res['goodsList'] = $goodsList;
        $res['list'] = $list;
        $res['state'] = $state;
        $this->ajaxReturn(array('status'=>1,'msg'=>"请求成功","result"=>$res));
    }
    /**
     *  退货详情
     */
    public function return_goods_info()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $id = I('id/d', 0);
        $return_goods = M('return_goods')->where("id = $id")->find();
        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        if ($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();
        $state = C('REFUND_STATUS');

        $res['return_goods'] = $return_goods;
        $res['goods'] = $goods;
        $res['state'] = $state;
        $this->ajaxReturn(array('status'=>1,'msg'=>"请求成功","result"=>$res));

    }
    /**
     * 取消售后服务
     * @author lxl
     * @time 2017-4-19
     */
    public function return_goods_cancel(){
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $id = I('id',0);
        if(empty($id))$this->error('参数错误');
        $return_goods = M('return_goods')->where(array('id'=>$id,'user_id'=>$this->user_id))->find();
        if(empty($return_goods)) $this->error('参数错误');
        M('return_goods')->where(array('id'=>$id))->save(array('status'=>-2,'canceltime'=>time()));
        $this->ajaxReturn(array('status'=>1,'msg'=>"取消成功"));
    }

    /*
     * 取消订单
     */
    public function cancel_order()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $id = $this->user_id;
        $order_id = I('get.order_id/d');
//        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>I('get.')));
        //检查是否有积分，余额支付
        $logic = new UsersLogic();
        $data = $logic->cancel_order($id, $order_id);
        $this->ajaxReturn($data);

    }
    /*
     * 订单详情
     */
    public function order_detail()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $id = $this->user_id;
        $order_id = I('get.order_id/d');

        $map['order_id'] = $order_id;
        $map['user_id'] = $id;
//        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>I('get.')));
        $order_info = M('order')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        $invoice_no = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no', true);
        $order_info[invoice_no] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id' => $id))->select();
        //统计订单商品数量

        foreach ($order_info['goods_list'] as $kk => $vv) {
            $order_info['goods_list'][$kk]['shop_img'] = goods_thum_images($vv['goods_id'],100,100);
        }
        $res['order_info'] = $order_info;
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$res));
    }
    /*
     * 账户资金列表
    */
    public function account_list()
    {

        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $type = I('type','all');
        $p = I('pagestart',0);
        $d = I('speed',5);
        $usersLogic = new UsersLogic;
        $result = $usersLogic->account($this->user_id, $type,$p,$d);
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$result['account_log']));
    }

    public function account_detail(){
        $log_id = I('log_id/d',0);
        $detail = Db::name('account_log')->where(['log_id'=>$log_id])->find();
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$detail));
    }

    /**账户积分明细*/
    public function points_list()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $type = I('type','all');
        $p = I('pagestart',0);
        $d = I('speed',5);
        $usersLogic = new UsersLogic;
        $result = $usersLogic->points($this->user_id, $type,$p,$d);
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$result['account_log']));

    }


    /**
     * 优惠券
     */
    public function coupon()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
        $logic = new UsersLogic();
        $p = I('pagestart',0);
        $d = I('speed',5);
//        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>1));
        $data = $logic->get_coupon($this->user_id, input('type'),$p,$d);
        $coupon_list = $data['result'];
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$coupon_list));

    }
    /**
     * 确定订单的使用优惠券
     * @author lxl
     * @time 2017
     */
    public function checkcoupon()
    {
        if(empty($this->user_id)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未登录"));
        }
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
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功","result"=>$coupon_list));

    }

}
