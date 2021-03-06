<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/27 0027
 * 订单
 * Time: 15:20
 */

namespace  app\index\controller;

use think\Controller;
use think\Request;
use think\Db;

class  Order extends  Controller
{
    /**
     **************李火生*******************
     * @param Request $request
     * Notes:立即购买过去购物清单数据返回
     **************************************
     */
    public function order_return(Request $request)
    {
        if ($request->isPost()) {
            $open_id =$request->only("open_id")["open_id"];
            $member_grade_id =Db::name("member")->where("member_openid",$open_id)->find();
            $member_consumption_discount =Db::name("member_grade")
                ->where("member_grade_id",$member_grade_id["member_grade_id"])
                ->find();
            $goods_id = $request->only("goods_id")["goods_id"];
            $special_id = $request->only("guige")["guige"];
            $number = $request->only("num")["num"];
            if (empty($goods_id)) {
                return ajax_error("商品信息有误，请返回重新提交", ["status" => 0]);
            }
            foreach ($goods_id as  $key=>$value){
                $goods_data = null;
                $goods_data = Db::name("goods")->where("id", $value)->find();
                //判断是为专用还是通用
                //专用规格
                if ($goods_data["goods_standard"] == 0) {
                    $data[$key]["goods_info"] = $goods_data;
                    $data[$key]["grade_price"] =$member_consumption_discount["member_consumption_discount"] * $goods_data["goods_new_money"];
                    $data[$key]["special_info"] = null;
                    $data[$key]["number"] =$number[$key];
                    $data[$key]["user_grade_image"] =$member_consumption_discount["member_grade_img"];
                    $data[$key]["unit"]=$goods_data['monomer'];
                    } else{
                    $data[$key]["goods_info"] = $goods_data;
                    if($special_id[$key] != 0){
                        $info = Db::name("special")
                            ->where("id", $special_id[$key])
                            ->find();
                        $data[$key]["special_info"] =$info;
                        $data[$key]["grade_price"] =$member_consumption_discount["member_consumption_discount"]* $info["price"];
                        $data[$key]["unit"]=$info['offer'];
                    }else{
                        $data[$key]["goods_info"] = $goods_data;
                        $data[$key]["grade_price"] =$member_consumption_discount["member_consumption_discount"] * $goods_data["goods_new_money"];
                        $data[$key]["unit"]=$goods_data['monomer'];
                    }
                    $data[$key]["number"] =$number[$key];
                    $data[$key]["user_grade_image"] =$member_consumption_discount["member_grade_img"];
                }
            }
            if(!empty($data)){
                return ajax_success("数据返回",$data);
            }else{
                return ajax_error("没有数据",["status"=>0]);
            }

        }
    }


    /**
     **************李火生*******************
     * @param Request $request
     * Notes:提交订单
     **************************************
     * @param Request $request
     */
    public function order_place(Request $request){
        if ($request->isPost()){
            $open_id = $request->only("open_id")["open_id"];//open_id
            $address_id = $request->param("address_id");//address_id
            $coupon_id =$request->only("coupon_id")["coupon_id"]; //添加使用优惠券id
            $order_type =$request->only("order_type")["order_type"];//1为选择直邮，2到店自提，3选择存茶
            $user_id =Db::name("member")
                ->where("member_openid",$open_id)
                ->value("member_id");
            if(empty($user_id)){
                return ajax_error("未登录",['status'=>0]);
            }

            $member_grade_id =Db::name("member")->where("member_id",$user_id)->find();
            $member_consumption_discount =Db::name("member_grade")
                ->where("member_grade_id",$member_grade_id["member_grade_id"])
                ->find();
            $user_information =Db::name("member")->where("member_id",$user_id)->find();
            $is_address = Db::name('user_address')
                ->where("id",$address_id)
                ->where('user_id', $user_id)
                ->find();
            if (empty($is_address) ) {
                return ajax_error('请填写收货地址',['status'=>0]);
            }else{
                $is_address_status = Db::name('user_address')
                    ->where('user_id', $user_id)
                    ->where('id',$address_id)
                    ->find();
                if (empty($is_address_status) ) {
                    $is_address_status =$is_address;
                }
                $commodity_id = $request->only("goods_id")["goods_id"];//商品id
                $all_money =$request->only("order_amount")["order_amount"];//总价钱
                $goods_standard_id =$request->only("goods_standard_id")["goods_standard_id"];//规格id
                $numbers =$request->only("order_quantity")["order_quantity"];

                $harvest_address_city =str_replace(',','',$is_address_status['address_name']);
                $harvest_address =$harvest_address_city.$is_address_status['harvester_real_address']; //收货人地址
                $time=date("Y-m-d",time());
                $v=explode('-',$time);
                $time_second=date("H:i:s",time());
                $vs= explode(':',$time_second);
                //1为选择直邮，2到店自提，3选择存茶
                if($order_type ==1){
                    $parts_order_number ="ZY".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                }else if($order_type ==2){
                    $parts_order_number ="ZT".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                }else if($order_type ==3){
                    $parts_order_number ="CC".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                }
                foreach ($commodity_id as $keys=>$values){
                    if (!empty($commodity_id)){
                        $goods_data = Db::name('goods')->where('id',$values)->find();
                        $create_time = time();//下单时间
                        $normal_time =Db::name("order_setting")->find();//订单设置的时间
                        $normal_future_time = strtotime("+". $normal_time['normal_time']." minute");

                        if (!empty($goods_data)){
//                        if(!empty($data["buy_message"])){
//                            $buy_message =$data["buy_message"];
//                        }else{
                            $buy_message = NUll;
//                        }
                            //判断是通用
                            if($goods_data["goods_standard"]==0){
                                $datas['goods_image'] = $goods_data['goods_show_image'];//图片
                                $datas["goods_money"]=$goods_data['goods_new_money']* $member_consumption_discount["member_consumption_discount"];//商品价钱
                            }else{
                                //图片
                                $special_data =Db::name("special")
                                    ->where("id",$goods_standard_id[$keys])
                                    ->find();
                                $datas['goods_image'] = $special_data['images'];//图片
                                $datas["goods_money"]= $special_data['price'] * $member_consumption_discount["member_consumption_discount"];//商品价钱
                                $datas['goods_standard'] = $special_data["name"]; //商品规格
                            }
                            $datas["order_type"] =$order_type;//1为选择直邮，2到店自提，3选择存茶
                            $datas["distribution"] =$goods_data["distribution"];//是否分销
                            $datas["goods_describe"] =$goods_data["goods_describe"];//卖点
                            $datas["parts_goods_name"] =$goods_data["goods_name"];//名字
                            $datas["order_quantity"] =$numbers[$keys];//订单数量
                            $datas["member_id"] =$user_id;//用户id
                            $datas["user_account_name"] = $user_information["member_name"];//用户名
                            $datas["user_phone_number"] = $user_information["member_phone_num"];//用户名手机号
                            $datas["harvester"] = $is_address_status['harvester'];
                            $datas["harvest_phone_num"] = $is_address_status['harvester_phone_num'];
                            $datas["harvester_address"] = $harvest_address;
                            $datas["order_create_time"] = $create_time;
                            $datas["order_amount"] = $datas["goods_money"]*$numbers[$keys];//订单金额
                            $datas["order_real_pay"] = $all_money;//订单实际支付的金额(即优惠券抵扣之后的价钱）
                            $datas["status"] = 1;
                            $datas["goods_id"] = $values;
                            $datas["parts_order_number"] = $parts_order_number;//时间+4位随机数+用户id构成订单号
                            $datas["buy_message"] = $buy_message;//买家留言
                            $datas["normal_future_time"] = $normal_future_time;//未来时间
                            $datas["special_id"] = $goods_standard_id[$keys];//规格id
                            $datas["coupon_id"] = $coupon_id;
                            $datas["refund_amount"] =$all_money;

                            
                            if(!empty($coupon_id)){ //优惠券金额
                                $datas["coupon_deductible"] = db("coupon")->where("id",$coupon_id)->value("money");
                            }
                            $res = Db::name('order')->insertGetId($datas);
                            if ($res) {
                                $order_datas =Db::name("order")
                                    ->field("order_real_pay,parts_goods_name,parts_order_number")
                                    ->where('id',$res)
                                    ->where("member_id",$user_id)
                                    ->find();
                                return ajax_success('下单成功',$order_datas);
                            }else{
                                return ajax_error('失败',['status'=>0]);
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     **************郭杨*******************
     * @param Request $request
     * Notes:提交订单(修改过的)
     **************************************
     * @param Request $request
     */
    public function order_places(Request $request){
        if ($request->isPost()){
            $user_id = $request->only("member_id")["member_id"];//member_id
            $address_id = $request->param("address_id");//address_id
            $coupon_id = $request->only("coupon_id")["coupon_id"]; //添加使用优惠券id
            $order_type = $request->only("order_type")["order_type"];//1为选择直邮，2到店自提，3选择存茶
            $commodity_id = $request->only("goods_id")["goods_id"];//商品id
            $all_money = $request->only("order_amount")["order_amount"];//总价钱
            $goods_standard_id = $request->only("goods_standard_id")["goods_standard_id"];//规格id
            $numbers = $request->only("order_quantity")["order_quantity"];//商品对应数量
            $unit = $request->only("unit")["unit"];//商品单位
            $receipt_status = $request->only("receipt_status")["receipt_status"];//是否开发票
            $receipt_id = $request->only("receipt_id")["receipt_id"];//发票id
            $receipt_price = $request->only("receipt_price")["receipt_price"];//发票金额
            
            if(empty($user_id)){
                return ajax_error("未登录",['status'=>0]);
            }
            $member_grade_id = Db::name("member")->where("member_id",$user_id)->find();
            $member_consumption_discount =Db::name("member_grade")
                ->where("member_grade_id",$member_grade_id["member_grade_id"])
                ->find();
            $user_information =Db::name("member")->where("member_id",$user_id)->find();
            $buy_message = null; //买家留言
            $time = date("Y-m-d",time());
            $v = explode('-',$time);
            $time_second = date("H:i:s",time());
            $vs = explode(':',$time_second);
            

            foreach ($commodity_id as $keys=>$values){
                $goods_data = Db::name('goods')->where('id',$values)->find();
                $create_time = time();//下单时间
                $normal_time =Db::name("order_setting")->find();//订单设置的时间
                if(!empty($normal_time)){
                    $normal_future_time = strtotime("+". $normal_time['normal_time']." minute");
                } else {
                    $normal_future_time = null;
                }
                if($goods_data["goods_standard"]==0){
                    $datas['goods_image'] = $goods_data['goods_show_image'];//图片
                    $datas["goods_money"]=$goods_data['goods_new_money']* $member_consumption_discount["member_consumption_discount"];//商品价钱
                    $data['unit'] = explode(",",$goods_data['unit']);
                    $data['num'] = explode(",",$goods_data['num']);
                } else {
                    //图片
                    $special_data =Db::name("special")
                        ->where("id",$goods_standard_id[$keys])
                        ->find();
                    $datas['goods_image'] = $special_data['images'];   //图片
                    $datas["goods_money"]= $special_data['price'] * $member_consumption_discount["member_consumption_discount"];//商品价钱
                    $datas['goods_standard'] = $special_data["name"]; //商品规格  
                    $data['unit'] = explode(",",$special_data['unit']);
                    $data['num'] = explode(",",$special_data['num']);

                }
                if($order_type != 3){
                    if($order_type == 1){
                            $parts_order_number ="ZY".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号 
                            $is_address = Db::name('user_address')
                            ->where("id",$address_id)
                            ->where('user_id', $user_id)
                            ->find();
                            if (empty($is_address) ) {
                                return ajax_error('请填写收货地址',['status'=>0]);
                            } else {
                                $is_address_status = Db::name('user_address')
                                    ->where('user_id', $user_id)
                                    ->where('id',$address_id)
                                    ->find();
                                    $harvest_address_city = str_replace(',','',$is_address_status['address_name']);
                                    $harvest_address = $harvest_address_city.$is_address_status['harvester_real_address']; //收货人地址
                                    $harvester = $is_address_status['harvester'];
                                    $harvester_phone_num = $is_address_status['harvester_phone_num'];
                            } 
                        }

                    if ($order_type == 2) {
                        $parts_order_number ="ZT".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                        $is_address_status = Db::name('extract_address')
                        ->where('id',$address_id)
                        ->find();
                        if(empty($is_address_status)){
                            return ajax_error('请填写到店自提地址',['status'=>0]);
                        } else {
                            $harvest_address_city = str_replace(',','',$is_address_status['extract_address']);
                            $harvest_address = $harvest_address_city.$is_address_status['extract_real_address']; //收货人地址  
                            $harvester = null;
                            $harvester_phone_num = $is_address_status['phone_num'];              
                        } 
                    }
                        $datas["order_type"] = $order_type;//1为选择直邮，2到店自提，3选择存茶
                        $datas["distribution"] = $goods_data["distribution"];//是否分销
                        $datas["goods_describe"] = $goods_data["goods_describe"];//卖点
                        $datas["parts_goods_name"] = $goods_data["goods_name"];//名字
                        $datas["order_quantity"] = $numbers[$keys];//订单数量
                        $datas["member_id"] = $user_id;//用户id
                        $datas["user_account_name"] = $user_information["member_name"];//用户名
                        $datas["user_phone_number"] = $user_information["member_phone_num"];//用户名手机号
                        $datas["harvester"] = $harvester;
                        $datas["harvest_phone_num"] = $harvester_phone_num;
                        $datas["harvester_address"] = $harvest_address;
                        $datas["order_create_time"] = $create_time;
                        $datas["order_amount"] = $datas["goods_money"]*$numbers[$keys];//订单金额
                        $datas["order_real_pay"] = $all_money;//订单实际支付的金额(即优惠券抵扣之后的价钱）
                        $datas["status"] = 1;
                        $datas["goods_id"] = $values;
                        $datas["parts_order_number"] = $parts_order_number;//时间+4位随机数+用户id构成订单号
                        $datas["buy_message"] = $buy_message;//买家留言
                        $datas["normal_future_time"] = $normal_future_time;//未来时间
                        $datas["special_id"] = $goods_standard_id[$keys];//规格id
                        $datas["coupon_id"] = $coupon_id;
                        $datas["refund_amount"] = $all_money;
                        $datas["unit"] = $unit[$keys];
                        $datas["receipt_status"] = $receipt_status; 
                        $datas["receipt_id"] = $receipt_id;
                        $datas["receipt_price"] = $receipt_price ;
                                        
                        $res = Db::name('order')->insertGetId($datas);
                        if ($res) {
                            $order_datas =Db::name("order")
                                ->field("order_real_pay,parts_goods_name,parts_order_number")
                                ->where('id',$res)
                                ->where("member_id",$user_id)
                                ->find();
                            return ajax_success('下单成功',$order_datas);
                        }else{

                            return ajax_error('失败',['status'=>0]);
                        }
                    } else {
                        $parts_order_number ="CC".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                        $is_address_status = Db::name('store_house')
                        ->where('id',$address_id)
                        ->find();
                        $year = $request->only("year")["year"];//存茶年限
                        $harvest_address = $is_address_status['adress']; //仓库地址 
                        $store_name =  $is_address_status['name'];//仓库名
                        $harvester_phone_num = $is_address_status['phone'];
                        $datase["parts_order_number"] = $parts_order_number;//时间+4位随机数+用户id构成订单号
                        $datase["parts_goods_name"] = $goods_data["goods_name"];//名字
                        $datase["distribution"] = $goods_data["distribution"];//是否分销
                        $datase["goods_describe"] = $goods_data["goods_describe"];//卖点
                        $datase["order_quantity"] = $numbers[$keys];//订单数量
                        $datase["member_id"] = $user_id;//用户id
                        $datase["user_account_name"] = $user_information["member_name"];//用户名
                        $datase["user_phone_number"] = $user_information["member_phone_num"];//用户名手机号
                        $datase["harvest_phone_num"] = $harvester_phone_num;
                        $datase["harvester_address"] = $harvest_address;
                        $datase["order_create_time"] = $create_time;
                        $datase["order_amount"] = $datas["goods_money"]*$numbers[$keys];//订单金额
                        $datase["order_real_pay"] = $all_money;//订单实际支付的金额(即优惠券抵扣之后的价钱）
                        $datase["status"] = 1;
                        $datase["goods_id"] = $values;
                        $datase["buy_message"] = $buy_message;//买家留言
                        $datase["normal_future_time"] =$normal_future_time;//未来时间
                        $datase["special_id"] = $goods_standard_id[$keys];//规格id
                        $datase["coupon_id"] = $coupon_id;
                        $datase["receipt_status"] = $receipt_status; 
                        $datase["receipt_id"] = $receipt_id;
                        $datase["receipt_price"] = $receipt_price ;   

                        $rest_id = Db::name('order')->insertGetId($datase);
                        $datas = $datase;
                        $datas["store_house_id"] = $address_id;
                        $datas["store_name"] = $store_name;
                        $datas["store_unit"] = $unit[$keys];
                        $datas['end_time'] = strtotime(date('Y-m-d H:i:s',$create_time+$year*365*24*60*60));  
                        $datas["age_limit"] = $year;  
                
                        $key = array_search($unit[$keys],$data['unit']);
                        switch($key){
                            case 0:
                                $datas["store_number"] = $datas["order_quantity"].','.$unit[$keys];
                                break;
                            case 1:
                                $number_one = $data['unit'][$key];    //等级单位
                                $num_one = $data['num'][$key];        //等级数量
                                $number_zero = $data['unit'][$key-1]; //等级单位
                                $num_zero = $data['num'][$key]-1;     //等级数量

                                $number = $datas['order_quantity']/$num_one;
                                if($number > 1){
                                    $remainder = $datas['order_quantity']%$num_one;
                                    $datas["store_number"] = $number.','.$number_zero.','.$remainder.','.$num_one;
                                } else {
                                    $number = 0;
                                    $datas["store_number"] = $number.','.$number_zero.','.$datas['order_quantity'].','.$num_one;
                                }
                                break;
                            case 2: 
                                $number_two = $data['unit'][$key];    //等级单位
                                $num_two = $data['num'][$key];        //等级数量
                                $number_one = $data['unit'][$key-1];  //等级单位
                                $num_one = $data['num'][$key-1];      //等级数量
                                $number_zero = $data['unit'][$key-2]; //等级单位
                                $num_zero = $data['num'][$key-2];     //等级数量

                                $rank_one = $datas['order_quantity']/$number_two; //第二个数量
                                if($rank_one > 1){
                                    $three = $datas['order_quantity'] % $num_two; //第三个数量
                                    $two = $rank_one/$number_one ;//第一个数量
                                    if($two > 1){
                                        $foure = $rank_one % $number_one ;//第二个数量
                                        $datas["store_number"] = $two.','.$number_zero.','.$foure.','.$number_one.','.$rank_one.','.$number_two;
                                    } else {
                                        $two = 0;
                                        $datas["store_number"] = $two.','.$number_zero.','.$rank_one.','.$number_one.','.$three.','.$number_two;
                                    }
                                } else {
                                    $two = 0;
                                    $rank_six = 0;
                                    $datas["store_number"] = $two.','.$number_zero.','.$rank_six.','.$number_one.','.$datas['order_quantity'].','.$number_two;
                                }
                                break;                                                             
                        }
                        $res = Db::name('house_order')->insertGetId($datas);
                        if ($res) {
                            $order_datas =Db::name("house_order")
                                ->field("order_real_pay,parts_goods_name,parts_order_number")
                                ->where('id',$res)
                                ->where("member_id",$user_id)
                                ->find();
                            return ajax_success('下单成功',$order_datas);
                        }else{
                            return ajax_error('失败',['status'=>0]);
                    }         
                }
            }           
        }
    }

    /**
     **************李火生*******************
     * @param Request $request
     * Notes:购物车提交订单
     **************************************
     * @param Request $request
     */
    public function order_place_by_shopping(Request $request){
        if ($request->isPost()) {
            $shopping_id =$request->only("shopping_id")["shopping_id"];
            $coupon_id =$request->only("coupon_id")["coupon_id"]; //添加使用优惠券id
            $open_id =$request->only("open_id")["open_id"];
            $address_id =$request->only("address_id")["address_id"];
            $user_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            if(empty($user_id)){
                return ajax_error("未登录",['status'=>0]);
            }
            $member_grade_id =Db::name("member")->where("member_id",$user_id)->find();
            $member_consumption_discount =Db::name("member_grade")
                ->where("member_grade_id",$member_grade_id["member_grade_id"])
                ->find();
            $user_information =Db::name("member")
                ->where("member_id",$user_id)
                ->find();
            $is_address = Db::name('user_address')
                ->where("id",$address_id)
                ->where('user_id', $user_id)
                ->find();

            if (empty($is_address) ) {
                return ajax_error('请填写收货地址',['status'=>0]);
            }else{
                $is_address_status = Db::name('user_address')
                    ->where('user_id', $user_id)
                    ->where('id',$address_id)
                    ->find();
                if (empty($is_address_status) ) {
                    $is_address_status =$is_address;
                }
                $commodity_id = $request->only("goods_id")["goods_id"];//商品id
                $all_money =$request->only("order_amount")["order_amount"];//总价钱
                $goods_standard_id =$request->only("goods_standard_id")["goods_standard_id"];//规格id
                $numbers =$request->only("order_quantity")["order_quantity"];
                $order_type =$request->only("order_type")["order_type"]; //1为选择直邮，2到店自提，3选择存茶
                $harvest_address_city =str_replace(',','',$is_address_status['address_name']);
                $harvest_address =$harvest_address_city.$is_address_status['harvester_real_address']; //收货人地址
                $time=date("Y-m-d",time());
                $v=explode('-',$time);
                $time_second=date("H:i:s",time());
                $vs=explode(':',$time_second);
                //1为选择直邮，2到店自提，3选择存茶
                if($order_type ==1){
                    $parts_order_number ="ZY".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                }else if($order_type ==2){
                    $parts_order_number ="ZT".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                }else if($order_type ==3){
                    $parts_order_number ="CC".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                }
                $create_time = time();//下单时间
                $normal_time =Db::name("order_setting")->find();//订单设置的时间
                $normal_future_time =strtotime("+". $normal_time['normal_time']." minute");
                foreach ($commodity_id as $keys=>$values){
                    if (!empty($commodity_id)){
                        $goods_data = Db::name('goods')->where('id',$values)->find();
                        if (!empty($goods_data)) {
//                        if(!empty($data["buy_message"])){
//                            $buy_message =$data["buy_message"];
//                        }else{
                            $buy_message = NUll; //买家留言
//                        }
                            //判断是通用
                            if($goods_data["goods_standard"]==0){
                                $datas['goods_image'] = $goods_data['goods_show_image'];//图片
                                $datas["goods_money"]=$goods_data['goods_new_money']* $member_consumption_discount["member_consumption_discount"];//商品价钱
                            }else{
                                //图片
                                $special_data =Db::name("special")
                                    ->where("id",$goods_standard_id[$keys])
                                    ->find();
                                $datas['goods_image'] = $special_data['images'];//图片
                                $datas["goods_money"]= $special_data['price'] * $member_consumption_discount["member_consumption_discount"];//商品价钱
                                $datas['goods_standard'] = $special_data["name"]; //商品规格
                            }
                            $datas["order_type"] =$order_type;//1为选择直邮，2到店自提，3选择存茶
                            $datas["distribution"] =$goods_data["distribution"];//是否分销
                            $datas["goods_describe"] =$goods_data["goods_describe"];//卖点
                            $datas["parts_goods_name"] =$goods_data["goods_name"];//名字
                            $datas["order_quantity"] =$numbers[$keys];//订单数量
                            $datas["member_id"] =$user_id;//用户id
                            $datas["user_account_name"] =$user_information["member_name"];//用户名
                            $datas["user_phone_number"] =$user_information["member_phone_num"];//用户名手机号
                            $datas["harvester"] =$is_address_status['harvester'];
                            $datas["harvest_phone_num"] =$is_address_status['harvester_phone_num'];
                            $datas["harvester_address"] =$harvest_address;
                            $datas["order_create_time"] =$create_time;
                            $datas["order_amount"] =$datas["goods_money"]*$numbers[$keys];//订单金额
                            $datas["order_real_pay"] =$all_money;//订单实际支付的金额(即优惠券抵扣之后的价钱）
                            $datas["status"] =1;
                            $datas["goods_id"] =$values;
                            $datas["parts_order_number"] =$parts_order_number;//时间+4位随机数+用户id构成订单号
                            $datas["buy_message"] =$buy_message;//买家留言
                            $datas["normal_future_time"] =$normal_future_time;//未来时间
                            $datas["special_id"] =$goods_standard_id[$keys];//规格id
                            $datas["coupon_id"] =$coupon_id;
                            $datas["refund_amount"] =$all_money;
                            if(!empty($coupon_id)){ //优惠券金额
                                $datas["coupon_deductible"] = db("coupon")->where("id",$coupon_id)->value("money");
                            }
                            $res = Db::name('order')->insertGetId($datas);
//
//                            $coin = db("recommend_integral")->where("id",1)->value("coin"); //消费满多少送积分金额条件
//                            $intgral = db("recommend_integral")->where("id",1)->value("consume_integral"); //消费满多少送多少积分
                        }
                    }
                }
                if ($res) {
                    $order_datas =Db::name("order")
                        ->field("order_real_pay,parts_goods_name,parts_order_number")
                        ->where('id',$res)
                        ->where("member_id",$user_id)
                        ->find();

                    //清空购物车数据
                    if(is_array($shopping_id)){
                        $where ='id in('.implode(',',$shopping_id).')';
                    }else{
                        $where ='id='.$shopping_id;
                    }
                    $list =  Db::name('shopping')->where($where)->delete();
                    return ajax_success('下单成功',$order_datas);
                }else{
                    return ajax_error('失败',['status'=>0]);
                }


            }
        }
    }

        /**
     **************李火生*******************
     * @param Request $request
     * Notes:购物车提交订单(修改过的)
     **************************************
     * @param Request $request
     */
    public function order_place_by_shoppings(Request $request){
        if ($request->isPost()){
            $shopping_id = $request->only("shopping_id")["shopping_id"];
            $user_id = $request->only("member_id")["member_id"];//member_id
            $address_id = $request->param("address_id");//address_id
            $coupon_id = $request->only("coupon_id")["coupon_id"]; //添加使用优惠券id
            $order_type = $request->only("order_type")["order_type"];//1为选择直邮，2到店自提，3选择存茶
            $commodity_id = $request->only("goods_id")["goods_id"];//商品id
            $all_money = $request->only("order_amount")["order_amount"];//总价钱
            $goods_standard_id = $request->only("goods_standard_id")["goods_standard_id"];//规格id
            $numbers = $request->only("order_quantity")["order_quantity"];//商品对应数量
            $unit = $request->only("unit")["unit"];//商品单位
            $receipt_status = $request->only("receipt_status")["receipt_status"];//是否开发票
            $receipt_id = $request->only("receipt_id")["receipt_id"];//发票id
            $receipt_price = $request->only("receipt_price")["receipt_price"];//发票金额
            
            if(empty($user_id)){
                return ajax_error("未登录",['status'=>0]);
            }
            $member_grade_id =Db::name("member")->where("member_id",$user_id)->find();
            $member_consumption_discount =Db::name("member_grade")
                ->where("member_grade_id",$member_grade_id["member_grade_id"])
                ->find();
            $user_information =Db::name("member")->where("member_id",$user_id)->find();
            $buy_message = null; //买家留言
            $time = date("Y-m-d",time());
            $v = explode('-',$time);
            $time_second = date("H:i:s",time());
            $vs = explode(':',$time_second);
            

            foreach ($commodity_id as $keys=>$values){
                $goods_data = Db::name('goods')->where('id',$values)->find();
                $create_time = time();//下单时间
                $normal_time =Db::name("order_setting")->find();//订单设置的时间
                if(!empty($normal_time)){
                    $normal_future_time = strtotime("+". $normal_time['normal_time']." minute");
                } else {
                    $normal_future_time = null;
                }
                if($goods_data["goods_standard"]==0){
                    $datas['goods_image'] = $goods_data['goods_show_image'];//图片
                    $datas["goods_money"]=$goods_data['goods_new_money']* $member_consumption_discount["member_consumption_discount"];//商品价钱
                    $data['unit'] = explode(",",$goods_data['unit']);
                    $data['num'] = explode(",",$goods_data['num']);
                } else {
                    //图片
                    $special_data =Db::name("special")
                        ->where("id",$goods_standard_id[$keys])
                        ->find();
                    $datas['goods_image'] = $special_data['images'];//图片
                    $datas["goods_money"]= $special_data['price'] * $member_consumption_discount["member_consumption_discount"];//商品价钱
                    $datas['goods_standard'] = $special_data["name"]; //商品规格
                    $data['unit'] = explode(",",$special_data['unit']);
                    $data['num'] = explode(",",$special_data['num']);

                }
                if($order_type != 3){
                    if($order_type == 1){
                            $parts_order_number ="ZY".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号 
                            $is_address = Db::name('user_address')
                            ->where("id",$address_id)
                            ->where('user_id', $user_id)
                            ->find();
                            if (empty($is_address) ) {
                                return ajax_error('请填写收货地址',['status'=>0]);
                            } else {
                                $is_address_status = Db::name('user_address')
                                    ->where('user_id', $user_id)
                                    ->where('id',$address_id)
                                    ->find();
                                    $harvest_address_city = str_replace(',','',$is_address_status['address_name']);
                                    $harvest_address = $harvest_address_city.$is_address_status['harvester_real_address']; //收货人地址
                                    $harvester = $is_address_status['harvester'];
                                    $harvester_phone_num = $is_address_status['harvester_phone_num'];
                            } 
                        }

                    if ($order_type == 2) {
                        $parts_order_number ="ZT".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                        $is_address_status = Db::name('extract_address')
                        ->where('id',$address_id)
                        ->find();
                        if(empty($is_address_status)){
                            return ajax_error('请填写到店自提地址',['status'=>0]);
                        } else {
                            $harvest_address_city = str_replace(',','',$is_address_status['extract_address']);
                            $harvest_address = $harvest_address_city.$is_address_status['extract_real_address']; //收货人地址  
                            $harvester = null;
                            $harvester_phone_num = $is_address_status['phone_num'];              
                        } 
                    }
                        $datas["order_type"] = $order_type;//1为选择直邮，2到店自提，3选择存茶
                        $datas["distribution"] = $goods_data["distribution"];//是否分销
                        $datas["goods_describe"] = $goods_data["goods_describe"];//卖点
                        $datas["parts_goods_name"] = $goods_data["goods_name"];//名字
                        $datas["order_quantity"] = $numbers[$keys];//订单数量
                        $datas["member_id"] = $user_id;//用户id
                        $datas["user_account_name"] = $user_information["member_name"];//用户名
                        $datas["user_phone_number"] = $user_information["member_phone_num"];//用户名手机号
                        $datas["harvester"] = $harvester;
                        $datas["harvest_phone_num"] = $harvester_phone_num;
                        $datas["harvester_address"] = $harvest_address;
                        $datas["order_create_time"] = $create_time;
                        $datas["order_amount"] = $datas["goods_money"]*$numbers[$keys];//订单金额
                        $datas["order_real_pay"] = $all_money;//订单实际支付的金额(即优惠券抵扣之后的价钱）
                        $datas["status"] = 1;
                        $datas["goods_id"] = $values;
                        $datas["parts_order_number"] = $parts_order_number;//时间+4位随机数+用户id构成订单号
                        $datas["buy_message"] = $buy_message;//买家留言
                        $datas["normal_future_time"] = $normal_future_time;//未来时间
                        $datas["special_id"] = $goods_standard_id[$keys];//规格id
                        $datas["coupon_id"] = $coupon_id;
                        $datas["refund_amount"] = $all_money;
                        $datas["unit"] = $unit[$keys]; 
                        $datas["receipt_status"] = $receipt_status; 
                        $datas["receipt_id"] = $receipt_id;
                        $datas["receipt_price"] = $receipt_price ;                                        
                        $res = Db::name('order')->insertGetId($datas);
                    } else {
                        $parts_order_number ="CC".$v[0].$v[1].$v[2].$vs[0].$vs[1].$vs[2].($user_id+1001); //订单编号
                        $is_address_status = Db::name('store_house')
                        ->where('id',$address_id)
                        ->find();
                        $year = $request->only("year")["year"];//存茶年限
                        $house_price = $request->only("house_price")["house_price"];//存茶年限
                        $harvest_address = $is_address_status['adress']; //仓库地址 
                        $store_name =  $is_address_status['name'];//仓库名
                        $harvester_phone_num = $is_address_status['phone'];
                        $datase['goods_image'] = $datas['goods_image'];
                        $datase["goods_money"] = $datas["goods_money"];
                        $datase['goods_standard'] = $datas['goods_standard'];
                        $datase["parts_order_number"] = $parts_order_number;//时间+4位随机数+用户id构成订单号
                        $datase["parts_goods_name"] = $goods_data["goods_name"];//名字
                        $datase["distribution"] = $goods_data["distribution"];//是否分销
                        $datase["goods_describe"] = $goods_data["goods_describe"];//卖点
                        $datase["coupon_type"] = $goods_data["coupon_type"];//卖点
                        $datase["order_quantity"] = $numbers[$keys];//订单数量
                        $datase["member_id"] = $user_id;//用户id
                        $datase["user_account_name"] = $user_information["member_name"];//用户名
                        $datase["user_phone_number"] = $user_information["member_phone_num"];//用户名手机号
                        $datase["harvest_phone_num"] = $harvester_phone_num;
                        $datase["harvester_address"] = $harvest_address;
                        $datase["order_create_time"] = $create_time;
                        $datase["order_amount"] = $datas["goods_money"]*$numbers[$keys];//订单金额
                        $datase["order_real_pay"] = $all_money;//订单实际支付的金额(即优惠券抵扣之后的价钱）
                        $datase["status"] = 1;
                        $datase["goods_id"] = $values;
                        $datase["buy_message"] = $buy_message;//买家留言
                        $datase["normal_future_time"] =$normal_future_time;//未来时间
                        $datase["special_id"] = $goods_standard_id[$keys];//规格id
                        $datase["coupon_id"] = $coupon_id;
                        $datase["receipt_status"] = $receipt_status; 
                        $datase["receipt_id"] = $receipt_id;
                        $datase["receipt_price"] = $receipt_price ;

                        $rest_id = Db::name('order')->insertGetId($datase);
                        $datas = $datase;
                        $datas["store_house_id"] = $address_id;
                        $datas["store_name"] = $store_name;
                        $datas["store_unit"] = $unit[$keys];
                        $datas['end_time'] = strtotime(date('Y-m-d H:i:s',$create_time+$year*365*24*60*60));  
                        $datas["age_limit"] = $year;
                        $datas["house_price"] = $house_price[$keys];
                      
                        $key = array_search($unit[$keys],$data['unit']);
                        switch($key){
                            case 0:
                                $datas["store_number"] = $datas["order_quantity"].','.$unit[$keys];
                                break;
                            case 1:
                                $number_one = $data['unit'][$key];    //等级单位
                                $num_one = $data['num'][$key];        //等级数量
                                $number_zero = $data['unit'][$key-1]; //等级单位
                                $num_zero = $data['num'][$key-1];     //等级数量

                                $number = $datas['order_quantity']/$num_one;
                                if($number > 1){
                                    $remainder = $datas['order_quantity']%$num_one;
                                    $datas["store_number"] = $number.','.$number_zero.','.$remainder.','.$number_one;
                                } else {
                                    $number = 0;
                                    $datas["store_number"] = $number.','.$number_zero.','.$datas['order_quantity'].','.$number_one;
                                }
                                break;
                            case 2: 
                                $number_two = $data['unit'][$key];    //等级单位
                                $num_two = $data['num'][$key];        //等级数量
                                $number_one = $data['unit'][$key-1];  //等级单位
                                $num_one = $data['num'][$key-1];      //等级数量
                                $number_zero = $data['unit'][$key-2]; //等级单位
                                $num_zero = $data['num'][$key-2];     //等级数量

                                $rank_one = $datas['order_quantity']/$number_two; //第二个数量
                                if($rank_one > 1){
                                    $three = $datas['order_quantity'] % $num_two; //第三个数量
                                    $two = $rank_one/$number_one ; //第一个数量
                                    if($two > 1){
                                        $foure = $rank_one % $number_one ;//第二个数量
                                        $datas["store_number"] = $two.','.$number_zero.','.$foure.','.$number_one.','.$rank_one.','.$number_two;
                                    } else {
                                        $two = 0;
                                        $datas["store_number"] = $two.','.$number_zero.','.$rank_one.','.$number_one.','.$three.','.$number_two;
                                    }
                                } else {
                                    $two = 0;
                                    $rank_six = 0;
                                    $datas["store_number"] = $two.','.$number_zero.','.$rank_six.','.$number_one.','.$datas['order_quantity'].','.$number_two;
                                }
                                break;                                                             
                            }
                        $res = Db::name('house_order')->insertGetId($datas);        
                    }
                }
            if($order_type != 3){
                if ($res) {
                    $order_datas =Db::name("order")
                        ->field("order_real_pay,parts_goods_name,parts_order_number")
                        ->where('id',$res)
                        ->where("member_id",$user_id)
                        ->find();
                //清空购物车数据
                if(is_array($shopping_id)){
                    $where ='id in('.implode(',',$shopping_id).')';
                }else{
                    $where ='id='.$shopping_id;
                }
                $list =  Db::name('shopping')->where($where)->delete();    
                    return ajax_success('下单成功',$order_datas);
                }else{

                    return ajax_error('失败',['status'=>0]);
                } 
            } else {
                if ($res) {
                    $order_datas =Db::name("house_order")
                        ->field("order_real_pay,parts_goods_name,parts_order_number")
                        ->where('id',$res)
                        ->where("member_id",$user_id)
                        ->find();
                //清空购物车数据
                if(is_array($shopping_id)){
                    $where ='id in('.implode(',',$shopping_id).')';
                }else{
                    $where ='id='.$shopping_id;
                }
                $list =  Db::name('shopping')->where($where)->delete();
                    return ajax_success('下单成功',$order_datas);
                }else{
                    return ajax_error('失败',['status'=>0]);
                } 
            }          
        }
    }


    /**
     **************李火生*******************
     * @param Request $request
     * Notes:订单详情页面信息
     **************************************
     * @param Request $request
     * @return \think\response\View|void
     */
    public function order_detail(Request $request){
        if($request->isPost()) {
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $user_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            $order_number =$request->only("order_number")["order_number"];//订单编号
            $condition = "`member_id` = " . $user_id .  " and `parts_order_number` = " . $order_number;
            $data = Db::name("order")
                ->where($condition)
                ->select();
            if (!empty($data)) {
                $datas["buy_message"] = $data[0]["buy_message"];//买家留言
                $datas["create_time"] = $data[0]["order_create_time"];//订单创建时间
                $datas["parts_order_number"] = $data[0]["parts_order_number"];//订单编号
                $datas["pay_time"] = $data[0]["pay_time"]; //支付时间
                $datas["harvester"] = $data[0]["harvester"];//收货人
                $datas["harvest_phone_num"] = $data[0]["harvest_phone_num"];//收件人电话
                $datas["harvester_address"] = $data[0]["harvester_address"];//收件人地址
                $datas["status"] = $data[0]["status"];//状态
                foreach ($data as $ks=>$vs){
                    $datas["all_goods_money"][] =$vs["goods_money"]*$vs["order_quantity"];
                    $data[$ks]["express_namess"] =str_to_chinese($vs["express_name"]);
                }
                $datas["all_goods_pays"] =array_sum($datas["all_goods_money"]); //商品总额（商品*数量）
                $datas["normal_future_time"] = $data[0]["normal_future_time"];//正常订单未付款自动关闭的时间
                $datas["all_order_real_pay"] = $data[0]["order_real_pay"];//订单实际支付
                $datas["all_numbers"] = array_sum(array_map(create_function('$vals', 'return $vals["order_quantity"];'), $data));//订单总数量
                $datas["info"] = $data;
                if (!empty($datas)) {
                    return ajax_success("数据返回成功", $datas);
                } else {
                    return ajax_error("没有数据信息", ["status" => 0]);
                }
            } else {
                return ajax_error("订单信息错误", ["status" => 0]);
            }
        }

        return view('order_parts_detail');
    }


    /**
     **************李火生*******************
     * @param Request $request
     * Notes:未付款判断时间是否过了订单设置的时间，过了则进行自动关闭
     **************************************
     * @param Request $request
     */
    public function order_detail_cancel(Request $request){
        if($request->isPost()){
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $user_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");

            $cancel_order_description =$request->only('cancel_order_description')["cancel_order_description"];//取消原因
            $parts_order_number =$request->only("parts_order_number")["parts_order_number"];//订单编号

            if(!empty($parts_order_number)){
                $res =Db::name("order")
                    ->where("parts_order_number",$parts_order_number)
                    ->select();
                if(!empty($res)){
                    $normal_future_time =$res[0]["normal_future_time"];//未来时间（超过则自动关闭有优惠抵扣退回优惠抵扣）
                    $new_time =time();
                    if($new_time >= $normal_future_time){
                        foreach($res as $k=>$v){
                            $is_use_integral[$k] =Db::name("order_parts")
                                ->field("integral_discount_setting_id,id,integral_deductible_num")
                                ->where("id",$v["id"])
                                ->having("integral_discount_setting_id","NEQ",NULL)
                                ->group("integral_discount_setting_id")
                                ->find();
                            $data = [
                                "status"=>9,
                                "coupon_id"=>0,
                                "cancel_order_description"=>$cancel_order_description
                            ];
                            $bool =Db::name("order_parts")->where("id",$v["id"])->update($data);
                        }

                        if($bool){
                            //取消订单退回积分到积余额
                            if(!empty( $is_use_integral)){
                                if(!empty($is_use_integral[0]["integral_deductible_num"])){
                                    $user_info = Db::name("user")->field("user_integral_wallet,user_integral_wallet_consumed")->where("id",$user_id)->find();
                                    $update_data =[
                                        "user_integral_wallet"=>$user_info["user_integral_wallet"] + $is_use_integral[0]["integral_deductible_num"],
                                        "user_integral_wallet_consumed"=>$user_info["user_integral_wallet_consumed"] - $is_use_integral[0]["integral_deductible_num"]
                                    ];
                                    Db::name("user")->where("id",$user_id)->update($update_data); //积分增加
                                    $integral_data =[
                                        "user_id"=>$user_id,//用户ID
                                        "integral_operation"=>"+".$is_use_integral[0]["integral_deductible_num"],//积分操作
                                        "integral_balance"=>$user_info["user_integral_wallet"] + $is_use_integral[0]["integral_deductible_num"],//积分余额
                                        "integral_type"=> 1,//积分类型
                                        "operation_time"=>date("Y-m-d H:i:s") ,//操作时间
                                        "integral_remarks"=>"订单号:".$parts_order_number."因超时未付款，取消退回".$is_use_integral[0]["integral_deductible_num"]."积分",//积分备注
                                    ];
                                    Db::name("integral")->insert($integral_data); //插入积分消费记录
                                }
                            }
                            return ajax_success("取消成功",["status"=>1]);
                        }else{
                            return ajax_error("取消失败",["status"=>0]);
                        }
                    }else{
                        return ajax_error("还未到达自动取消订单时间",["status"=>0]);
                    }
                }
            }else{
            return ajax_error("所传参数不能为空",["status"=>0]);
            }
        }
    }

    /**
     **************李火生*******************
     * @param Request $request
     * Notes:订单状态全部订单接口
     **************************************
     * @param Request $request
     */
    public function   ios_api_order_all(Request $request)
    {
        if ($request->isPost()) {
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $member_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            if(empty($member_id)){
                exit(json_encode(array("status" => 2, "info" => "请重新登录","data"=>["status"=>0])));
            }
            $data = Db::name('order')
                ->field('parts_order_number,order_create_time,group_concat(id) order_id')
                ->where('member_id', $member_id)
                ->order('order_create_time', 'desc')
                ->group('parts_order_number')
                ->select();
            foreach ($data as $key=>$value) {
                if (strpos($value["order_id"], ",")) {
                    $order_id = explode(',', $value["order_id"]);
                    foreach ($order_id as $k=>$v){
                        $return_data_info[] = Db::name('order')
                            ->where('id', $v)
                            ->where('member_id', $member_id)
                            ->order('order_create_time', 'desc')
                            ->find();
                    }
                    foreach ($return_data_info as $ke => $item) {
                        $parts_order_number_all[$ke] = $item['parts_order_number'];
                    }
                    $unique_order_number = array_merge(array_unique($parts_order_number_all));

                    foreach ( $unique_order_number as $da_k =>$da_v){
                        $order_data['info'][$da_k] = Db::name('order')
                            ->where('member_id', $member_id)
                            ->where('parts_order_number', $da_v)
                            ->order('order_create_time', 'desc')
                            ->select();
                        $names = Db::name("order")
                            ->where("parts_order_number", $da_v)
                            ->where("member_id", $member_id)
                            ->find();
                        $order_data['status'][$da_k] = $names['status'];
                        $order_data["parts_order_number"][$da_k] = $names["parts_order_number"];
                        $order_data["all_order_real_pay"][$da_k] = $names["order_real_pay"];
                        $order_data["order_create_time"][$da_k] = $names["order_create_time"];
                        foreach ($order_data["info"] as $kk => $vv) {
                            $order_data["all_numbers"][$kk] = array_sum(array_map(create_function('$vals', 'return $vals["order_quantity"];'), $vv));
                        }
                    }
                } else {
                    $return_data = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                    $data_information["all_order_real_pay"][] = $return_data["order_real_pay"];
                    $data_information["all_numbers"][] = $return_data["order_quantity"];
                    $data_information['status'][] = $return_data['status'];
                    $data_information['parts_order_number'][] = $return_data['parts_order_number'];
                    $data_information['order_create_time'][] = $value['order_create_time'];
                    $data_information['all'][] = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                }
            }
            if(!empty($order_data)){

                //所有信息
                foreach ($order_data["info"] as $i=>$j){
                    if(!empty($j)){
                        $new_arr[] =$j;
                    }
                }
                foreach ($new_arr as $i=>$j){
                    $end_info[$i]["info"] =$j;
                }
                //状态值
                foreach ($order_data['status'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_status[] = $j;
                    }
                }
                foreach ($new_arr_status as $i=>$j){
                    $end_info[$i]['status'] = $j;
                }
                //实际支付的金额
                foreach ($order_data['all_order_real_pay'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_pay[] =$j;
                    }
                }
                foreach ($new_arr_pay as $i=>$j){
                    $end_info[$i]['all_order_real_pay'] = $j;
                }
                //总数量
                foreach ($order_data['all_numbers'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_numbers[] =$j;
                    }
                }
                foreach ($new_arr_all_numbers as $i=>$j){
                    $end_info[$i]['all_numbers'] = $j;
                }

                //订单编号
                foreach ($order_data['parts_order_number'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_order_number[] =$j;
                    }
                }
                foreach ($new_arr_all_order_number as $i=>$j){
                    $end_info[$i]['parts_order_number'] = $j;
                }

                //订单创建时间
                foreach ($order_data['order_create_time'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_order_create_time[] =$j;
                    }
                }
                foreach ($new_arr_order_create_time as $i=>$j){
                    $end_info[$i]['order_create_times'] = $j;
                }
            }
            if(!empty($data_information)){
                if(!empty($new_arr)){
                    $count =count($new_arr);
                }else{
                    $count =0;
                }
                //支付状态
                foreach ($data_information['status'] as $a=>$b){
                    $end_info[$a+$count]['status'] = $b;
                }
                //总支付
                foreach ($data_information['all_order_real_pay'] as $a=>$b){
                    $end_info[$a+$count]['all_order_real_pay'] = $b;
                }
                //所有数量
                foreach ($data_information['all_numbers'] as $a=>$b){
                    $end_info[$a+$count]['all_numbers'] = $b;
                }
                //订单编号
                foreach ($data_information['parts_order_number'] as $a=>$b){
                    $end_info[$a+$count]['parts_order_number'] = $b;
                }
                //所有信息

                foreach ($data_information['all'] as $a=>$b){
                    $end_info[$a+$count]['info'][] = $b;
                }
                //创建订单时间
                foreach ($data_information['order_create_time'] as $a=>$b){
                    $end_info[$a+$count]['order_create_times'] = $b;
                }
            }
            if (!empty($end_info)) {
                $ords =array();
                foreach ($end_info as $vl){
                    $ords[] =intval($vl["order_create_times"]);
                }
                array_multisort($ords,SORT_DESC,$end_info);
                return ajax_success('数据', $end_info);
            } else {
                return ajax_error('没数据');
            }

        }
    }

    /**
     **************李火生*******************
     * @param Request $request
     * Notes:我的待支付订单
     **************************************
     * @param Request $request
     */
    public function   ios_api_order_wait_pay(Request $request)
    {
        if ($request->isPost()) {
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $member_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            if(empty($member_id)){
                exit(json_encode(array("status" => 2, "info" => "请重新登录","data"=>["status"=>0])));
            }
            $data = Db::name('order')
                ->field('parts_order_number,order_create_time,group_concat(id) order_id')
                ->where('member_id', $member_id)
                ->where("status",1)
                ->order('order_create_time', 'desc')
                ->group('parts_order_number')
                ->select();
            foreach ($data as $key=>$value) {
                if (strpos($value["order_id"], ",")) {
                    $order_id = explode(',', $value["order_id"]);
                    foreach ($order_id as $k=>$v){
                        $return_data_info[] = Db::name('order')
                            ->where('id', $v)
                            ->where('member_id', $member_id)
                            ->order('order_create_time', 'desc')
                            ->find();
                    }
                    foreach ($return_data_info as $ke => $item) {
                        $parts_order_number_all[$ke] = $item['parts_order_number'];
                    }
                    $unique_order_number = array_merge(array_unique($parts_order_number_all));

                    foreach ( $unique_order_number as $da_k =>$da_v){
                        $order_data['info'][$da_k] = Db::name('order')
                            ->where('member_id', $member_id)
                            ->where('parts_order_number', $da_v)
                            ->order('order_create_time', 'desc')
                            ->select();
                        $names = Db::name("order")
                            ->where("parts_order_number", $da_v)
                            ->where("member_id", $member_id)
                            ->find();
                        $order_data['status'][$da_k] = $names['status'];
                        $order_data["parts_order_number"][$da_k] = $names["parts_order_number"];
                        $order_data["all_order_real_pay"][$da_k] = $names["order_real_pay"];
                        $order_data["order_create_time"][$da_k] = $names["order_create_time"];
                        foreach ($order_data["info"] as $kk => $vv) {
                            $order_data["all_numbers"][$kk] = array_sum(array_map(create_function('$vals', 'return $vals["order_quantity"];'), $vv));
                        }
                    }
                } else {
                    $return_data = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                    $data_information["all_order_real_pay"][] = $return_data["order_real_pay"];
                    $data_information["all_numbers"][] = $return_data["order_quantity"];
                    $data_information['status'][] = $return_data['status'];
                    $data_information['parts_order_number'][] = $return_data['parts_order_number'];
                    $data_information['order_create_time'][] = $value['order_create_time'];
                    $data_information['all'][] = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                }
            }
            if(!empty($order_data)){

                //所有信息
                foreach ($order_data["info"] as $i=>$j){
                    if(!empty($j)){
                        $new_arr[] =$j;
                    }
                }
                foreach ($new_arr as $i=>$j){
                    $end_info[$i]["info"] =$j;
                }
                //状态值
                foreach ($order_data['status'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_status[] = $j;
                    }
                }
                foreach ($new_arr_status as $i=>$j){
                    $end_info[$i]['status'] = $j;
                }
                //实际支付的金额
                foreach ($order_data['all_order_real_pay'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_pay[] =$j;
                    }
                }
                foreach ($new_arr_pay as $i=>$j){
                    $end_info[$i]['all_order_real_pay'] = $j;
                }
                //总数量
                foreach ($order_data['all_numbers'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_numbers[] =$j;
                    }
                }
                foreach ($new_arr_all_numbers as $i=>$j){
                    $end_info[$i]['all_numbers'] = $j;
                }

                //订单编号
                foreach ($order_data['parts_order_number'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_order_number[] =$j;
                    }
                }
                foreach ($new_arr_all_order_number as $i=>$j){
                    $end_info[$i]['parts_order_number'] = $j;
                }

                //订单创建时间
                foreach ($order_data['order_create_time'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_order_create_time[] =$j;
                    }
                }
                foreach ($new_arr_order_create_time as $i=>$j){
                    $end_info[$i]['order_create_times'] = $j;
                }
            }
            if(!empty($data_information)){
                if(!empty($new_arr)){
                    $count =count($new_arr);
                }else{
                    $count =0;
                }
                //支付状态
                foreach ($data_information['status'] as $a=>$b){
                    $end_info[$a+$count]['status'] = $b;
                }
                //总支付
                foreach ($data_information['all_order_real_pay'] as $a=>$b){
                    $end_info[$a+$count]['all_order_real_pay'] = $b;
                }
                //所有数量
                foreach ($data_information['all_numbers'] as $a=>$b){
                    $end_info[$a+$count]['all_numbers'] = $b;
                }
                //订单编号
                foreach ($data_information['parts_order_number'] as $a=>$b){
                    $end_info[$a+$count]['parts_order_number'] = $b;
                }
                //所有信息

                foreach ($data_information['all'] as $a=>$b){
                    $end_info[$a+$count]['info'][] = $b;
                }
                //创建订单时间
                foreach ($data_information['order_create_time'] as $a=>$b){
                    $end_info[$a+$count]['order_create_times'] = $b;
                }
            }
            if (!empty($end_info)) {
                $ords =array();
                foreach ($end_info as $vl){
                    $ords[] =intval($vl["order_create_times"]);
                }
                array_multisort($ords,SORT_DESC,$end_info);
                return ajax_success('数据', $end_info);
            } else {
                return ajax_error('没数据');
            }

        }
    }

    /**
     **************李火生*******************
     * @param Request $request
     * Notes:我的待发货订单
     **************************************
     * @param Request $request
     */
    public function   ios_api_order_wait_send(Request $request)
    {
        if ($request->isPost()) {
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $member_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            if(empty($member_id)){
                exit(json_encode(array("status" => 2, "info" => "请重新登录","data"=>["status"=>0])));
            }
            $condition ="`status` = '2' or `status` = '3'";
            $data = Db::name('order')
                ->field('parts_order_number,pay_time,group_concat(id) order_id')
                ->where('member_id', $member_id)
                ->where($condition)
                ->order('pay_time', 'desc')
                ->group('parts_order_number')
                ->select();
            foreach ($data as $key=>$value) {
                if (strpos($value["order_id"], ",")) {
                    $order_id = explode(',', $value["order_id"]);
                    foreach ($order_id as $k=>$v){
                        $return_data_info[] = Db::name('order')
                            ->where('id', $v)
                            ->where('member_id', $member_id)
                            ->find();
                    }
                    foreach ($return_data_info as $ke => $item) {
                        $parts_order_number_all[$ke] = $item['parts_order_number'];
                    }
                    $unique_order_number = array_merge(array_unique($parts_order_number_all));

                    foreach ( $unique_order_number as $da_k =>$da_v){
                        $order_data['info'][$da_k] = Db::name('order')
                            ->where('member_id', $member_id)
                            ->where('parts_order_number', $da_v)
                            ->order('order_create_time', 'desc')
                            ->select();
                        $names = Db::name("order")
                            ->where("parts_order_number", $da_v)
                            ->where("member_id", $member_id)
                            ->find();
                        $order_data['status'][$da_k] = $names['status'];
                        $order_data["parts_order_number"][$da_k] = $names["parts_order_number"];
                        $order_data["all_order_real_pay"][$da_k] = $names["order_real_pay"];
                        $order_data["pay_time"][$da_k] = $names["pay_time"];
                        foreach ($order_data["info"] as $kk => $vv) {
                            $order_data["all_numbers"][$kk] = array_sum(array_map(create_function('$vals', 'return $vals["order_quantity"];'), $vv));
                        }
                    }
                } else {
                    $return_data = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                    $data_information["all_order_real_pay"][] = $return_data["order_real_pay"];
                    $data_information["all_numbers"][] = $return_data["order_quantity"];
                    $data_information['status'][] = $return_data['status'];
                    $data_information['parts_order_number'][] = $return_data['parts_order_number'];
                    $data_information['pay_time'][] = $value['pay_time'];
                    $data_information['all'][] = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                }
            }
            if(!empty($order_data)){

                //所有信息
                foreach ($order_data["info"] as $i=>$j){
                    if(!empty($j)){
                        $new_arr[] =$j;
                    }
                }
                foreach ($new_arr as $i=>$j){
                    $end_info[$i]["info"] =$j;
                }
                //状态值
                foreach ($order_data['status'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_status[] = $j;
                    }
                }
                foreach ($new_arr_status as $i=>$j){
                    $end_info[$i]['status'] = $j;
                }
                //实际支付的金额
                foreach ($order_data['all_order_real_pay'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_pay[] =$j;
                    }
                }
                foreach ($new_arr_pay as $i=>$j){
                    $end_info[$i]['all_order_real_pay'] = $j;
                }
                //总数量
                foreach ($order_data['all_numbers'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_numbers[] =$j;
                    }
                }
                foreach ($new_arr_all_numbers as $i=>$j){
                    $end_info[$i]['all_numbers'] = $j;
                }

                //订单编号
                foreach ($order_data['parts_order_number'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_order_number[] =$j;
                    }
                }
                foreach ($new_arr_all_order_number as $i=>$j){
                    $end_info[$i]['parts_order_number'] = $j;
                }

                //订单创建时间
                foreach ($order_data['pay_time'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_order_create_time[] =$j;
                    }
                }
                foreach ($new_arr_order_create_time as $i=>$j){
                    $end_info[$i]['pay_time'] = $j;
                }
            }
            if(!empty($data_information)){
                if(!empty($new_arr)){
                    $count =count($new_arr);
                }else{
                    $count =0;
                }
                //支付状态
                foreach ($data_information['status'] as $a=>$b){
                    $end_info[$a+$count]['status'] = $b;
                }
                //总支付
                foreach ($data_information['all_order_real_pay'] as $a=>$b){
                    $end_info[$a+$count]['all_order_real_pay'] = $b;
                }
                //所有数量
                foreach ($data_information['all_numbers'] as $a=>$b){
                    $end_info[$a+$count]['all_numbers'] = $b;
                }
                //订单编号
                foreach ($data_information['parts_order_number'] as $a=>$b){
                    $end_info[$a+$count]['parts_order_number'] = $b;
                }
                //所有信息

                foreach ($data_information['all'] as $a=>$b){
                    $end_info[$a+$count]['info'][] = $b;
                }
                //创建订单时间
                foreach ($data_information['pay_time'] as $a=>$b){
                    $end_info[$a+$count]['pay_time'] = $b;
                }
            }
            if (!empty($end_info)) {
                $ords =array();
                foreach ($end_info as $vl){
                    $ords[] =intval($vl["pay_time"]);
                }
                array_multisort($ords,SORT_DESC,$end_info);
                return ajax_success('数据', $end_info);
            } else {
                return ajax_error('没数据');
            }

        }
    }

    /**
     **************李火生*******************
     * @param Request $request
     * Notes:我的待收货订单
     **************************************
     * @param Request $request
     */
    public function   ios_api_order_wait_deliver(Request $request)
    {
        if ($request->isPost()) {
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $member_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            if(empty($member_id)){
                exit(json_encode(array("status" => 2, "info" => "请重新登录","data"=>["status"=>0])));
            }
            $condition =" `status` = '4' or `status` = '5' ";
            $data = Db::name('order')
                ->field('parts_order_number,order_create_time,group_concat(id) order_id')
                ->where('member_id', $member_id)
                ->where($condition)
                ->order('order_create_time', 'desc')
                ->group('parts_order_number')
                ->select();
            foreach ($data as $key=>$value) {
                if (strpos($value["order_id"], ",")) {
                    $order_id = explode(',', $value["order_id"]);
                    foreach ($order_id as $k=>$v){
                        $return_data_info[] = Db::name('order')
                            ->where('id', $v)
                            ->where('member_id', $member_id)
                            ->order('order_create_time', 'desc')
                            ->find();
                    }
                    foreach ($return_data_info as $ke => $item) {
                        $parts_order_number_all[$ke] = $item['parts_order_number'];
                    }
                    $unique_order_number = array_merge(array_unique($parts_order_number_all));

                    foreach ( $unique_order_number as $da_k =>$da_v){
                        $order_data['info'][$da_k] = Db::name('order')
                            ->where('member_id', $member_id)
                            ->where('parts_order_number', $da_v)
                            ->order('order_create_time', 'desc')
                            ->select();
                        $names = Db::name("order")
                            ->where("parts_order_number", $da_v)
                            ->where("member_id", $member_id)
                            ->find();
                        $order_data['status'][$da_k] = $names['status'];
                        $order_data["parts_order_number"][$da_k] = $names["parts_order_number"];
                        $order_data["all_order_real_pay"][$da_k] = $names["order_real_pay"];
                        $order_data["order_create_time"][$da_k] = $names["order_create_time"];
                        foreach ($order_data["info"] as $kk => $vv) {
                            $order_data["all_numbers"][$kk] = array_sum(array_map(create_function('$vals', 'return $vals["order_quantity"];'), $vv));
                        }
                    }
                } else {
                    $return_data = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                    $data_information["all_order_real_pay"][] = $return_data["order_real_pay"];
                    $data_information["all_numbers"][] = $return_data["order_quantity"];
                    $data_information['status'][] = $return_data['status'];
                    $data_information['parts_order_number'][] = $return_data['parts_order_number'];
                    $data_information['order_create_time'][] = $value['order_create_time'];
                    $data_information['all'][] = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                }
            }
            if(!empty($order_data)){

                //所有信息
                foreach ($order_data["info"] as $i=>$j){
                    if(!empty($j)){
                        $new_arr[] =$j;
                    }
                }
                foreach ($new_arr as $i=>$j){
                    $end_info[$i]["info"] =$j;
                }
                //状态值
                foreach ($order_data['status'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_status[] = $j;
                    }
                }
                foreach ($new_arr_status as $i=>$j){
                    $end_info[$i]['status'] = $j;
                }
                //实际支付的金额
                foreach ($order_data['all_order_real_pay'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_pay[] =$j;
                    }
                }
                foreach ($new_arr_pay as $i=>$j){
                    $end_info[$i]['all_order_real_pay'] = $j;
                }
                //总数量
                foreach ($order_data['all_numbers'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_numbers[] =$j;
                    }
                }
                foreach ($new_arr_all_numbers as $i=>$j){
                    $end_info[$i]['all_numbers'] = $j;
                }

                //订单编号
                foreach ($order_data['parts_order_number'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_order_number[] =$j;
                    }
                }
                foreach ($new_arr_all_order_number as $i=>$j){
                    $end_info[$i]['parts_order_number'] = $j;
                }

                //订单创建时间
                foreach ($order_data['order_create_time'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_order_create_time[] =$j;
                    }
                }
                foreach ($new_arr_order_create_time as $i=>$j){
                    $end_info[$i]['order_create_times'] = $j;
                }
            }
            if(!empty($data_information)){
                if(!empty($new_arr)){
                    $count =count($new_arr);
                }else{
                    $count =0;
                }
                //支付状态
                foreach ($data_information['status'] as $a=>$b){
                    $end_info[$a+$count]['status'] = $b;
                }
                //总支付
                foreach ($data_information['all_order_real_pay'] as $a=>$b){
                    $end_info[$a+$count]['all_order_real_pay'] = $b;
                }
                //所有数量
                foreach ($data_information['all_numbers'] as $a=>$b){
                    $end_info[$a+$count]['all_numbers'] = $b;
                }
                //订单编号
                foreach ($data_information['parts_order_number'] as $a=>$b){
                    $end_info[$a+$count]['parts_order_number'] = $b;
                }
                //所有信息

                foreach ($data_information['all'] as $a=>$b){
                    $end_info[$a+$count]['info'][] = $b;
                }
                //创建订单时间
                foreach ($data_information['order_create_time'] as $a=>$b){
                    $end_info[$a+$count]['order_create_times'] = $b;
                }
            }
            if (!empty($end_info)) {
                $ords =array();
                foreach ($end_info as $vl){
                    $ords[] =intval($vl["order_create_times"]);
                }
                array_multisort($ords,SORT_DESC,$end_info);
                return ajax_success('数据', $end_info);
            } else {
                return ajax_error('没数据');
            }

        }
    }

    /**
     **************李火生*******************
     * @param Request $request
     * Notes:我待评价订单
     **************************************
     * @param Request $request
     */
    public function   ios_api_order_wait_evaluate(Request $request)
    {
        if ($request->isPost()) {
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $member_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            if(empty($member_id)){
                exit(json_encode(array("status" => 2, "info" => "请重新登录","data"=>["status"=>0])));
            }
            $condition ="`status` = '6' or `status` = '7'";
            $data = Db::name('order')
                ->field('parts_order_number,order_create_time,group_concat(id) order_id')
                ->where('member_id', $member_id)
                ->where($condition)
                ->order('order_create_time', 'desc')
                ->group('parts_order_number')
                ->select();
            foreach ($data as $key=>$value) {
                if (strpos($value["order_id"], ",")) {
                    $order_id = explode(',', $value["order_id"]);
                    foreach ($order_id as $k=>$v){
                        $return_data_info[] = Db::name('order')
                            ->where('id', $v)
                            ->where('member_id', $member_id)
                            ->order('order_create_time', 'desc')
                            ->find();
                    }
                    foreach ($return_data_info as $ke => $item) {
                        $parts_order_number_all[$ke] = $item['parts_order_number'];
                    }
                    $unique_order_number = array_merge(array_unique($parts_order_number_all));

                    foreach ( $unique_order_number as $da_k =>$da_v){
                        $order_data['info'][$da_k] = Db::name('order')
                            ->where('member_id', $member_id)
                            ->where('parts_order_number', $da_v)
                            ->order('order_create_time', 'desc')
                            ->select();
                        $names = Db::name("order")
                            ->where("parts_order_number", $da_v)
                            ->where("member_id", $member_id)
                            ->find();
                        $order_data['status'][$da_k] = $names['status'];
                        $order_data["parts_order_number"][$da_k] = $names["parts_order_number"];
                        $order_data["all_order_real_pay"][$da_k] = $names["order_real_pay"];
                        $order_data["order_create_time"][$da_k] = $names["order_create_time"];
                        foreach ($order_data["info"] as $kk => $vv) {
                            $order_data["all_numbers"][$kk] = array_sum(array_map(create_function('$vals', 'return $vals["order_quantity"];'), $vv));
                        }
                    }
                } else {
                    $return_data = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                    $data_information["all_order_real_pay"][] = $return_data["order_real_pay"];
                    $data_information["all_numbers"][] = $return_data["order_quantity"];
                    $data_information['status'][] = $return_data['status'];
                    $data_information['parts_order_number'][] = $return_data['parts_order_number'];
                    $data_information['order_create_time'][] = $value['order_create_time'];
                    $data_information['all'][] = Db::name('order')
                        ->where('id', $value['order_id'])
                        ->find();
                }
            }
            if(!empty($order_data)){

                //所有信息
                foreach ($order_data["info"] as $i=>$j){
                    if(!empty($j)){
                        $new_arr[] =$j;
                    }
                }
                foreach ($new_arr as $i=>$j){
                    $end_info[$i]["info"] =$j;
                }
                //状态值
                foreach ($order_data['status'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_status[] = $j;
                    }
                }
                foreach ($new_arr_status as $i=>$j){
                    $end_info[$i]['status'] = $j;
                }
                //实际支付的金额
                foreach ($order_data['all_order_real_pay'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_pay[] =$j;
                    }
                }
                foreach ($new_arr_pay as $i=>$j){
                    $end_info[$i]['all_order_real_pay'] = $j;
                }
                //总数量
                foreach ($order_data['all_numbers'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_numbers[] =$j;
                    }
                }
                foreach ($new_arr_all_numbers as $i=>$j){
                    $end_info[$i]['all_numbers'] = $j;
                }

                //订单编号
                foreach ($order_data['parts_order_number'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_all_order_number[] =$j;
                    }
                }
                foreach ($new_arr_all_order_number as $i=>$j){
                    $end_info[$i]['parts_order_number'] = $j;
                }

                //订单创建时间
                foreach ($order_data['order_create_time'] as $i => $j) {
                    if(!empty($j)){
                        $new_arr_order_create_time[] =$j;
                    }
                }
                foreach ($new_arr_order_create_time as $i=>$j){
                    $end_info[$i]['order_create_times'] = $j;
                }
            }
            if(!empty($data_information)){
                if(!empty($new_arr)){
                    $count =count($new_arr);
                }else{
                    $count =0;
                }
                //支付状态
                foreach ($data_information['status'] as $a=>$b){
                    $end_info[$a+$count]['status'] = $b;
                }
                //总支付
                foreach ($data_information['all_order_real_pay'] as $a=>$b){
                    $end_info[$a+$count]['all_order_real_pay'] = $b;
                }
                //所有数量
                foreach ($data_information['all_numbers'] as $a=>$b){
                    $end_info[$a+$count]['all_numbers'] = $b;
                }
                //订单编号
                foreach ($data_information['parts_order_number'] as $a=>$b){
                    $end_info[$a+$count]['parts_order_number'] = $b;
                }
                //所有信息

                foreach ($data_information['all'] as $a=>$b){
                    $end_info[$a+$count]['info'][] = $b;
                }
                //创建订单时间
                foreach ($data_information['order_create_time'] as $a=>$b){
                    $end_info[$a+$count]['order_create_times'] = $b;
                }
            }
            if (!empty($end_info)) {
                $ords =array();
                foreach ($end_info as $vl){
                    $ords[] =intval($vl["order_create_times"]);
                }
                array_multisort($ords,SORT_DESC,$end_info);
                return ajax_success('数据', $end_info);
            } else {
                return ajax_error('没数据');
            }

        }
    }


    /**
     **************李火生*******************
     * @param Request $request
     * Notes:订单详情
     **************************************
     */
    public function order_details(Request $request){
        if($request->isPost()){
            $status =$request->only(["status"])["status"];//订单状态
            $parts_order_number =$request->only(["parts_order_number"])["parts_order_number"];//订单编号
            $data =Db::name("order")
                ->where("status",$status)
                ->where("parts_order_number",$parts_order_number)
                ->select();
            if($data[0]["order_type"] ==1){
                    $order_type ="直邮";
                    $name =$data[0]["harvester"];
                    $phone_num =$data[0]["harvest_phone_num"];
                    $address =$data[0]["harvester_address"];
            }else if($data[0]["order_type"] ==2){
                $order_type ="自提";
                $name =$data[0]["harvester"];
                $phone_num =$data[0]["harvest_phone_num"];
                $address =$data[0]["harvester_address"];
            }else if($data[0]["order_type"] ==3){
                $order_type ="存茶";
                $name =$data[0]["harvester"];
                $phone_num =$data[0]["harvest_phone_num"];
                $address =$data[0]["harvester_address"];
            }
            if(!empty($data)){
                $datas =[
                    "data"=>$data,
                    "status"=>$status,
                    "parts_order_number"=>$parts_order_number,
                    "create_time"=>$data[0]["order_create_time"],
                    "pay_time"=>$data[0]["pay_time"],
                    "order_type_name"=>$order_type,
                    "order_type"=>$data[0]["order_type"],
                    "name"=>$name,
                    "phone_num"=>$phone_num,
                    "address"=>$address
                ];
                return ajax_success("详情数据返回成功",$datas);
            }else{
                return ajax_error("没有数据返回",["status"=>0]);
            }

        }
    }


    /**
     **************李火生*******************
     * @param Request $request
     * Notes:订单状态修改（买家确认收货）
     **************************************
     * @param Request $request
     */
    public function ios_api_order_collect_goods(Request $request){
        if($request->isPost()){
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $member_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            if(empty($member_id)){
                exit(json_encode(array("status" => 2, "info" => "请重新登录","data"=>["status"=>0])));
            }
            $parts_order_number =$request->only("parts_order_number")["parts_order_number"];//订单编号
            if(!empty($parts_order_number)){
                $res =Db::name("order")
                    ->where("member_id",$member_id)
                    ->where("parts_order_number",$parts_order_number)
                    ->select();
                if(!empty($res)){
                    foreach($res as $k=>$v){
                        $data =[
                            "status"=>7
                        ];
                        $bool =Db::name("order")->where("id",$v["id"])->update($data);
                    }
                    if($bool){
                        return ajax_success("确认收货成功",["status"=>1]);
                    }else{
                        return ajax_error("确认收货失败",["status"=>0]);
                    }
                }
            }else{
                return ajax_error("所传参数不能为空",["status"=>0]);
            }
        }
    }


    /**
     **************李火生*******************
     * @param Request $request
     * Notes:买家删除订单接口(ajax)
     **************************************
     * @param Request $request
     */
    public function  ios_api_order_del(Request $request){
        if($request->isPost()){
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $member_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            if(empty($member_id)){
                exit(json_encode(array("status" => 2, "info" => "请重新登录","data"=>["status"=>0])));
            }
            $parts_order_number =$request->only("parts_order_number")["parts_order_number"];//订单编号
            if(!empty($parts_order_number)){
                $res =Db::name("order")
                    ->where("parts_order_number",$parts_order_number)
                    ->where("member_id",$member_id)
                    ->select();
                if(!empty($res)){
                    foreach($res as $k=>$v){
                        $bool =Db::name("order")
                            ->where("id",$v["id"])
                            ->delete();
                    }
                    if($bool){
                        return ajax_success("删除成功",["status"=>1]);
                    }else{
                        return ajax_error("删除失败",["status"=>0]);
                    }
                }
            }else{
                return ajax_error("所传参数不能为空",["status"=>0]);
            }
        }
    }

    /**
     **************李火生*******************
     * @param Request $request
     * Notes:订单状态修改（未付款买家取消订单）
     **************************************
     * @param Request $request
     */
    public function ios_api_order_no_pay_cancel(Request $request){
        if($request->isPost()){
            $open_id =$request->only("open_id")["open_id"]; //用户open_ID
            $member_id =Db::name("member")->where("member_openid",$open_id)->value("member_id");
            if(empty($member_id)){
                exit(json_encode(array("status" => 2, "info" => "请重新登录","data"=>["status"=>0])));
            }
            $cancel_order_description =$request->only('cancel_order_description')["cancel_order_description"];//取消原因
            $parts_order_number =$request->only("parts_order_number")["parts_order_number"];//订单编号
            if(!empty($parts_order_number)){
                $res =Db::name("order")
                    ->where("parts_order_number",$parts_order_number)
                    ->where("member_id",$member_id)
                    ->select();
                if(!empty($res)){
                    foreach($res as $k=>$v){
                        $data =[
                            "status"=>9,
                            "coupon_id"=>0,
                            "cancel_order_description"=>$cancel_order_description
                        ];
                        $bool =Db::name("order")->where("id",$v["id"])->update($data);
                    }
                    if($bool){
                        exit(json_encode(array("status" => 1, "info" => "取消成功","data"=>["status"=>1])));
                    }else{
                        exit(json_encode(array("status" => 0, "info" => "取消失败","data"=>["status"=>0])));
                    }
                }
            }else{
                return ajax_error("所传参数不能为空",["status"=>0]);
            }
        }
    }


    /**
     **************李火生*******************
     * @param Request $request
     * Notes:小程序活动支付成功回来修改状态
     **************************************
     */

    public function notify(){
        include EXTEND_PATH."WxpayAPI/lib/WxPay.Data.php";
        include EXTEND_PATH."WxpayAPI/lib/WxPay.Notify.php";
        include EXTEND_PATH."WxpayAPI/lib/WxPay.Api.php";
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml_data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xml_data), true);
        if($val["result_code"] == "SUCCESS" ){
//            file_put_contents(EXTEND_PATH."data.txt",$val);
            $res = Db::name("activity_order")
                ->where("parts_order_number",$val["out_trade_no"])
                ->update(["status"=>2]);
            $activity = Db::name("activity_order")->where("parts_order_number",$val["out_trade_no"])->find();
            $day_array = Db::name("teahost")->where("id",$activity['teahost_id'])->find();
            $new_array = explode(",",$day_array["day_array"]);
            $index = $activity['index'];
            $new_array[$index] = $new_array[$index]-1;
            $intest = implode(",",$new_array);
            $peoples = $day_array["peoples"] + 1;
            $rest_order = Db::name("teahost")->where("id",$activity['teahost_id'])->update(["day_array"=>$intest,"peoples"=>$peoples]);
            
            if($res){
                //做消费记录
                $information =Db::name("activity_order")
                    ->field("member_openid,cost_moneny,activity_name")
                    ->where("parts_order_number",$val["out_trade_no"])
                    ->find();
                $user_information =Db::name("member")
                    ->field("member_id,member_wallet,member_recharge_money")
                    ->where("member_openid",$information["member_openid"])
                    ->find();
                $now_money =$user_information["member_wallet"]+$user_information["member_recharge_money"];
                $datas=[
                    "user_id"=>$user_information["member_id"],//用户ID
                    "wallet_operation"=> $information["cost_moneny"],//消费金额
                    "wallet_type"=>-1,//消费操作(1入，-1出)
                    "operation_time"=>date("Y-m-d H:i:s"),//操作时间
                    "operation_linux_time"=>time(), //操作时间
                    "wallet_remarks"=>"订单号：".$val["out_trade_no"]."，微信消费".$information["cost_moneny"]."元",//消费备注
                    "wallet_img"=>" ",//图标
                    "title"=>$information["activity_name"],//标题（消费内容）
                    "order_nums"=>$val["out_trade_no"],//订单编号
                    "pay_type"=>"小程序", //支付方式/
                    "wallet_balance"=>$now_money,//此刻钱包余额
                ];
                Db::name("wallet")->insert($datas); //存入消费记录表
                echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            }else{
                return ajax_error("失败",$val["out_trade_no"]);
            }
        }
    }

    /**
     **************李火生*******************
     * @param Request $request
     * Notes:小程序订单支付成功回来修改状态
     **************************************
     */
    public function order_notify(){
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml_data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xml_data), true);
        if($val["result_code"] == "SUCCESS" ){
             file_put_contents(EXTEND_PATH."data.txt",$val);
            $res = Db::name("order")
                ->where("parts_order_number",$val["out_trade_no"])
                ->update(["status"=>2,"pay_time"=>time(),"si_pay_type"=>2]);

                $host_rest = Db::name("house_order")
                ->where("parts_order_number",$val["out_trade_no"])
                ->update(["status"=>2,"pay_time"=>time(),"si_pay_type"=>2]);
            if($res){
                //做消费记录
                $information =Db::name("order")->field("member_id,order_real_pay,parts_goods_name")->where("parts_order_number",$val["out_trade_no"])->find();
                $user_information =Db::name("member")
                    ->field("member_wallet,member_recharge_money")
                    ->where("member_id",$information["member_id"])
                    ->find();
                $now_money =$user_information["member_wallet"]+$user_information["member_recharge_money"];
                $datas=[
                    "user_id"=>$information["member_id"],//用户ID
                    "wallet_operation"=> $information["order_real_pay"],//消费金额
                    "wallet_type"=>-1,//消费操作(1入，-1出)
                    "operation_time"=>date("Y-m-d H:i:s"),//操作时间
                    "operation_linux_time"=>time(), //操作时间
                    "wallet_remarks"=>"订单号：".$val["out_trade_no"]."，微信消费".$information["order_real_pay"]."元",//消费备注
                    "wallet_img"=>" ",//图标
                    "title"=>$information["parts_goods_name"],//标题（消费内容）
                    "order_nums"=>$val["out_trade_no"],//订单编号
                    "pay_type"=>"小程序", //支付方式/
                    "wallet_balance"=>$now_money,//此刻钱包余额
                ];
                Db::name("wallet")->insert($datas); //存入消费记录表



                $all_money = db("order")->where("parts_order_number",$val["out_trade_no"])->value("order_real_pay");//实际支付的金额
                $member_id = db("order")->where("parts_order_number",$val["out_trade_no"])->value("member_id");//会员id
                $coin = db("recommend_integral")->where("id",1)->value("coin"); //消费满多少送积分金额条件
                $integral = db("recommend_integral")->where("id",1)->value("consume_integral"); //消费满多少送多少积分
                //消费满多少金额赠送多少积分
                if( $all_money > $coin){
                    $rest = db("member")->where("member_id",$member_id)->setInc('member_integral_wallet',$integral);//满足条件则增加积分
                    $many = db("member")->where("member_id",$member_id)->value("member_integral_wallet");//获取所有积分
                    //插入积分记录
                    $integral_data = [
                        "member_id" => $member_id,
                        "integral_operation" => $integral,//获得积分
                        "integral_balance" => $many,//积分余额
                        "integral_type" => 1, //积分类型（1获得，-1消费）
                        "operation_time" => date("Y-m-d H:i:s"), //操作时间
                        "integral_remarks" => "消费满" . $coin . "送".$integral."积分",
                    ];
                    Db::name("integral")->insert($integral_data);
                }
                echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            }else{
                return ajax_error("失败");
            }
        }
    }

    /**
     **************李火生*******************
     * @param Request $request
     * Notes:小程序充值支付成功回来处理数据
     **************************************
     */
    public function recharge_notify(){
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml_data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xml_data), true);
        if($val["result_code"] == "SUCCESS" ){
//            file_put_contents(EXTEND_PATH."data.txt",$val);
            $data['status'] = 1;
            $data['pay_time'] = time();
            $data['pay_type_name'] = "微信";
            $condition['recharge_order_number'] = $val["out_trade_no"];
            $res = Db::name("recharge_record")
                ->where($condition)
                ->update($data);
            if($res > 0){
                //进行钱包消费记录
                $parts =Db::name("recharge_record")
                ->field("recharge_money,user_id")
                    ->where($condition)
                    ->find();
                $title ="余额充值";
                $money =$parts["recharge_money"];//金额
                $recharge_record_data = Db::name("recharge_record")
                    ->where("recharge_order_number",$val["out_trade_no"])
                    ->find();
                //充值送积分
                $list = Db::name("recharge_full_setting")
                    ->field("recharge_setting_send_integral,recharge_setting_full_money")
                    ->select();
                $lists =0;
                foreach($list as $k=>$v){
                    if($v["recharge_setting_full_money"] ==$recharge_record_data["recharge_money"]){
                        $lists =$v["recharge_setting_send_integral"];
                    }
                }
                //如果达到充值送积分条件
                if(!empty($lists)){
                    $recharge_data =[
                        "user_id" =>$parts["user_id"],//用户id
                        "operation_time"=>date("Y-m-d H:i:s"),//操作时间
                        "operation_linux_time"=>time(),//操作时间
                        "operation_type"=>1,//充值为1，提现为负一
                        "pay_type_content"=>$recharge_record_data["pay_type_name"],//支付方式
                        "money_status"=>1 , //到款状态（1到账，2未到款）
                        "img_url"=>" ", //对应的图片链接
                        "operation_amount" =>$recharge_record_data["recharge_money"], //操作金额
                        "recharge_describe" =>"充值".$recharge_record_data["recharge_money"]."元,送了".$lists."积分",//描述
                        "status"=>1,
                        "is_able_withdrawal"=>1
                    ];
                    Db::name("recharge_reflect")->insert($recharge_data);//插到记录
                    //充值剩下的余额
                    $user_wallet =Db::name("member")
                        ->field("member_recharge_money")
                        ->where("member_id",$recharge_record_data["user_id"])
                        ->find();
                    //更新充值的余额
                    Db::name("member")->where("member_id",$recharge_record_data["user_id"])
                        ->update(["member_recharge_money"=>$user_wallet["member_recharge_money"]+$recharge_record_data["recharge_money"]]);
                    //插入积分记录
                         Db::name("member")
                        ->where("member_id",$recharge_record_data["user_id"])
                        ->setInc('member_integral_wallet',$lists);//满足条件则增加积分
                    $integral_res = Db::name("member")
                        ->where("member_id",$recharge_record_data["user_id"])
                        ->value("member_integral_wallet");//获取所有积分
                    $integral_data = [
                        "member_id" => $recharge_record_data["user_id"],
                        "integral_operation" => $lists,//获得积分
                        "integral_balance" => $integral_res,//积分余额
                        "integral_type" => 1, //积分类型（1获得，-1消费）
                        "operation_time" => date("Y-m-d H:i:s"), //操作时间
                        "integral_remarks" => "充值满" . $money . "送".$lists."积分",
                    ];
                    Db::name("integral")->insert($integral_data);
                }
                $new_wallet =Db::name("member")
                    ->where("member_id",$recharge_record_data["user_id"])
                    ->value("member_recharge_money");
                $datas=[
                    "user_id"=>$parts["user_id"],//用户ID
                    "wallet_operation"=> $money,//消费金额
                    "wallet_type"=>1,//消费操作(1入，-1出)
                    "operation_time"=>date("Y-m-d H:i:s"),//操作时间
                    "operation_linux_time"=>time(), //操作时间
                    "wallet_remarks"=>"订单号：".$val["out_trade_no"]."，充值，余额增加".$money."元,送".$lists."积分",//消费备注
                    "wallet_img"=>" ",//图标
                    "title"=>$title,//标题（消费内容）
                    "order_nums"=>$val["out_trade_no"],//订单编号
                    "pay_type"=>"小程序", //支付方式/
                    "wallet_balance"=>$new_wallet,//此刻钱包余额
                ];
                Db::name("wallet")->insert($datas); //存入消费记录表
                echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            }else{
                return "fail";
            }
        }
    }



       /**
     **************李火生*******************
     * @param Request $request
     * Notes:打赏订单支付成功回来修改状态
     **************************************
     */
    public function reward_notify(){
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml_data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xml_data), true);
        if($val["result_code"] == "SUCCESS" ){
//             file_put_contents(EXTEND_PATH."data.txt",$val);
            $res = Db::name("reward")
                ->where("order_number",$val["out_trade_no"])
                ->update(["status"=>2,"pay_time"=>time()]);
            if($res){
                //做消费记录
                $information =Db::name("reward")->field("money,order_number,crowd_name,member_id")->where("order_number",$val["out_trade_no"])->find();
                $member_wallet =Db::name("member")
                    ->where("member_id",$information["member_id"])
                    ->value('member_wallet');
                $datas= [
                    "user_id"=>$information["member_id"],//用户ID
                    "wallet_operation"=> $information["money"],//消费金额
                    "wallet_type"=>-1,//消费操作(1入，-1出)
                    "operation_time"=> date("Y-m-d H:i:s"),//操作时间
                    "operation_linux_time"=>time(), //操作时间
                    "wallet_remarks"=>"订单号：".$val["out_trade_no"]."众筹打赏".$information["money"]."元",//消费备注
                    "wallet_img"=>" ",//图标
                    "title"=>$information["crowd_name"],//标题（消费内容）
                    "order_nums"=>$val["out_trade_no"],//订单编号
                    "pay_type"=>"小程序", //支付方式/
                    "wallet_balance"=>$member_wallet,//此刻钱包余额
                ];
                Db::name("wallet")->insert($datas); //存入消费记录表
                echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            }else{
                return ajax_error("失败");
            }
        }
    }



    /**
     **************郭杨*******************
     * @param Request $request
     * Notes:默认存茶收货地址
     **************************************
     */
    public function tacitly_approve(Request $request){
        if($request->isPost()){
            $data =Db::name("store_house")
                ->where("label",1)
                ->find();
            if(!empty($data)){
                $data["unit"] = explode(",",$data["unit"]);
                $data["cost"] = explode(",",$data["cost"]);
                return ajax_success("返回成功",$data);
            }else{
                return ajax_error("没有默认收货地址");
            }
        }
    }


        /**
     **************郭杨*******************
     * @param Request $request
     * Notes:默认存茶收货地址列表
     **************************************
     */
    public function tacitly_list(Request $request){
        if($request->isPost()){
            $data =Db::name("store_house")
                ->select();

            if(!empty($data)){
                foreach($data as $key => $value){
                    $data[$key]["unit"] = explode(",",$data[$key]["unit"]);
                    $data[$key]["cost"] = explode(",",$data[$key]["cost"]);
                }
                return ajax_success("返回成功",$data);
            }else{
                return ajax_error("没有默认收货地址");
            }
        }
    }


    /**
     **************郭杨*******************
     * @param Request $request
     * Notes:运费
     **************************************
     */
    public function transportation(Request $request){
        if($request->isPost()){
            $goods_id = $request->only("goods_id")["goods_id"];//商品id
            $are =$request->only("are")["are"];//地区
            $standard =$request->only("goods_standard_id")["goods_standard_id"];//规格id
            $res = array();
            if(!empty($goods_id)){
                foreach($goods_id as $key => $value){
                    $goods = db("goods")->where("id",$value)->find();
                    if($goods["goods_standard"] == 1){
                        $goods["monomer"] = db("special")->where("id",$standard[$key])->value("offer");
                    }
                    $data["goods_id"] = $value;
                    if($goods['goods_franking'] != 0){
                        $data["collect"] = $goods["goods_franking"]; //统一邮费
                        $data["markup"] = 0; //统一邮费
                    }else{
                        $templet_name = explode(",",$goods["templet_name"]);
                        $templet_id = explode(",",$goods["templet_id"]);
                        $monomer = $goods["monomer"];
                        $tempid = array_search($monomer,$templet_name);
                        $express_id = $templet_id[$tempid];
                        $rest = db("express")->where("id",$express_id)->find();
                        if(!empty($rest)){
                            $are_block = explode(",",$rest["are"]);
                            if(in_array($are,$are_block)){
                                $data["collect"] = $rest["price"];//首费
                                $data["markup"] = $rest["markup"];//续费
                            } else {
                                $data["collect"] = $rest["price_two"];//首费
                                $data["markup"] = $rest["markup_two"];//续费
                            }
                        } else {
                            return ajax_error("没有运费模板");
                        }
                    }
                    array_push($res,$data);                    
                }
                return ajax_success("返回成功",$res);
            } else {
                return ajax_error("没有运费模板");
            }
        }

    }


}