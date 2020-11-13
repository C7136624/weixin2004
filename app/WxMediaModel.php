<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WxMediaModel extends Model
{
    protected $table = 'media';
    public $timestamps = false;
    protected $primaryKey = 'm_id';
    protected $guarded = [];   //黑名单  create只需要开启
}
