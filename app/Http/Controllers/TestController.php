<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class TestController extends Controller
{
    public function index()
    {
        $echoStr = request()->get("echostr", "");
        if ($this->checkSignature() && !empty($echoStr)) {
            //至少微信公众平台第一次接入调用走这个
            echo $echoStr;

        }
    }

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = "woainimen";
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }


    }

    //获取access_token
    public function getAccessToken()
    {
        $key = 'wx:access_token';
        $token = Redis::get($key);
        if ($token) {
            echo "有缓存";
        } else {
            echo "五缓存";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . env('WX_APPID') . "&secret=" . env('WX_APPSECRET');
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            $token = $data['access_token'];
            Redis::set($key, $token);
            Redis::expire($key, 5);
        }


        echo "access_token: " . $token;


    }

    public function wxEvent()
    {
        $signature = request()->get("signature");
        $timestamp = request()->get("timestamp");
        $nonce = request()->get("nonce");

        $token = "woainimen";
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            $xml_data = file_get_contents('php://input');
            file_put_contents('wx_event.log', $xml_data);
            $data = simplexml_load_string($xml_data, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($data->MsgType == 'event') {
                if ($data->Event == 'subscribe') {
                    $ToUserName = $data->FromUserName;
                    $FromUserName = $data->ToUserName;
                    $CreateTime = time();
                    $MsgType = "text";
                    $array = ['欢迎关注'];
                    $Content = $array[array_rand($array)];
                    $xml = "<xml>
                                <ToUserName><![CDATA[%s]]></ToUserName>
                                <FromUserName><![CDATA[%s]]></FromUserName>
                                <CreateTime>%s</CreateTime>
                                <MsgType><![CDATA[%s]]></MsgType>
                                <Event><![CDATA[%s]]></Event>
                                <Content><![CDATA[" . $Content . "]]></Content>
                            </xml>";
                    echo sprintf($xml, $ToUserName, $FromUserName, $CreateTime, $MsgType, $Content);
                }

            } else {
                echo "";
            }
        }
    }
}
