<?php


namespace App\Services;


use App\Models\Option;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class OptionService
{
    private ?Collection $options = null;

    private function loadOptions(): Collection
    {
        if ($this->options instanceof Collection) {
            return $this->options;
        }

        try {
            if (! Schema::hasTable('options')) {
                return $this->options = collect();
            }

            $this->options = Cache::rememberForever('options', static function () {
                return Option::all();
            });
        } catch (\Throwable) {
            $this->options = collect();
        }

        return $this->options;
    }

    public function get($key, $default_value = '')
    {
        $item = $this->loadOptions()->first(function ($item, $_k) use ($key) {
            return $item->field == $key;
        }, function () use ($default_value) {
            return $default_value;
        });

        return (is_object($item)) ? $item->value : $item;
    }

    public function getPublicOptions()
    {
        return $this->loadOptions()
            ->whereIn('tab', ['general', 'booking', 'customer', 'cancellation', 'vatcharge', 'facts'])
            ->pluck('value', 'field');
    }
}
