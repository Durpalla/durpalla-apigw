<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\GhatUpdateToFirebaseJob;
use App\Services\FirebaseService;
use App\Services\GhatService;

class UpdateActiveStoppagesToFirebase extends Command
{
    protected $stoppage;
    protected $firebase;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stoppage:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update active stoppages to firebase and cache items';

    /**
     * Create a new command instance.
     *
     * @param FirebaseService $firebaseService
     * @param GhatService $ghatService
     */
    public function __construct(
        FirebaseService $firebaseService,
        GhatService $ghatService
    )
    {
        parent::__construct();
        $this->firebase = $firebaseService;
        $this->stoppage = $ghatService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        dispatch(new GhatUpdateToFirebaseJob($this->firebase, $this->stoppage));
    }
}
