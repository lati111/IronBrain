<?php

use App\Dataproviders\Cardlists\Config\ProjectOverviewCardlist;
use App\Dataproviders\Cardlists\Modules\PKSanc\PKSancOverviewCardList;
use App\Dataproviders\Cardlists\Modules\PKSanc\PKSancPokedexCardList;
use App\Dataproviders\Cardlists\Modules\PKSanc\PKSancStagingCardList;
use App\Dataproviders\SelectorLists\Modules\PKSanc\FilterSelects\OwnedPokemonSpecies;
use App\Dataproviders\SelectorLists\Modules\PKSanc\GameDataSelect;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Modules\PKSanc\PKSancContributionController;
use App\Http\Controllers\Modules\PKSanc\PKSancController;
use App\Http\Controllers\Modules\PKSanc\PKSancDepositController;
use App\Http\Controllers\Modules\PKSanc\PKSancPokdexController;
use Illuminate\Support\Facades\Route;

//| home
Route::prefix('/')->group(function() {
    Route::get('/', [HomeController::class, 'show'])->name("home.show");

    // data providers
    Route::prefix('/home/data/overview')->group(function() {
        Route::get('/', [ProjectOverviewCardlist::class, 'data'])
            ->name('home.overview.cardlist');

        Route::get('/pages', [ProjectOverviewCardlist::class, 'count'])
            ->name('home.overview.pages');
    });
});

//| authentication
Route::prefix('/auth')->group(function() {
    Route::get('/signup', [AuthController::class, 'showSignup'])
        ->name("auth.signup.show");

    Route::post('/signup/save', [AuthController::class, 'saveSignup'])
        ->name("auth.signup.save");

    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name("auth.login.show");

    Route::post('/login/save', [AuthController::class, 'attemptLogin'])
        ->name("auth.login.attempt");

    Route::get('/logout', [AuthController::class, 'logout'])
        ->name("auth.logout");
});

//| pksanc
Route::prefix('/pksanc')
    ->middleware('auth:sanctum')
    ->group(function() {
    // pages
    Route::get('/', [PKSancController::class, 'showOverview'])
        ->name('pksanc.home.show');

    Route::get('/pokedex', [PKSancController::class, 'showPokedex'])
        ->name('pksanc.pokedex.show');

    Route::prefix('/deposit')->group(function() {
        Route::get('/', [PKSancDepositController::class, 'showDeposit'])
            ->name('pksanc.deposit.show');

        Route::prefix('/stage')->group(function() {
            Route::post('/', [PKSancDepositController::class, 'stageDepositAttempt'])
                ->name('pksanc.deposit.stage.attempt');

            Route::get('/{importUuid}', [PKSancDepositController::class, 'showDepositAttempt'])
                ->name('pksanc.deposit.stage.show');

            Route::get('/{importUuid}/confirm', [PKSancDepositController::class, 'depositConfirm'])
                ->name('pksanc.deposit.stage.confirm');

            Route::get('/{importUuid}/cancel', [PKSancDepositController::class, 'depositCancel'])
                ->name('pksanc.deposit.stage.cancel');
        });
    });

    // api calls
    Route::post('/romhacks/add', [PKSancContributionController::class, 'addRomhack'])
        ->name('pksanc.games.romhacks.add');

    Route::post('/pokedex/mark', [PKSancPokdexController::class, 'setPokedexMarking'])
        ->name('pksanc.pokedex.mark');

    Route::post('/pokedex/unmark', [PKSancPokdexController::class, 'setPokedexMarking'])
        ->name('pksanc.pokedex.unmark');

    // data providers
    Route::prefix('/data/overview')->group(function() {
        Route::get('/', [PKSancOverviewCardList::class, 'data'])
            ->name('pksanc.overview.cardlist');

        Route::get('/pages', [PKSancOverviewCardList::class, 'count'])
            ->name('pksanc.overview.pages');

        Route::get('/filters', [PKSancOverviewCardList::class, 'filters'])
            ->name('pksanc.overview.filters');
    });

    Route::prefix('/data/staging/{import_uuid}')->group(function() {
        Route::get('/', [PKSancStagingCardList::class, 'data'])
            ->name('pksanc.staging.cardlist');

        Route::get('/pages', [PKSancStagingCardList::class, 'count'])
            ->name('pksanc.staging.pages');

        Route::get('/filters', [PKSancStagingCardList::class, 'filters'])
            ->name('pksanc.staging.filters');
    });

    Route::prefix('/data/pokedex')->group(function() {
        Route::get('/', [PKSancPokedexCardList::class, 'data'])
            ->name('pksanc.pokedex.cardlist');

        Route::get('/pages', [PKSancPokedexCardList::class, 'count'])
            ->name('pksanc.pokedex.pages');

        Route::get('/filters', [PKSancPokedexCardList::class, 'filters'])
            ->name('pksanc.pokedex.filters');
    });

    Route::prefix('/data/games/dataselect')->group(function() {
        Route::get('/', [GameDataSelect::class, 'data'])
            ->name('pksanc.games.dataselect');

        Route::get('/pages', [GameDataSelect::class, 'count'])
            ->name('pksanc.games.pages');
    });

        Route::prefix('/data/owned-species/dataselect')->group(function() {
            Route::get('/', [OwnedPokemonSpecies::class, 'data'])
                ->name('pksanc.owned-species.dataselect');

            Route::get('/pages', [OwnedPokemonSpecies::class, 'count'])
                ->name('pksanc.owned-species.pages');
        });
});
