<?php

namespace Database\Factories\Modules\PKSanc;

use App\Models\Auth\User;
use App\Models\PKSanc\ContestStats;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Moveset;
use App\Models\PKSanc\Origin;
use App\Models\PKSanc\Stats;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Trainer;

class StoredPokemonCreator
{
    /** @var User|null Which user should be used for this pokemon */
    private ?User $user = null;

    /** @var Trainer|null Which trainer should be used for the origin */
    private ?Trainer $trainer = null;

    /** @var ImportCsv|null Which import csv should be used */
    private ?ImportCsv $csv = null;

    /** @var array $pokemonvars Array of variables to be passed to the pokemon factory */
    public array $pokemonvars = [];

    /** @var array $originvars Array of variables to be passed to the origin factory */
    public array $originvars = [];

    /** @var array $originvars Array of variables to be passed to the statblock factory */
    public array $statvars = [];

    /** @var array $originvars Array of variables to be passed to the contest statblock factory */
    public array $conteststatblockvars = [];

    /** @var array $originvars Array of variables to be passed to the moveset factory */
    public array $movesetvars = [];

    /**
     * Creates the pokemon according to the given parameters
     * @return StoredPokemon Returns the created pokemon
     */
    public function create(): StoredPokemon {
        $pokemon = StoredPokemon::factory()->make(array_merge([
            'owner_uuid' => $this->getUser()->uuid,
            'csv_uuid' => $this->getCsv()->uuid,
        ], $this->pokemonvars));

        Origin::factory()->make(array_merge([
            'pokemon_uuid' => $pokemon->uuid,
            'trainer_uuid' => $this->getTrainer()->uuid,
        ], $this->originvars));

        Stats::factory()->make(array_merge([
            'pokemon_uuid' => $pokemon->uuid,
        ], $this->statvars));

        ContestStats::factory()->make(array_merge([
            'pokemon_uuid' => $pokemon->uuid,
        ], $this->conteststatblockvars));

        Moveset::factory()->make(array_merge([
            'pokemon_uuid' => $pokemon->uuid,
        ], $this->movesetvars));

        return $pokemon;
    }


    /**
     * Sets the user this pokemon should be made with
     * @param User $user
     * @return void
     */
    public function setUser(User $user): void {
        $this->user = $user;
    }

    /**
     * Gets the user this pokemon should be made with
     * @return User
     */
    public function getUser(): User {
        $user = $this->user;
        if ($user === null) {
            $user = User::inRandomOrder()->first();

            if ($user === null) {
                $user = User::factory()->make();
            }

            $this->user = $user;
        }

        return $user;
    }


    /**
     * Sets the trainer this pokemon's origin should be made with
     * @param Trainer $trainer
     * @return void
     */
    public function setTrainer(Trainer $trainer): void {
        $this->trainer = $trainer;
    }

    /**
     * Gets the trainer this pokemon's origin should be made with
     * @return Trainer
     */
    public function getTrainer(): Trainer {
        $trainer = $this->trainer;
        if ($trainer === null) {
            $trainer = Trainer::inRandomOrder()->first();

            if ($trainer === null) {
                $trainer = Trainer::factory()->make();
            }

            $this->trainer = $trainer;
        }

        return $trainer;
    }


    /**
     * Sets the csv that should be used for this pokemon
     * @param ImportCsv $csv
     * @return void
     */
    public function setCsv(ImportCsv $csv): void {
        $this->csv = $csv;
    }

    /**
     * Gets the trainer this pokemon's origin should be made with
     * @return ImportCsv
     */
    public function getCsv(): ImportCsv {
        $csv = $this->csv;
        if ($csv === null) {
            $csv = ImportCsv::factory()->make([
                'uploader_uuid' => $this->getUser()->uuid,
            ]);

            $this->csv = $csv;
        }

        return $csv;
    }
}
