<?php
namespace App\Repository\Interfaces;

use Illuminate\Support\Collection;
use App\Models\User;

interface ScheduleRepositoryInterface
{
    public function all(): Collection;

    public function getSupervisorJobs(User $supervisor);

    public function create(array $data);

    public function update(array $data, $id);
}
