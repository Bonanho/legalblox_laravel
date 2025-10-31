<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    public $table = 'users';

    // protected $casts = [
    //     'doc' => 'object',
    // ];

    // const STATUS_ACTIVE = 1;
    // const STATUS_PENDING = 0;
    // const STATUS_INACTIVE = -1;
    // const STATUS_LIST = [-1 => "Inativo", 0 => "Pendente", 1 => "Ativo"];

    ####################
    ### RELATIONSHIP ###
    // public function Grid() {
    //     return $this->hasOne(ScreenGrid::class, 'screen_id', 'id');
    // }
    // public function Retail () {
    //     return $this->belongsTo(Retail::class, 'retail_id', 'id');
    // }

    ###############
    ### METHODS ###
}
