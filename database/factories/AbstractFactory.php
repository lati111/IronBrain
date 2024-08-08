<?php

namespace Database\Factories;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Factories\Factory;

abstract class AbstractFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (AbstractModel $item) {
            $item->save();
        });
    }
}
