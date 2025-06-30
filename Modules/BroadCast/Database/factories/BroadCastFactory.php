<?php
namespace Modules\BroadCast\Database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BroadCast\Entities\BroadCast;

class BroadCastFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BroadCast::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            //
        ];
    }
}

