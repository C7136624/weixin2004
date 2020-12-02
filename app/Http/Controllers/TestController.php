<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;
use App\WxuserModel;
use App\WxMediaModel;

class TestController extends Controller
{

    protected $str_obj;

    //接入微信
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
            echo "";
            return true;
        }else{
            echo "";
            return false;
        }


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


    /*
      * 接受微信推送事件
     */
    public function wxEvent()
    {
        //接受数据
        $xml_str = file_get_contents("php://input");

        //记录日志
        file_put_contents("wx_event.log", $xml_str);

        $data = simplexml_load_string($xml_str);
//            print_r($data);die;
        $this->str_obj = $data;
        //文本回复
        if(strtolower($data->MsgType) == "text") {
            if (preg_match("/([\x81-\xfe][\x40-\xfe])/", strtolower($data->Content), $match)) {
                $text = strtolower($data->Content);
                $content = $this->fanyi($text);
                echo $this->response($content['newslist']['0']['pinyin']);
                die;
            }
        }
    }

    //获取天气
    public function weather(){
        $url = "https://devapi.qweather.com/v7/weather/now?location=101010100&key=3b20b6ae1ba348c4afdc9545926f1694&gzip=n";
//        dd($url);
        $red = $this->curl($url);
        $red = json_decode($red,true);
        $rea = $red['now'];
        $data = "时间:".$rea['obsTime']."天气:".$rea['text']."地区:北京"."风向:".$rea['windDir'];
        return    $data;
    }


    //处理图片消息
    protected function  imageHandler(){
        //下载素材
        $token = $this->getAccessToken();
        $media_id = $this->xml_obj->MediaId;
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$token.'&media_id='.$media_id;
        $img = file_get_contents($url);
        $media_path = 'upload/'.$this->xml_obj->MediaId.'.jpg';
        $res = file_put_contents($media_path,$img);
        if ($res){
            //TODO 保存成功
        }else{
            //TODO 保存失败
        }
        //入库
        $info = [
            'media_id'  => $media_id,
            'openid'   => $this->xml_obj->FromUserName,
            'type'  => $this->xml_obj->MsgType,
            'msg_id'  => $this->xml_obj->MsgId,
            'created_at'  => $this->xml_obj->CreateTime,
            'media_path'    => $media_path
        ];
        WxMediaModel::insertGetId($info);
    }

    //封装回复方法
    public function response($content){
        $fromUserName=$this->str_obj->ToUserName;
        $toUserName=$this->str_obj->FromUserName;
        $time=time();
        $msgType="text";
        $xml="<xml>
                   <ToUserName><![CDATA[%s]]></ToUserName>
                   <FromUserName><![CDATA[%s]]></FromUserName>
                   <CreateTime>%s</CreateTime>
                   <MsgType><![CDATA[%s]]></MsgType>
                   <Content><![CDATA[%s]]></Content>
                   </xml>";//发送//来自//时间//类型//内容
        return sprintf($xml,$toUserName,$fromUserName,$time,$msgType,$content);
    }

    //自定义菜单
    public function Menu(){
        $menu = [
            'button' => [
                [
                    'type' => 'view',
                    'name' => '商城',
                    'url' => 'http://jd2004.csazam.top/'
                ],
                [
                    'type' => 'click',
                    'name' => '天气',
                    'key' => 'weather'
                ],
                [
                    'type' => 'click',
                    'name' => '签到',
                    'key' => 'wx_key_0002'
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

    //关注用户信息入库
    public function  subscribe(){
        $ToUserName=$this->xml_obj->FromUserName;       // openid
        $FromUserName=$this->xml_obj->ToUserName;
        //检查用户是否存在
        $u = WxuserModel::where(['openid'=>$ToUserName])->first();
        if($u){
            // TODO 用户存在
            $content = "欢迎回来 现在时间是：" . date("Y-m-d H:i:s");
        }else{
            //获取用户信息，并入库
            $user_info = $this->getWxUserInfo();

            //入库
            unset($user_info['subscribe']);
            unset($user_info['remark']);
            unset($user_info['groupid']);
            unset($user_info['substagid_listcribe']);
            unset($user_info['qr_scene']);
            unset($user_info['qr_scene_str']);
            unset($user_info['tagid_list']);

            WxuserModel::insertGetId($user_info);
            $content = "欢迎关注 现在时间是：" . date("Y-m-d H:i:s");
        }
        echo   $this->infocodl($content);
    }


    //获取微信用户信息
    public function getWxUserInfo()
    {

        $token = $this->getAccessToken();
        $openid = $this->xml_obj->FromUserName;
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$token.'&openid='.$openid.'&lang=zh_CN';

        //请求接口
        $client = new Client();
        $response = $client->request('GET',$url,[
            'verify'    => false
        ]);
        return  json_decode($response->getBody(),true);
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

    //翻译接口
    public function fanyi($text){
        $url = 'http://api.tianapi.com/txapi/pinyin/index?key=727f365f887584d5a4d14c685b2b4e5e&text='.$text;
        $get = file_get_contents($url);
        $json = json_decode($get,true);
        return $json;
    }



}
