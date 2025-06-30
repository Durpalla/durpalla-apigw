<?php


namespace App\Services;


use Illuminate\Support\Facades\Cache;
use App\Models\Option;

class OptionService
{
    private $options;
    public function __construct()
    {
        $this->options = Cache::rememberForever('options', function() {
            return Option::all();
        });
    }

    public function get($key, $default_value = '')
    {
        $item = $this->options->first(function($item, $_k) use($key, $default_value) {
            return $item->field == $key;
        }, function() use($default_value) {
            return $default_value;
        });

        return (is_object($item)) ? $item->value : $item;
    }

    public function getPublicOptions()
    {
        return $this->options->whereIn('tab', ['general', 'booking', 'customer', 'cancellation', 'vatcharge', 'facts'])->pluck('value', 'field');
    }
}
