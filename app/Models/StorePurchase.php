<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorePurchase extends Model
{
    protected $fillable = ['user_id', 'store_item_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }
}
