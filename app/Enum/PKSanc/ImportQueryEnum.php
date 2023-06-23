<?php

namespace App\Enum\PKSanc;

class ImportQueryEnum
{
    public const TYPE_QUERY =
        'query type_query {'.
        '  type: pokemon_v2_type {'.
        '    type: name'.
        '    name: pokemon_v2_typenames(where: {pokemon_v2_language: {name: {_eq: "en"}}}) {'.
        '      name'.
        '    }'.
        '  }'.
        '}';

    public const NATURE_QUERY =
        'query nature_query {'.
        '  type: pokemon_v2_nature {'.
        '    type: name'.
        '    name: pokemon_v2_naturenames(where: {pokemon_v2_language: {name: {_eq: "en"}}}) {'.
        '      name'.
        '    }'.
        '  }'.
        '}';

    public const MOVE_QUERY =
        'query move_query {'.
        '  move: pokemon_v2_move {'.
        '    move: name'.
        '    name: pokemon_v2_movenames(where: {pokemon_v2_language: {name: {_eq: "en"}}}) {'.
        '      name'.
        '    }'.
        '    power'.
        '    accuracy'.
        '    priority'.
        '    type: pokemon_v2_type {'.
        '      type: name'.
        '    }'.
        '    move_type: pokemon_v2_movedamageclass {'.
        '      move_type: name'.
        '    }'.
        '    description: pokemon_v2_moveflavortexts(where: {pokemon_v2_language: {name: {_eq: "en"}}}, order_by: {pokemon_v2_versiongroup: {generation_id: desc}}, limit: 1) {'.
        '      description: flavor_text'.
        '    }'.
        '  }'.
        '}';

    public const POKEBALL_QUERY =
        'query pokeball_query {'.
        '  pokeball: pokemon_v2_item(where: {item_category_id: {_in: [33, 34, 39]}}) {'.
        '    pokeball: name'.
        '    name: pokemon_v2_itemnames(where: {pokemon_v2_language: {name: {_eq: "en"}}}) {'.
        '      name'.
        '    }'.
        '    sprite_string: pokemon_v2_itemsprites {'.
        '      sprite_string: sprites'.
        '    }'.
        '    '.
        '  }'.
        '}';

    public const ABILITY_QUERY =
        'query ability_query {'.
        '  pokemon_v2_ability {'.
        '    ability: name'.
        '    name: pokemon_v2_abilitynames(where: {pokemon_v2_language: {name: {_eq: "en"}}}) {'.
        '      name'.
        '    }'.
        '    description: pokemon_v2_abilityflavortexts(where: {pokemon_v2_language: {name: {_eq: "en"}}}, limit: 1, order_by: {pokemon_v2_versiongroup: {generation_id: desc}}) {'.
        '      description: flavor_text'.
        '    }'.
        '  }'.
        '}'.
        '';

    public const POKEMON_QUERY =
        'query pokemon_query {'.
        '  pokemon:pokemon_v2_pokemonform(where: {is_mega: {_eq: false}, is_battle_only: {_eq: false}}, order_by: {pokemon_v2_pokemon: {pokemon_v2_pokemonspecy: {id: asc}}}) {'.
        '    pokemon: name'.
        '    form_index: form_order'.
        '    form_name: form_name'.
        '    details: pokemon_v2_pokemon {'.
        '      species: pokemon_v2_pokemonspecy {'.
        '        species: name'.
        '        generation: pokemon_v2_generation {'.
        '          generation: id'.
        '        }'.
        '      }'.
        '      types: pokemon_v2_pokemontypes {'.
        '        type: pokemon_v2_type {'.
        '          type: name'.
        '        }'.
        '      }'.
        '      stats: pokemon_v2_pokemonstats {'.
        '        stat: pokemon_v2_stat {'.
        '          name'.
        '        }'.
        '        value: base_stat'.
        '      }'.
        '    }'.
        '    sprites: pokemon_v2_pokemonformsprites {'.
        '      sprites'.
        '    }'.
        '    pokedex_id: pokemon_id'.
        '  }'.
        '}'.
        '';

}
