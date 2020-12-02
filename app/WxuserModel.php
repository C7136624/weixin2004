<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WxuserModel extends Model
{
    //
    protected $table = 'p_wx_users';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $guarded = [];   //黑名单  create只需要开启
}
