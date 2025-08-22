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
                $lockTime = getOption('cart_lock_period', 5) * 60;
                $expiresAt = time() - $lockTime;
                $items = CabinLock::where('created_at', '<=', date('Y-m-d H:i:s', $expiresAt))->get();
                if($items) {
                    $items->each(function($item, $key) {
                        if($item->mapping_id) {
                            DB::table('schedule_cabin_mappings')->where('id', $item->mapping_id)->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING]);
                        } else {
                            DB::table('schedule_cabin_mappings')->where(['schedule_id', $item->trip_id, 'cabin_id' => $item->cabin_id])->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING]);
                        }
                        $item->delete();
                    });
                }
            },3);
        } catch( \Exception $e ) {
            $this->info($e->getMessage());
        }
    }
}
