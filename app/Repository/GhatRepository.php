<?php


namespace App\Repository;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Ghat;
use App\Repository\Interfaces\GhatRepositoryInterface;
use App\Models\VehicleSchedule;

class GhatRepository extends BaseRepository implements GhatRepositoryInterface
{
    public function __construct(Ghat $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return Cache::rememberForever('ghats', function() {
            return parent::all();
        });
    }

    public function allActive() : Collection
    {
        return Cache::remember('active_stoppages', 3600, function() {
            return VehicleSchedule::with('routeProperties.ghat')->active()->get()
                ->flatten(3)
                ->map(function($item, $key) {
                    return $item->routeProperties->map(function($item, $key) {
                        return [
                            'id' => $item->ghat->id,
                            'name' => $item->ghat->name,
                            'type' => $item->ghat->service_type
                        ];
                    });
                })->flatten(1)->unique();
        });
    }
}
