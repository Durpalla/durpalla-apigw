<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserRoleAssignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $role;
    private $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, $role_name)
    {
        $this->user = $user;
        $this->role = Role::findByName($role_name);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->user->assignRole($this->role);
    }
}
