<?php


namespace App\Repository;


use App\Models\Option;
use App\Repository\Interfaces\OptionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class OptionRepository extends BaseRepository implements OptionRepositoryInterface
{
    public function __construct(Option $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return Cache::rememberForever('options', function() {
            return parent::all();
        });
    }
}
