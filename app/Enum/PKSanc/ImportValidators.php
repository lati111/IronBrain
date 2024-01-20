<?php

namespace App\Enum\PKSanc;

class ImportValidators
{
    public const PID = 'required|integer|digits_between:8,12';
    public const NICKNAME = 'required|string|max:14';
    public const SPECIES = 'required|string|pksanc_pokemon_exists:%s';
    public const FORM_INDEX = 'required|integer';
    public const ABILITY = 'required|string|exists:pksanc__ability,ability';
    public const NATURE = 'required|string|exists:pksanc__nature,nature';
    public const POKEMON_GENDER = 'required|string|pksanc_pokemon_gender';
    public const LEVEL = 'required|integer|max:100';
    public const FRIENDSHIP = 'required|integer|max:255';
    public const POKEBALL = 'required|string|exists:pksanc__pokeball,pokeball';
    public const SIZE = 'required|integer';
    public const BOOL = 'required|string|csv_boolean';
    public const TYPE = 'required|string|exists:pksanc__type,type';
    public const CONTEST_STAT = 'required|integer|min:0|max:255';
    public const IV = 'required|integer|min:0|max:31';
    public const EV = 'required|integer|min:0|max:255';
    public const MOVE = 'required|string|pksanc_move_exists';
    public const PP_UPS = 'required|integer|min:0|max:3';
    public const DATE = 'required|date';
    public const LOCATION = 'nullable|string|max:255';
    public const GAME = 'required|string|exists:pksanc__game,game';
    public const DYNAMAX_LEVEL = 'required|integer|min:0|max:10';

    public const TRAINER_ID = 'required|integer|digits_between:1,8';
    public const TRAINER_NAME = 'required|string|max:32';
    public const TRAINER_GENDER = 'required|string|pksanc_trainer_gender';

}
