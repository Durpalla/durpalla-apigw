<?php

namespace Modules\Cart\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['token', 'customer_id'];
    protected $hidden = ['customer_id'];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function scopeFilter($query, $request)
    {
        if (auth()->check()) {
            $query->where('customer_id', auth('api')->id());
        } elseif ($guestId = $request->input('guest_unique_id')) {
            $query->where('token', decrypt($guestId));
        }

        return $query;
    }

    public function format(): array
    {
        return [
            'token' => $this->token,
            'total_qty' => $this->items->sum('qty'),
            'total_amount' => $this->items->map(function ($item, $k) {
                if ($item->price != $item->product->price) {
                    $item->product->update(['price' => $item->product->price]);
                }
                return [
                    'amount' => $item->qty * $item->product->price
                ];
            })->sum('amount'),
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->item_id,
                    'qty' => $item->qty,
                    'price' => $item->product->price,
                    'sub_total' => $item->qty * $item->product->price,
                    'product' => $item->product->only(['id', 'title', 'price', 'thumbnail'])
                ];
            })
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth('customer')->check()) {
                $model->customer_id = auth('customer')->id();
            }
        });

        static::updating(function ($model) {
            if (auth('customer')->check()) {
                $model->customer_id = auth('customer')->id();
            }
        });

        static::deleting(function ($model) {
            $model->items()->delete();
        });
    }
}
