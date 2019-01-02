<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/3 0003
 * Time: 18:21
 */

namespace  app\admin\controller;

use think\console\Input;
use think\Controller;
use think\Db;
use think\Request;

class  Bonus extends  Controller{

    /**
     * [积分商城显示]
     * GY
     */
    public function bonus_index() 
    {
             
        return view('bonus_index');
    }


    /**
     * [积分商城编辑]
     * GY
     */
    public function bonus_edit()
    {
        return view('bonus_edit');
    }


    /**
     * [积分商城添加商品]
     * GY
     */
    public function bonus_add()
    {
        return view('bonus_add');
    }


    /**
     * [优惠券显示]
     * GY
     */
    public function coupon_index()
    {
        $coupon = db("coupon")->paginate(20);
        
        return view('coupon_index',["coupon" => $coupon]);
    }


    /**
     * [优惠券添加]
     * GY
     */
    public function coupon_add(){
        return view('coupon_add');
    }



    /**
     * [优惠券保存入库]
     * GY
     */
    public function coupon_save(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->param();           
            // $data["start_time"] = strtotime($data["start_time"]);
            // $data["end_time"] = strtotime($data["end_time"]);
            foreach($data["goods_id"] as $key => $value)
            {
                $goods[$key] = db("goods")->where("id",$data["goods_id"][$key])->field("id,goods_number,goods_show_images,goods_name,goods_standard,goods_repertory")->find();

            }
            unset($data["goods_id"]);

            $coupon_id = db("coupon")->insertGetId($data);

            foreach($goods as $key => $value){
                $goods[$key]["goods_id"] =  $goods[$key]["id"];
                $goods[$key]["coupon_id"] =  $coupon_id;

                if($goods[$key]["goods_standard"] == 1)
                {
                    $goods[$key]["goods_repertory"] = db("special")->where("goods_id",$goods[$key]["id"])->sum("stock");
                    $goods[$key]["goods_show_images"] = explode(",",$goods[$key]["goods_show_images"])[0];
                } else {
                    $goods[$key]["goods_show_images"] = explode(",",$goods[$key]["goods_show_images"])[0];
                }
                unset($goods[$key]["id"]);
            }

            foreach ($goods as $k => $v) {
                $rest = db("join")->insert($v);
            }
            if ($coupon_id && $rest) {
                $this->success("添加成功", url("admin/Bonus/coupon_index"));
            } else {
                $this->error("添加失败", url("admin/Bonus/coupon_add"));
            }
        }
    }


    /**
     * [优惠券编辑]
     * GY
     */
    public function coupon_edit($id)
    {
        $coupons = db("coupon")->where("id", $id)->select();
        return view('coupon_edit',["coupons"=>$coupons]);
        
        
    }


    /**
     * [优惠券编辑]
     * GY
     */
    public function coupon_weave(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->only(["id"])["id"];
            $join = db("join")->where("coupon_id",$id)->select();  
            if (!empty($join) && !empty($id)) 
            {
                return ajax_success("获取成功", $join);
            } else {
                return ajax_error("获取失败优惠券失败");
            }
            
        }
              
    }



    /**
     * [优惠券编辑]
     * GY
     */
    public function coupon_update(){
        return view('coupon_edit');
    }



    /**
     * [优惠券删除]search
     * GY
     */
    public function coupon_del(){
        return view('coupon_edit');
    }



    /**
     * [优惠券搜索商品]
     * GY
     */
    public function coupon_search(Request $request)
    {
        $goods_number = input("goods_number");
        $goods = db("goods")->where("goods_number",$goods_number)->field("id,goods_number,goods_show_images,goods_name,goods_standard,goods_repertory")->select();

        foreach($goods as $key => $value){        
            if($goods[$key]["goods_standard"] == 1)
            {
                $goods[$key]["goods_repertory"] = db("special")->where("goods_id",$goods[$key]["id"])->sum("stock");
                $goods[$key]["goods_show_images"] = explode(",",$goods[$key]["goods_show_images"])[0];
            } else {
                $goods[$key]["goods_show_images"] = explode(",",$goods[$key]["goods_show_images"])[0];
            }
        }

        if (!empty($goods) && !empty($goods_number)) {
            return ajax_success("获取成功", $goods);
        } else {
            return ajax_error("获取失败商品信息");
        }
    }





}