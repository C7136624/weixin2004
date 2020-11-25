<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Goods extends Model
{
    protected $table = 'p_goods';
    public $timestamps = false;
    protected $primaryKey = 'goods_id';
    protected $guarded = [];   //黑名单  create只需要开启
}

