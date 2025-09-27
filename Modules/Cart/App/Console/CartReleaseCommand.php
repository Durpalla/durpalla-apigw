<?php

namespace Modules\Cart\App\Console;

use Illuminate\Console\Command;
use Modules\Cart\App\Models\CartItem;

class CartReleaseCommand extends Command
{
    protected $signature = 'cart:locking-release';

    protected $description = 'Release Cart lock';

    public function handle(): void
    {
        try {
            $this->info("Release Cart Lock started");
            CartItem::where('created_ab', '<=', now()->subMinutes(config('cart.locking_period', 15)))
                ->cursor()
                ->each(function ($item) {
                    $this->info("Releasing cart item {$item->id}, {$item->created_at}");
                    $item->release();
                });
            $this->info("Release Cart Lock finished");
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }
}
