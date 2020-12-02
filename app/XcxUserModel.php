<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class XcxUserModel extends Model
{
    //
    protected $table = 'xcx_user';
    public $timestamps = false;
    protected $primaryKey = 'user_id';
    protected $guarded = [];   //黑名单  create只需要开启
}
