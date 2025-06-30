<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\FirebaseService;
use App\Services\OptionService;

class OptionUpdateJob
{
    use Dispatchable;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $firebase;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebase = $firebaseService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $options = new OptionService();
        $mapped = $options->getPublicOptions();
        $this->firebase->set('options')->update($mapped);
    }
}
