<?php

namespace App\Repositories\Hotel;

interface HotelRepositoryInterface
{
    public function getAll();
    public function find($id);
    public function create(array $data);
    public function update(array $data, $id);
    public function delete($id);
    public function getActiveHotels();
    public function getHotelsByCity($cityId);
    public function search(array $filters = []);
}
