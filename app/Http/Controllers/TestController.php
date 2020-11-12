<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;
use App\Wxuser;

class TestController extends Controller
{
    private function index()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = "woainimen";
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }


    }

    //调用接口方法
    public function curl($url,$header="",$content=[]){
        $ch = curl_init(); //初始化CURL句柄
        if(substr($url,0,5)=="https"){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,2);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //字符串类型打印
        curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
        if(!empty($header)){
            curl_setopt ($ch, CURLOPT_HTTPHEADER,$header);
        }
        if($content){
            curl_setopt ($ch, CURLOPT_POST,true);
            curl_setopt ($ch, CURLOPT_POSTFIELDS,$content);
        }
        //执行
        $output = curl_exec($ch);
        if($error=curl_error($ch)){
            die($error);
        }
        //关闭
        curl_close($ch);
        return $output;
    }

    //获取access_token
    public function getAccessToken(){
        $key = 'wx:access_token';
        $token = Redis::get($key);
        if ($token){
            echo "有缓存";
        }else{
            echo "五缓存";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".env('WX_APPID')."&secret=".env('WX_APPSECRET');
            $response = file_get_contents($url);
            $data = json_decode($response,true);
            $token = $data['access_token'];
            Redis::set($key,$token);
            Redis::expire($key,5);
        }


        return $token;


    }


    public function wxEvent()
    {
        $signature = request()->get("signature");
        $timestamp = request()->get("timestamp");
        $nonce = request()->get("nonce");

        $token = "woainimen";
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr);


        if( $tmpStr == $signature ){
            //接受数据
            $xml_data=file_get_contents('php://input');
//            file_put_contents('wx_event.log',$xml_data);
            $data=simplexml_load_string($xml_data);
            if ($data->MsgType=='event'){
                if ($data->Event=='subscribe'){
                    $openid=$data->FromUserName;//接收对方账号
                    $u = Wxuser::where('openid',$openid)->first();
                    if($u){
                        $Content ="欢迎回来";
                        $result = $this->infocodl($data,$Content);
                        return $result;
                    }else{
                        $token = $this->getAccessToken();
                        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$token.'&openid='.$openid.'&lang=zh_CN';
                        $user_file = file_get_contents($url);
                        $user_code = json_decode($user_file,true);
                        $data = [
                            'openid' => $user_code['openid'],
                            'nickname' => $user_code['nickname'],
                            'sex' => $user_code['sex'],
                            'country' => $user_code['country'],
                            'headimgurl' => $user_code['headimgurl'],
                            'subscribe_time' => $user_code['subscribe_time'],
                        ];
                        $res = Wxuser::insertGetId($data);
                        $Content ="关注成功";
                        $result = $this->infocodl($data,$Content);
                        return $result;
                    }
                }
            }
            // 回复天气
            $arr = ['天气','天气。','天气,'];
            if($data->Content==$arr[array_rand($arr)]){
                $content = $this->Weater();
                $result = $this->infocodl($data,$content);
                return $result;
            }
        }else{
            echo "";
        }


    }
    //封装回复方法
    public function infocodl($postarray,$Content)
    {
        $ToUserName=$postarray->FromUserName;//接收对方账号
        $FromUserName=$postarray->ToUserName;//接收开发者微信
//        file_put_contents('log.logs',$ToUserName);

        $time=time();//接受时间
        $text='text';//数据类型
        $ret="<xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[%s]]></MsgType>
                <Content><![CDATA[%s]]></Content>
            </xml>";
        return sprintf($ret,$ToUserName,$FromUserName,$time,$text,$Content);
    }

    public function Menu(){
        $menu = [
            'button' => [
                [
                    'type' => 'view',
                    'name' => '天气',
                    'url' => 'http://2004wlc.wx.comcto.com/weater'
                ],
                [
                    'type' => 'click',
                    'name' => '签到',
                    'key' => 'wx_key_0002'
                ],[
                    'name' => '发图',
                    'sub_button' => [
                        [
                            'type' => 'pic_sysphoto',
                            'name' => '照片',
                            'key' => 'rselfmenu_1_0',
                            "sub_button" => [ ]
                        ],
                        [
                            "type" => "pic_photo_or_album",
                            "name" => "相册",
                            "key" => "rselfmenu_1_1",
                            "sub_button" => [ ]
                        ],
                        [
                            "type" => "pic_weixin",
                            "name" => "微信",
                            "key" => "rselfmenu_1_2",
                            "sub_button" => [ ]
                        ]
                    ]
                ]
            ]

        ];
        $menu = json_encode($menu,JSON_UNESCAPED_UNICODE);
        $access_token = $this->getAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;
        $client = new Client();
        $res_menu = $client->request('POST',$url,[
            'verify'    => false,    //忽略 HTTPS证书 验证
            'body' => $menu
        ]);
        $data = $res_menu->getBody();
        echo $data;

    }

    public function Weater(){
        $key = "577cef88286449eb9a5010194e9a2473";
        $url = "https://devapi.qweather.com/v7/weather/now?location=101010100&key=$key&gzip=n";
        $red = $this->curl($url);
        $red = json_decode($red,true);
        $rea = $red['now'];
        $rea = implode(',',$rea);
        return $rea;
    }

//    public function ccc(){
//        $openid = 'o0c0h6DlowAw8LLOeLn12ln9EPUc';
//        $u = Wxuser::where('openid',$openid)->first();
//        if($u){
//            echo "欢迎再次关注";
//        }else{
//            $token = $this->getAccessToken();
//            $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$token.'&openid='.$openid.'&lang=zh_CN';
//            $user_file = file_get_contents($url);
//            $user_code = json_decode($user_file,true);
//            dd($user_code);
//            $data = [
//                'openid' => $user_code['openid'],
//                'nickname' => $user_code['nickname'],
//                'sex' => $user_code['sex'],
//                'country' => $user_code['country'],
//                'headimgurl' => $user_code['headimgurl'],
//                'subscribe_time' => $user_code['subscribe_time'],
//            ];
//            $res = Wxuser::insertGetId($data);
//            echo "关注成功";
//        }
//
//
//
//    }

}
