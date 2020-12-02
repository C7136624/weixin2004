<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class XcxWxUserModel extends Model
{
    //
    protected $table = 'xcxuser';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $guarded = [];   //黑名单  create只需要开启
}
