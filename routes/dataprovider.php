<?php

use Illuminate\Support\Facades\Route;

//| Home

Route::prefix('/home/overview')->group(function() {
    Route::get('/', [\App\Http\Dataproviders\Config\Module\ModuleOverviewCardlist::class, 'data'])
        ->name('data.home.overview.cardlist');

    Route::get('/pages', [\App\Http\Dataproviders\Config\Module\ModuleOverviewCardlist::class, 'count'])
        ->name('data.home.overview.pages');
});


//| Config

Route::prefix('/config')->group(function() {

    Route::prefix('/users')
        ->middleware('auth.permission:config.user.view')
        ->group(function() {
            Route::dataprovider('/overview', 'data.config.users.overview.datatable',
                \App\Http\Dataproviders\Config\User\UserOverviewDatatable::class);
    });

    Route::prefix('/role')
        ->middleware('auth.permission:config.role.view')
        ->group(function() {
            Route::dataprovider('/overview', 'data.config.roles.overview.datatable',
                \App\Http\Dataproviders\Config\Role\RoleOverviewDatatable::class);

            Route::dataprovider('/dataselect', 'data.config.roles.dataselect',
                \App\Http\Dataproviders\Config\Role\RoleDataselect::class);

            Route::dataprovider('/{role_id}/permissions', 'data.config.roles.permissions.datatable',
                \App\Http\Dataproviders\Config\Role\RolePermissionDatatable::class)
                ->middleware('auth.permission:config.role.permissions');
    });
});


//| PKSanc

Route::prefix('/pksanc/data')
    ->middleware('auth:sanctum')
    ->group(function() {

        Route::dataprovider('/overview', 'data.pksanc.overview',
            \App\Http\Dataproviders\Modules\PKSanc\PKSancOverviewCardList::class);

        Route::dataprovider('/pokedex', 'data.pksanc.pokedex',
            \App\Http\Dataproviders\Modules\PKSanc\PKSancPokedexCardList::class);

        Route::dataprovider('/staging/{import_uuid}', 'data.pksanc.staging',
            \App\Http\Dataproviders\Modules\PKSanc\PKSancStagingCardList::class);

        //| Data

        Route::dataprovider('/games/dataselect', 'data.pksanc.games.dataselect',
            \App\Http\Dataproviders\Modules\PKSanc\Data\GameDataSelect::class);

        Route::dataprovider('/owned-species/dataselect', 'data.pksanc.owned-species.dataselect',
            \App\Http\Dataproviders\Modules\PKSanc\Data\OwnedPokemonSpeciesSelect::class);
    });
