<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::any("index","TestController@index");
Route::get("getaccess","TestController@getAccessToken");
Route::any("/test","TestController@wxEvent");
Route::any("/menu","TestController@Menu");
Route::any("/weather","TestController@Weater");
Route::any("/weather","TestController@weather");
Route::any("/fanyi","TestController@fanyi");

Route::any("/clickHandler","TestController@clickHandler");


//小程序
Route::any("/test","ApiController@test");
Route::any("/onlogin","ApiController@homeLogin");
Route::get("/goods","ApiController@goods");
Route::get("/goodsinfo","ApiController@goodsinfo");
Route::any("/user-login","ApiController@userlogin");
Route::get("/add_cart","ApiController@add_cart")->middleware('check.token');


