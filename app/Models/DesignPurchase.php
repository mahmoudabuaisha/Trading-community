<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignPurchase extends Model
{
    protected $fillable = ['design_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }
}
