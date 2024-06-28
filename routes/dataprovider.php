<?php

use Illuminate\Support\Facades\Route;

Route::prefix('/pksanc/data')
    ->middleware('auth:sanctum')
    ->group(function() {
        Route::prefix('/overview')->group(function() {
            Route::get('/', [App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancOverviewCardList::class, 'data'])
                ->name('pksanc.overview.cardlist');

            Route::get('/pages', [App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancOverviewCardList::class, 'count'])
                ->name('pksanc.overview.pages');

            Route::get('/filters', [App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancOverviewCardList::class, 'filters'])
                ->name('pksanc.overview.filters');
        });

        Route::prefix('/pokedex')->group(function() {
            Route::get('/', [App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancPokedexCardList::class, 'data'])
                ->name('pksanc.pokedex.cardlist');

            Route::get('/pages', [App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancPokedexCardList::class, 'count'])
                ->name('pksanc.pokedex.pages');

            Route::get('/filters', [App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancPokedexCardList::class, 'filters'])
                ->name('pksanc.pokedex.filters');
        });

        Route::prefix('/staging/{import_uuid}')->group(function() {
            Route::get('/', [App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancStagingCardList::class, 'data'])
                ->name('pksanc.staging.cardlist');

            Route::get('/pages', [App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancStagingCardList::class, 'count'])
                ->name('pksanc.staging.pages');

            Route::get('/filters', [App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancStagingCardList::class, 'filters'])
                ->name('pksanc.staging.filters');
        });

        Route::prefix('/games/dataselect')->group(function() {
            Route::get('/', [App\Http\Dataproviders\SelectorLists\Modules\PKSanc\GameDataSelect::class, 'data'])
                ->name('pksanc.games.dataselect');

            Route::get('/pages', [App\Http\Dataproviders\SelectorLists\Modules\PKSanc\GameDataSelect::class, 'count'])
                ->name('pksanc.games.pages');
        });

        Route::prefix('/owned-species/dataselect')->group(function() {
            Route::get('/', [App\Http\Dataproviders\SelectorLists\Modules\PKSanc\FilterSelects\OwnedPokemonSpecies::class, 'data'])
                ->name('pksanc.owned-species.dataselect');

            Route::get('/pages', [App\Http\Dataproviders\SelectorLists\Modules\PKSanc\FilterSelects\OwnedPokemonSpecies::class, 'count'])
                ->name('pksanc.owned-species.pages');
        });
    });
