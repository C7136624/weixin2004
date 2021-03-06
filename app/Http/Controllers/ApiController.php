<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Goods;
use App\CartModel;
use App\XcxWxUserModel;
use App\XcxUserModel;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    function test()
    {
        $goods_info = [
            'goods_id' => 1313,
            'goods_name' => 'iphone 12',
            'price' => 7299
        ];
        echo json_encode($goods_info);
    }

//    public function onlogin(Request $request){
////        echo  111;die;
//        //接收code
//        $code = $request->get('code');
////        dd($code);
//        //使用code
//        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.env('WX_XCX_APPID').'&secret='.env('WX_XCX_SECRET').'&js_code='.$code.'&grant_type=authorization_code';
//        $data = json_decode(file_get_contents($url),true);
//
//        //自定义登录状态
//        if(isset($data['errcode']))         //有错误
//        {
//            $response = [
//                'errno' => 0,
//                'msg' => '登录失败'
//            ];
//        }else{
//            $token = sha1($data['openid'].$data['session_key'].mt_rand(0,999999));
//            //保存token
//            $redis_key = 'xcx_token:'.$token;
//            Redis::set($redis_key,time());
//            //设置过期时间
//            Redis::expire($redis_key,7200);
//
//            DB::table('xcx_user')->insert($data);
//            $response = [
//                'errno' => 0,
//                'msg' => 'ok',
//                'data' => [
//                    'token' => $token
//                ]
//
//            ];
//        }
//        return $response;
//    }

    /**
     * 小程序首页登录
     * @param Request $request
     */
    public function homeLogin(Request $request)
    {
        //接收code
        $code = $request->get('code');
//        return $code;
        //使用code
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . env('WX_XCX_APPID') . '&secret=' . env('WX_XCX_SECRET') . '&js_code=' . $code . '&grant_type=authorization_code';

        $data = json_decode(file_get_contents($url), true);
//        return $data;
        //自定义登录状态
        if (isset($data['errcode']))     //有错误
        {
            $response = [
                'errno' => 50001,
                'msg' => '登录失败',
            ];

        } else {              //成功
            $openid = $data['openid'];          //用户OpenID
            //判断新用户 老用户
            $u = XcxUserModel::where(['openid' => $openid])->first();
            if ($u) {
                // TODO 老用户
                $u_id = $u->u_id;
                //更新用户信息

            } else {
                // TODO 新用户
                $u_info = [
                    'openid' => $openid,
                    'add_time' => time(),
                    'type' => 3        //小程序
                ];

                $u_id = XcxUserModel::insertGetId($u_info);
            }

            //生成token
            $token = sha1($data['openid'] . $data['session_key'] . mt_rand(0, 999999));
            //保存token
            $redis_login_hash = 'h:xcx:login:' . $token;

            $login_info = [
                'u_id' => $u_id,
                'user_name' => "",
                'login_time' => date('Y-m-d H:i:s'),
                'login_ip' => $request->getClientIp(),
                'token' => $token,
                'openid' => $openid
            ];

            //保存登录信息
            Redis::hMset($redis_login_hash, $login_info);
            // 设置过期时间
            Redis::expire($redis_login_hash, 7200);

            $response = [
                'errno' => 0,
                'msg' => 'ok',
                'data' => [
                    'token' => $token
                ]
            ];
        }

        return $response;

    }

    public function goods(Request $request)
    {
        $pagesize = $request->get('ps');
        $data = Goods::select('goods_id', 'goods_name', 'shop_price', 'goods_img')->limit(10)->paginate($pagesize);
//        dd($goods_id);
        return $data;

    }

    public function goodsinfo()
    {
        $goods_id = request()->goods_id;
        $info = Goods::where(['goods_id' => $goods_id])->get();
        $info['goods_imgs'] = [
            '//img13.360buyimg.com/n1/s450x450_jfs/t1/138694/17/10615/68848/5f861345E105290e8/27a4a550d6b41eee.jpg',
            '//m.360buyimg.com/mobilecms/s1125x1125_jfs/t1/126256/19/14768/67348/5f861348Eede929c4/2aa8ce70add5f3b6.jpg!q70.dpg.webp',
            '//m.360buyimg.com/mobilecms/s1125x1125_jfs/t1/135503/9/12217/99639/5f86134bE9144ce5f/66534f8695095186.jpg!q70.dpg.webp',
            '//m.360buyimg.com/mobilecms/s1125x1125_jfs/t1/130775/39/12262/72360/5f86134eEec143f90/eb79c28e8465c119.jpg!q70.dpg.webp'
        ];
        $info['goods_desc_imgs'] = [
            '//img13.360buyimg.com/cms/jfs/t1/120836/20/14832/819799/5f8604f8Eb381a921/5be9108f28a06b69.jpg'
        ];
        return $info;
    }

    /**
     * 加入购物车
     */
    public function add_cart()
    {
        $goods_id = request()->post('goods_id');
//        dd($goods_id);
        $u_id = $_SERVER['u_id'];
        dd($u_id);
        $price = Goods::find($goods_id)->shop_price;

//        dd($price);

        //将商品存储购物车表 或 Redis
        $info = [
            'u_id' => $u_id,
            'goods_id' => $goods_id,
            'goods_number' => 1,
            'shop_price' => $price
        ];

        $id = CartModel::insertGetId($info);
        if ($id) {
            $response = [
                'errno' => 0,
                'msg' => 'ok'
            ];
        } else {
            $response = [
                'errno' => 50002,
                'msg' => '加入购物车失败'
            ];
        }

        return $response;
    }

    /**
     * 小程序 个人中心登录
     * @param Request $request
     * @return array
     */
    public function userlogin(Request $request)
    {
        //接收code
        //$code = $request->get('code');
        $token = $request->get('token');
//        dd($token);
        //获取用户信息
        $userinfo = json_decode(file_get_contents("php://input"), true);

        $redis_login_hash = 'h:xcx:login:' . $token;
        $openid = Redis::hget($redis_login_hash, 'openid');          //用户OpenID
//        dd($openid);
        $u0 = XcxWxUserModel::where(['openid' => $openid])->first();
        if (empty($u0)) {
            $u_info = [
                'openid' => $openid,
                'nickname' => $userinfo['u']['nickName'],
                'sex' => $userinfo['u']['gender'],
                'language' => $userinfo['u']['language'],
                'city' => $userinfo['u']['city'],
                'province' => $userinfo['u']['province'],
                'country' => $userinfo['u']['country'],
                'headimgurl' => $userinfo['u']['avatarUrl'],
                'update_time' => 0
            ];
            XcxWxUserModel::insert($u_info);
        } elseif ($u0->update_time == 0) {     // 未更新过资料
            //因为用户已经在首页登录过 所以只需更新用户信息表
            $u_info = [
                'nickname' => $userinfo['u']['nickName'],
                'sex' => $userinfo['u']['gender'],
                'language' => $userinfo['u']['language'],
                'city' => $userinfo['u']['city'],
                'province' => $userinfo['u']['province'],
                'country' => $userinfo['u']['country'],
                'headimgurl' => $userinfo['u']['avatarUrl'],
                'update_time' => time()
            ];
            XcxWxUserModel::where(['openid' => $openid])->update($u_info);
        }


        $response = [
            'errno' => 0,
            'msg' => 'ok',
        ];

        return $response;

    }
}









