<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 吃鸡网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: xwy
 * Date: 2018-05-08
 */

namespace app\api\model;
use think\Model;

/**
 * Class
 * @package Home\Model
 */
class NewsLogic extends Model
{



    /**
     * 获取新闻列表
     * @param $data
     * @return array
     */
    public static function news_list($type,$cat_id,$p,$d)
    {

        if($type){
            $where['tags'] = $type;
        }
        if($cat_id){
            $where['cat_id'] = $cat_id;
        }
        $where['is_open'] = 1;
        $list = M('news')
            ->where($where)
            ->limit($p,$d)
            ->order('publish_time desc')
            ->select();

        foreach ($list as $k => $v) {
            $list[$k]['time'] = date('Y-m-d', $v['publish_time']);
        }
        return array('status' => 1, 'msg' => '请求成功成功', 'result' => $list);
    }


    /**
     * 获取新闻详情
     * @param $data
     * @return array
     */
    public static function news_detail($newId)
    {
        $list = M('news')
            ->alias('n')
            ->join('__NEWS_CAT__ cat', 'cat.cat_id = n.cat_id', 'LEFT')
            ->field('article_id,title,click,thumb,description,tags,cat_name,publish_time,content')
            ->where(['open_type' => 0, 'is_open' => 1, 'article_id' =>$newId])
            ->find();
//      $list['addtime'] = date('Y-m-d',$list['addtime']);
        if ($list) {
            $list['content'] = htmlspecialchars_decode($list['content']);
            $list['time'] = date('Y-m-d', $list['publish_time']);
        }
        if ($list) {
            return array('status' => 1, 'msg' => '操作成功', 'result' => $list);
        }
        return array('status' => 1, 'msg' => '操作成功', 'result' => array());

    }


}