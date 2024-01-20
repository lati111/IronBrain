<?php

namespace App\Providers;

use App\Enum\PKSanc\Genders;
use App\Models\PKSanc\Move;
use App\Models\PKSanc\Pokemon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Validator::extend('csv_boolean', function ($attribute, $value, $parameters, $validator) {
            return in_array($value, ['True', 'False']);
        });

        Validator::extend('pksanc_pokemon_exists', function ($attribute, $value, $parameters, $validator) {
            if (count($parameters) !== 1) {
                throw new \InvalidArgumentException("Validation rule pksanc_pokemon_exists requires 1 parameter.");
            }

            $formIndex = $validator->getData()[$parameters[0]];
            return Pokemon::where('form_index', $formIndex)
                ->where(function ($query) use ($value) {
                    $query
                        ->orWhere('pokemon', $value)
                        ->orWhere('species', $value);
                })->exists();
        });

        Validator::extend('pksanc_move_exists', function ($attribute, $value, $parameters, $validator) {
            return (Move::where('move', $value)->exists() || $value === 'none');
        });

        Validator::extend('pksanc_pokemon_gender', function ($attribute, $value, $parameters, $validator) {
            return in_array($value, Genders::pokemonGenders);
        });

        Validator::extend('pksanc_trainer_gender', function ($attribute, $value, $parameters, $validator) {
            return in_array($value, Genders::trainerGenders);
        });
    }
}
