<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\FirebaseService;
use App\Services\GhatService;

class GhatUpdateToFirebaseJob
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $firebase;
    private $stoppages;

    /**
     * Create a new job instance.
     *
     * @param FirebaseService $firebase
     * @param GhatService $stoppages
     */
    public function __construct(FirebaseService $firebase, GhatService $stoppages)
    {
        $this->firebase = $firebase;
        $this->stoppages = $stoppages;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->stoppages->getActiveStoppages();
        $this->firebase->set('stoppages')
            ->update($data);
    }
}
