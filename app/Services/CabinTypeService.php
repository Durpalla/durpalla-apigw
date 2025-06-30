<?php


namespace App\Services;


use Illuminate\Support\Collection;
use App\Repository\Interfaces\CabinTypeRepositoryInterface;

class CabinTypeService
{
    private $cabinType;
    public function __construct(CabinTypeRepositoryInterface $cabinType)
    {
        $this->cabinType = $cabinType;
    }

    public function getCabinTypeDropDown(): Collection
    {
        return $this->cabinType->all()->filter(function($item, $key) {
            return $item->type == 'cabin';
        })->map(function($item, $key) {
            return [
                'id' => $item->id,
                'name' => $item->name . (($item->is_ac) ? ' (AC)' : ' (Non AC)')
            ];
        });
    }

    public function getSeatTypeDropDown(): Collection
    {
        return $this->cabinType->all()->filter(function($item, $key) {
            return $item->type == 'seat';
        })->map(function($item, $key) {
            return [
                'id' => $item->id,
                'name' => $item->name . (($item->is_ac) ? ' (AC)' : ' (Non AC)')
            ];
        });
    }

    public function create(array $data)
    {
        return $this->cabinType->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->cabinType->update($data, $id);
    }
}
