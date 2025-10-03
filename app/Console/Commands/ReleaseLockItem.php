<?php

namespace App\Console\Commands;

use App\Constants\AppConst;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\CabinLock;

class ReleaseLockItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'release:lock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Releasing all lock items which has expired';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try{
            DB::transaction(function() {
                $lockTime = getOption('cart.locking_period', 5);
                $items = CabinLock::where('created_at', '<=', now()->subMinutes($lockTime)->toDateTime())->get();
                if($items) {
                    $items->each(function($item, $key) {
                        $this->info("Releasing lock item {$item->id}");
                        $item->mapping->update(['is_locked' => 0]);
                        if($item->mapping->update(['is_locked' => 0])) {
                            $item->delete();
                        } else {
                            DB::table('schedule_cabin_mappings')->where('id', $item->mapping_id)->update(['is_locked' => 0]);
                            $item->delete();
                        }
                        $this->info("Released lock item {$item->id}");
                    });
                }
            },3);
        } catch( \Exception $e ) {
            $this->info($e->getMessage());
        }
    }
}
