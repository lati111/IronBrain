<?php

namespace App\Enum\PKSanc;

class Genders
{
    public const pokemonGenders = [
        Genders::MALE,
        Genders::FEMALE,
        Genders::GENDERLESS,
    ];

    public const trainerGenders = [
        Genders::MALE,
        Genders::FEMALE,
    ];

    public const MALE = 'M';
    public const FEMALE = 'F';
    public const GENDERLESS = '-';
}
