<?php

namespace App\Repositories\Hotel;

use App\Repository\BaseRepository;
use App\Models\Hotel;

class HotelRepository extends BaseRepository implements HotelRepositoryInterface
{
    public function __construct(Hotel $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    /**
     * Get all hotels.
     */
    public function getAll()
    {
        return $this->model->with(['city', 'rooms', 'facilities', 'images'])->latest()->get();
    }

    /**
     * Get active hotels.
     */
    public function getActiveHotels()
    {
        return $this->model->where('status', 1)
            ->with(['city', 'activeRooms', 'facilities'])
            ->latest()
            ->get();
    }

    /**
     * Get hotels by city.
     */
    public function getHotelsByCity($cityId)
    {
        return $this->model->where('city_id', $cityId)
            ->where('status', 1)
            ->with(['city', 'activeRooms', 'facilities'])
            ->get();
    }

    /**
     * Search hotels with filters.
     */
    public function search(array $filters = [])
    {
        $query = $this->model->where('status', 1);

        if (isset($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }

        if (isset($filters['min_rating'])) {
            $query->where('rating', '>=', $filters['min_rating']);
        }

        if (isset($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        return $query->with(['city', 'facilities', 'images'])->get();
    }
}
