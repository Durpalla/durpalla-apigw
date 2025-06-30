<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Models\Service;

class ServiceObserver
{
    public function __construct()
    {
        Cache::forget('services');
    }


    public function created(Service $service)
    {
        session()->flash('success', 'Service successfully created');
    }


    public function updated(Service $service)
    {
        session()->flash('success', 'Service successfully updated');
    }

    public function deleted(Service $service)
    {
        session()->flash('success', 'Service successfully deleted');
    }


    public function restored(Service $service)
    {
        session()->flash('success', 'Service successfully restored');
    }

    public function forceDeleted(Service $service)
    {
        session()->flash('success', 'Service permanently deleted');
    }
}
