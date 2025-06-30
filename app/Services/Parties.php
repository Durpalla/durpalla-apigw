<?php


namespace App\Services;


use App\Jobs\UserRoleAssignJob;
use App\Repository\Interfaces\PartyRepositoryInterface;
use App\Repository\Interfaces\UserRepositoryInterface;

class Parties
{
    private $partyRepository;
    private $user;

    public function __construct(PartyRepositoryInterface $partyRepository, UserRepositoryInterface $user)
    {
        $this->partyRepository = $partyRepository;
        $this->user = $user;
    }

    public function create(array $data)
    {
        $user = $this->user->create($data);
        dispatch(new UserRoleAssignJob($user, 'party'));
        return $this->partyRepository->create(array_merge($data, ['user_id' => $user->id]));
    }

    public function update(array $data, $id)
    {
        $this->user->update($data, $data['user_id']);
        return $this->partyRepository->update($data, $id);
    }

    public function getDropDown()
    {
        return collect(config('constants.default_parties'))->merge($this->partyRepository->all()
            ->pluck('name', 'slug'));
    }
}
