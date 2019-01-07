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
use think\Image;
use think\paginator\driver\Bootstrap;

class  Distribution extends  Controller{

    /**
     * [分销设置显示]
     * GY
     */
    public function setting_index()
    {
        $distribution = db("distribution") -> select();
        
        return view("setting_index",["distribution" =>$distribution ]);
    }



    /**
     * [分销设置编辑]
     * GY
     */
    public function setting_edit($id)
    {       
        $setting = db("distribution") -> where("id",$id) -> select();    
        return view("setting_edit",["setting" => $setting]);
    }




    /**
     * [分销设置编辑入库]
     * GY
     */
    public function setting_updata(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->param();
            $bool = db("distribution")->where('id', $request->only(["id"])["id"])->update($data);
            if ($bool) {
                $this->success("编辑成功", url("admin/Distribution/setting_index"));
            } else {
                $this->error("编辑失败", url("admin/Distribution/setting_index"));
            }
        }
    }




    /**
     * [分销商品页面]
     * GY
     */
    public function goods_index()
    {

        $commodity = db("commodity") -> select();
        foreach ($commodity as $key => $value)
        {
            $commodity[$key]["grade"] = explode(",",$commodity[$key]["grade"]);
        }


        $all_idents = $commodity;//这里是需要分页的数据
        $curPage = input('get.page') ? input('get.page') : 1;//接收前段分页传值
        $listRow = 20;//每页20行记录
        $showdata = array_slice($all_idents, ($curPage - 1) * $listRow, $listRow, true);// 数组中根据条件取出一段值，并返回
        $commodity = Bootstrap::make($showdata, $listRow, $curPage, count($all_idents), false, [
            'var_page' => 'page',
            'path' => url('admin/Category/index'),//这里根据需要修改url
            'query' => [],
            'fragment' => '',
        ]);
        $commodity->appends($_GET);
        $this->assign('commodity', $commodity->render());

        return view('goods_index',["commodity"=> $commodity]);

    }



    /**
     * [分销商品添加商品]
     * GY
     */
    public function goods_add()
    {
        return view('goods_add');
    }




    /**
     * [分销商品编辑]
     * GY
     */
    public function goods_edit($id)
    {

        $goods = db("commodity") -> where("id",$id) ->select();
        $goods[0]["grade"] = explode(",",$goods[0]["grade"]);
        $goods[0]["award"] = explode(",",$goods[0]["award"]);
        $goods[0]["scale"] = explode(",",$goods[0]["scale"]);
        $goods[0]["integral"] = explode(",",$goods[0]["integral"]);
    
        return view('goods_edit',["goods"=> $goods]);
    }


    
    /**
     * [分销商品编辑更新]
     * GY
     */
    public function goods_update(Request $request)
    {

        if ($request->isPost()) {
            $goods_data = $request->param();
            $goods_data["rank"] = implode(",",$goods_data["rank"]);
            $goods_data["grade"] = implode(",",$goods_data["grade"]);
            $goods_data["award"] = implode(",",$goods_data["award"]);
            $goods_data["scale"] = implode(",",$goods_data["scale"]);
            $goods_data["integral"] = implode(",",$goods_data["integral"]);

            $bool = db("commodity")->where('id', $request->only(["id"])["id"])->update($goods_data);
            if ($bool) {
                $this->success("编辑成功", url("admin/Distribution/goods_index"));
            } else {
                $this->error("编辑失败", url("admin/Distribution/goods_edit"));
            }
        }
    }



    /**
     * [分销商品添加入库]
     * GY
     */
    public function goods_save(Request $request)
    {
        if ($request->isPost())
         {
            $data = $request->param();
            $des = db("goods") -> where("goods_number",$data["shop_number"])->field("id,goods_name,goods_show_images,goods_new_money")-> find();
            $bool = db("goods")->where("goods_number",$data["shop_number"])->update(["distribution" => 1]);
            $reste = db("commodity")->where("goods_id",$des["id"])->find();
            $deny = explode(",",$des["goods_show_images"]);
            $data["picture"] = $deny[0];
            $data["shop_name"] = $des["goods_name"];
            $data["goods_id"] = $des["id"];
            
            $data["rank"] = implode(",",$data["rank"]);
            $data["grade"] = implode(",",$data["grade"]);
            $data["award"] = implode(",",$data["award"]);
            $data["scale"] = implode(",",$data["scale"]);
            $data["integral"] = implode(",",$data["integral"]);

            if(empty($data["shop_name"]))
            {
                $this->error("商品列表中没有该商品,请仔细核对后再添加", url("admin/Distribution/goods_add"));
            }
            if(!empty($reste))
            {
                $this->error("该商品已经加入分销设置,请仔细核对后再添加", url("admin/Distribution/goods_add"));
            }
         
            $bool = db("commodity")->insert($data);
            if ($bool) {
                $this->success("添加成功", url("admin/Distribution/goods_index"));
            } else {
                $this->error("添加失败", url("admin/Distribution/goods_add"));
            }
        }
    }


    /**
     * [分销商品组删除]
     * GY
     */
    public function goods_delete($id)
    {
        $bool = db("commodity")->where("id", $id)->delete();
        $goods_id = db("commodity")->where("id", $id)->value("goods_id");
        $boole = db("goods")->where("id",$goods_id)->update(["distribution" => 0]);
        if ($bool && $boole) {
            $this->success("删除成功", url("admin/Distribution/goods_index"));
        } else {
            $this->error("删除失败", url("admin/Distribution/goods_index"));
        }

    }

    /**
     * [分销记录页面]
     * GY
     */
    public function record_index(){
        //$record = db("order")->where("distribution",1)->select();//方便测试，后期再加上订单条件
        //halt($record );
        return view('record_index');
    }

    /**
     * [分销成员页面]
     * GY
     */
    public function member_index(){
        return view('member_index');
    }



    /**
     * [分销成员编辑页面]
     * GY
     */
    public function member_edit(){
        return view('member_edit');
    }





}