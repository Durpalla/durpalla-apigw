<?php

namespace Modules\BroadCast\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\BroadCast\Entities\BroadCast;
use Rajtika\Firebase\Services\Firebase;

class BroadcastTopicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $customers;
    private $broadcast;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BroadCast $broadcast, $customers)
    {
        $this->broadcast = $broadcast;
        $this->customers = $customers;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Firebase::to($this->getIds())
            ->setTitle($this->broadcast->title)
            ->setBody($this->broadcast->message)
            ->setTopic($this->broadcast->topic)
            ->send('data');
    }

    private function getIds()
    {
        return $this->customers->filter(function($customer, $key) {
            return $customer->device_id !== null && strlen($customer->device_id) > 32;
        })->implode('device_id', ',');
    }
}
