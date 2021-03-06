<?php
namespace app\index\controller;
use think\Controller;
use think\Request;
use think\Db;

class Commodity extends Controller
{

    /**
     * 商品分类 
     * GY
     */
    public function commodity_index(Request $request)
    {
        if($request->isPost()) {            
            $goods_type = db("wares")->where("status", 1)->select();
            $goods_type = _tree_sort(recursionArr($goods_type), 'sort_number');
            foreach($goods_type as $key => $value)
            {
                $goods_type[$key]['child'] = db("goods")->where("pid",$goods_type[$key]['id'])->where("label",1)->select();
 
            }
            return ajax_success("获取成功",array("goods_type"=>$goods_type));
        }
        
    }





    /**
     * 商品首页推荐
     * GY
     */
    public function commodity_recommend(Request $request)
    {

        if ($request->isPost()) {
            $member_id = $request->only(["open_id"])["open_id"];
            $member_grade_name = $request->only(["member_grade_name"])["member_grade_name"]; //会员等级
            $member_grade_id = db("member")->where("member_openid", $member_id)->value("member_grade_id");
            $discount = db("member_grade")->where("member_grade_id", $member_grade_id)->value("member_consumption_discount");
            $goods = db("goods")->where("status",1)->where("label",1)->select();

            foreach ($goods as $k => $v) //所有商品
            {
                if(!empty($goods[$k]["scope"])){
                    $goods[$k]["scope"] = explode(",",$goods[$k]["scope"]);
                }
                if($goods[$k]["goods_standard"] == 1){
                    $standard[$k] = db("special")->where("goods_id", $goods[$k]['id'])->select();
                    $max[$k] = db("special")->where("goods_id", $goods[$k]['id'])-> max("price") * $discount;//最高价格
                    $min[$k] = db("special")->where("goods_id", $goods[$k]['id'])-> min("price") * $discount;//最低价格
                    $line[$k] = db("special")->where("goods_id", $goods[$k]['id'])-> min("line");//最低价格
                    $goods[$k]["goods_standard"] = $standard[$k];
                    $goods[$k]["goods_show_images"] = explode(",",$goods[$k]["goods_show_images"]);
                    $goods[$k]["max_price"] = $max[$k];
                    $goods[$k]["min_price"] = $min[$k];
                    $goods[$k]["line"] = $line[$k];

                if(!empty($goods[$k]["scope"])){
                    if(!in_array($member_grade_name,$goods[$k]["scope"])){ 
                        unset($goods[$k]);
                    }
                }              
                } else {
                    $goods[$k]["goods_new_money"] = $goods[$k]["goods_new_money"] * $discount;
                    $goods[$k]["goods_show_images"] = explode(",",$goods[$k]["goods_show_images"]);
                    if(!empty($goods[$k]["scope"])){
                        if(!in_array($member_grade_name,$goods[$k]["scope"])){ 
                            unset($goods[$k]);
                        }
                    }
                }
                
            }
            $goods_new = array_values($goods);
            if (!empty($goods_new) && !empty($member_id)) {
                return ajax_success("获取成功", $goods_new);
            } else {
                return ajax_error("获取失败");
            }
        }

    }




    /**
     * 商品列表
     * GY
     */
    public function commodity_list(Request $request)
    {

        if($request->isPost()){
            $member_grade_name = $request->only(["member_grade_name"])["member_grade_name"]; //会员等级
            $goods_pid = $request->only(["id"])["id"];
            $goods = db("goods")->where("pid",$goods_pid)->where("label",1)->where("status",1)->select();
            foreach ($goods as $k => $v)
            {
                $goods[$k]["goods_show_images"] = (explode(",", $goods[$k]["goods_show_images"])[0]);
                if(!empty($goods[$k]["scope"])){
                    $goods[$k]["scope"] = explode(",",$goods[$k]["scope"]);
                    if(!in_array($member_grade_name,$goods[$k]["scope"])){ 
                        unset($goods[$k]);
                    }
                }
            }
            $new_goods = array_values($goods);
            if(!empty($new_goods) && !empty($goods_pid)){
                return ajax_success("获取成功",$new_goods);
            }else{
                return ajax_error("获取失败");
            }
        }

    }



    /**
     * 商品详情
     * GY
     */
    public function commodity_detail(Request $request)
    {
        if ($request->isPost()) {
            $goods_id = $request->only(["id"])["id"];
            $member_id = $request->only(["open_id"])["open_id"];
            $member_grade_id = db("member")->where("member_openid", $member_id)->value("member_grade_id");
            $discount = db("member_grade")->where("member_grade_id", $member_grade_id)->value("member_consumption_discount");
            $goods = db("goods")->where("id", $goods_id)->where("label",1)->select();
            $goods_standard = db("special")->where("goods_id", $goods_id)->select();
            $max_price = db("special")->where("goods_id", $goods_id)->max("price");
            $min_price = db("special")->where("goods_id", $goods_id)->min("price");
            $min_line = db("special")->where("goods_id", $goods_id)->min("line");
            $max_prices = $max_price * $discount;
            $min_prices = $min_price * $discount;

            foreach ($goods_standard as $key => $value) {
                $goods_standard[$key]["price"] = $goods_standard[$key]["price"] * $discount;
            }
            if ($goods[0]["goods_standard"] == 1) {
                $goods[0]["goods_standard"] = $goods_standard;
                $goods[0]["goods_show_images"] = (explode(",", $goods[0]["goods_show_images"]));
                $goods[0]["max_price"] = $max_prices;
                $goods[0]["min_price"] = $min_prices;
                $goods[0]["min_line"] = $min_line;
            } else {
                $goods[0]["goods_new_money"] = $goods[0]["goods_new_money"] * $discount;
                $goods[0]["goods_show_images"] = (explode(",", $goods[0]["goods_show_images"]));
                $goods[0]["min_line"] = $goods[0]["goods_bottom_money"] ;

            }
            if (!empty($goods) && !empty($goods_id)){
                return ajax_success("获取成功", $goods);
            } else {
                return ajax_error("获取失败");
            }
        }

    }


    /**
     **************郭杨*******************
     * @param Request $request
     * Notes:上门自提默认收获地址
     **************************************
     */
    public function approve_address(Request $request){
        if($request->isPost()){
            $data =Db::name("extract_address")->where("label",1)
                ->find();            
            if(!empty($data)){
                return ajax_success("返回成功",$data);
            }else{
                return ajax_error("没有默认自提地址");
            }
        }
   }



    /**
     **************郭杨*******************
     * @param Request $request
     * Notes:默认自提地址列表
     **************************************
     */
    public function approve_list(Request $request){
        if($request->isPost()){
            $data =Db::name("extract_address")->select();            
            if($data){
                return ajax_success("返回成功",$data);
            }else{
                return ajax_error("没有默认自提地址");
            }
        }
    }





    /**
     **************郭杨*******************
     * @param Request $request
     * Notes:选择自提地址详情
     **************************************
     */
    public function approve_detailed(Request $request){
        if($request->isPost()){
            $id = $request->only(["id"])["id"];
            $data =Db::name("extract_address")->where("id",$id)
                ->find();            
            if(!empty($data)){
                return ajax_success("返回成功",$data);
            }else{
                return ajax_error("参数有误");
            }
        }
    }
}
