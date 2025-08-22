<?php

namespace Modules\Cart\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Entities\Product;
use Ramsey\Uuid\Uuid;

class CartItem extends Model
{
    protected $fillable = [
        'item_id',
        'cart_id',
        'product_id',
        'qty',
        'price',
        'amount',
        'payload',
        'is_locked',
        'locked_id'
    ];

    protected $casts = ['payload' => 'array', 'is_locked' => 'bool', 'qty' => 'integer'];
    protected $hidden = ['id', 'created_at', 'updated_at'];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function  product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function(CartItem $cart) {
            $cart->item_id = Uuid::uuid4();
        });
    }
}
