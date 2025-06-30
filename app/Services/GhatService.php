<?php


namespace App\Services;


use Illuminate\Support\Collection;
use App\Repository\Interfaces\GhatRepositoryInterface;

class GhatService
{
    private $ghatRepository;
    public function __construct( GhatRepositoryInterface $ghatRepository)
    {
        $this->ghatRepository = $ghatRepository;
    }

    public function getAllGhats() : Collection
    {
        return $this->ghatRepository->all()->unique('name');
    }

    public function getSuggestions(string $term, string $accept) : Collection
    {
        return $this->ghatRepository->all()->filter(function($item, $key) use($term, $accept) {
            return (preg_match("/" . $term . "/i", $item->name) && $item->name != $accept);
        });
    }

    public function getDropDown() : Collection
    {
        return $this->ghatRepository->all()->unique('name')->pluck('name', 'id');
    }

    public function create(array $data)
    {
        return $this->ghatRepository->create($data);
    }

    public function update( array $data, int $id)
    {
        return $this->ghatRepository->update($data, $id);
    }

    public function getPlucked(string $service): Collection
    {
        return $this->ghatRepository->all()
            ->where('service_type', $service)
            ->pluck('name', 'id');
    }

    public function getActiveStoppages()
    {
        return $this->ghatRepository->allActive();
    }
}
