<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;
use App\Wxuser;
use App\WxMediaModel;

class TestController extends Controller
{

    protected $xml_obj;

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
        $xml_str = file_get_contents("php://input");

        // 记录日志
        $log_str = date('Y-m-d H:i:s') . ' >>>>>  ' . $xml_str ." \n\n";
        file_put_contents('wx_event.log',$log_str,FILE_APPEND);

        $obj = simplexml_load_string($xml_str);//将文件转换成 对象
        $this->xml_obj = $obj;

        $msg_type = $obj->MsgType;      //推送事件的消息类型
        switch($msg_type)
        {
            case 'event' :

                if($obj->Event=='subscribe')        // subscribe 扫码关注
                {
                    echo $this->subscribe();
                    exit;
                }elseif($obj->Event=='unsubscribe')     // // unsubscribe 取消关注
                {
                    echo "";
                    exit;
                }elseif($obj->Event=='CLICK')          // 菜单点击事件
                {
                    $this->clickHandler();
                    // TODO
                }elseif($obj->Event=='VIEW')            // 菜单 view点击 事件
                {
                    // TODO
                }


                break;

            case 'text' :           //处理文本信息
                $this->textHandler();
                break;

            case 'image' :          // 处理图片信息
                $this->imageHandler();
                break;

            case 'voice' :          // 语音
                $this->voiceHandler();
                break;
            case 'video' :          // 视频
                $this->videoHandler();
                break;

            default:
                echo 'default';
        }

        echo "";

    }

    //菜单点击事件
    public function clickHandler(){
        $event_key = $this->xml_obj->EventKey;      //菜单 click key
        echo $event_key;

        switch ($event_key){
            case 'checkin' :
                // TODO 签到逻辑
                break;

            case 'weather':
                // TODO 获取天气
                break;

            default:
                // TODO 默认
                break;
        }

        echo "";
    }


    //处理文本消息
    protected function textHandler()
    {
        echo '<pre>';print_r($this->xml_obj);echo '</pre>';
        $data = [
            'open_id'       => $this->xml_obj->FromUserName,
            'msg_type'      => $this->xml_obj->MsgType,
            'msg_id'        => $this->xml_obj->MsgId,
            'create_time'   => $this->xml_obj->CreateTime,
        ];

        //入库
        WxMediaModel::insertGetId($data);

    }

    //处理图片消息
    protected function  imageHandler(){
        //下载素材
        $token = $this->getAccessToken();
        $media_id = $this->xml_obj->MedId;
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$token.'&media_id='.$media_id;
        dd($url);
        $img = file_get_contents($url);
        $media_path = 'upload/'.$this->xml_obj->MedId.'.jpg';
        $res = file_get_contents($media_path,$img);
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
            'create_at'  => $this->xml_obj->CreateTime,
            'media_path'    => $media_path
        ];
        WxMediaModel::insertGetId($info);
    }

    //封装回复方法
    public function infocodl($Content)
    {
        $ToUserName=$this->xml_obj->FromUserName;       // openid
        $FromUserName=$this->xml_obj->ToUserName;
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

    public function  subscribe(){
        $ToUserName=$this->xml_obj->FromUserName;       // openid
        $FromUserName=$this->xml_obj->ToUserName;
        //检查用户是否存在
        $u = Wxuser::where(['openid'=>$ToUserName])->first();
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

            WxUser::insertGetId($user_info);
            $content = "欢迎关注 现在时间是：" . date("Y-m-d H:i:s");
        }
        echo   $this->infocodl($content);
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

}
