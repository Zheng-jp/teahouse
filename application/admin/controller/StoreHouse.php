<?php
/**
 * Created by PhpStorm.
 * User: GY
 * Date: 2019/2/20
 */
namespace  app\admin\controller;

use think\Controller;
use think\Db;
use think\Request;


class StoreHouse extends Controller{
    
    /**
     * [仓库管理]
     * 郭杨
     */    
    public function store_house(){
        $store_data = db("store_house")->select();
        if(!empty($store_data)){
            foreach($store_data as $key => $value){
                $store_data[$key]["max"] = max(explode(',',$store_data[$key]['cost']));
                $store_data[$key]["min"] = min(explode(',',$store_data[$key]['cost']));
            }
        }
        $url = 'admin/StoreHouse/store_house';
        $pag_number = 20;
        $store = paging_data($store_data,$url,$pag_number);
        return view("store_house",["store"=>$store]);
    } 

    
    /**
     * [仓库管理添加]
     * 郭杨
     */    
    public function store_house_add(Request $request){
        if($request->isPost()){
            $data = $request->param();
            $data["type"] = isset($data["type"])?$data["type"]:0;
            $data["unit"] = implode(",",$data["unit"]);
            $data["cost"] = implode(",",$data["cost"]);

            if($data["label"] = 1){
                $where = "update tb_store_house set label = 0";
                $rest = Db::query($where);
            }

            $res =Db::name("store_house")->insert($data);           
            if($res){
                $this -> success("添加成功","admin/StoreHouse/store_house");
            } else {
                $this -> success("添加失败","admin/StoreHouse/store_house");
            }
        }
        return view("store_house_add");
    }

    /**
     * [仓库管理编辑]
     * 郭杨
     */    
    public function store_house_update(Request $request){
        if($request->isPost()){
            $data = $request -> param();
            $data["type"] = isset($data["type"])?$data["type"]:0;
            $data["unit"] = implode(",",$data["unit"]);
            $data["cost"] = implode(",",$data["cost"]);
            if($data["label"] = 1){
                $where = "update tb_store_house set label = 0";
                $rest = Db::query($where);
            }
            $bool = db("store_house")->where('id', $request->only(["id"])["id"])->update($data);

            if($bool){
                $this->success("更新成功",url("admin/StoreHouse/store_house"));
            } else {
                $this->error("更新失败", url("admin/StoreHouse/store_house"));
            }
                  
        }
    }


    /**
     * [仓库管理编辑]
     * 郭杨
     */    
    public function store_house_edit($id){
        $house = db("store_house")->where("id",$id)->select();
        return view("store_house_edit",["house"=>$house]);
    }

    /**
     * [仓库管理删除]
     * 郭杨
     */
    public function store_house_delete($id){
        $bool = db("store_house")->where("id", $id)->delete();
        if ($bool) {
            $this->success("删除成功", url("admin/StoreHouse/store_house"));
        } else {
            $this->error("删除失败", url("admin/StoreHouse/store_house"));
        }

    }

    /**
     * [仓库所有单位]
     * 郭杨
     */
    public function store_house_unit(){
        $unit = db("special")->distinct(true)->field("unit")->select();
        if(!empty($unit)){
            $list = unit_list($unit);
            return ajax_success('传输成功', $list);
        } else {
            return ajax_error('数据为空');
        }      
    }


    /**
     * [仓库编辑价格单位]
     * 郭杨
     */
    public function store_house_cost(Request $request){
        if($request->isPost()){
            $id = $request->only(["id"])["id"];
            $cost = db("store_house") -> where('id',$id) ->field("cost,unit,id")->find();
            $cost['cost'] = explode(",",$cost['cost']);
            $cost['unit'] = explode(",",$cost['unit']);
            if(!empty($cost)){
                return ajax_success('传输成功', $cost);
            } else {
                return ajax_error('数据为空');
            } 
        }     
    }


    /**
     * [仓库默认入仓编辑]
     * 郭杨
     */
    public function store_house_status(Request $request){
        $status = $request->only(["status"])["status"];
        if ($status == 0) {
            $id = $request->only(["id"])["id"];
            $bool = db("store_house")->where("id", $id)->update(["label" => 0]);
            if ($bool) {
                $this->redirect(url("admin/StoreHouse/store_house"));
            } else {
                $this->error("修改失败", url("admin/StoreHouse/store_house"));
            }
        }
        if ($status == 1) {
            $where = "update tb_store_house set label = 0";
            $rest = Db::query($where);
            $id = $request->only(["id"])["id"];
            $bool = db("store_house")->where("id", $id)->update(["label" => 1]);
            if ($bool) {
                $this->redirect(url("admin/StoreHouse/store_house"));
            } else {
                $this->error("修改失败", url("admin/StoreHouse/store_house"));
            }
        }
         
    }


    /**
     * [仓库入仓]
     * 郭杨
     */    
    public function stores_divergence()
    { 
        $store_order = db("house_order")->where("status",">",0)->field("id,parts_order_number,parts_goods_name,user_account_name,store_name,store_number,order_create_time,end_time")->select();

        foreach($store_order as $key => $value){
            $store_order[$key]["store_number"] = str_replace(',', '', $store_order[$key]["store_number"]);
        }    

        $url = 'admin/StoreHouse/stores_divergence';
        $pag_number = 20;
        $stores_divergence = paging_data($store_order,$url,$pag_number);
        return view("stores_divergence",["stores_divergence"=>$stores_divergence]);
    }

    /**
     * [仓库出仓]
     * 郭杨
     */
    public function stores_divergence_out(){
        return view("stores_divergence_out");
    }


    
 }