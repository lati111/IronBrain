<?php

namespace App\Models\PKSanc;

use App\Enum\PKSanc\StoragePaths;
use App\Models\Auth\User;
use Database\Factories\Modules\PKSanc\ImportCsvFactory;
use Database\Factories\Modules\PKSanc\StoredPokemonFactory;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string uuid
 * @property string csv The relative path to the csv file
 * @property string game The game being imported
 * @property string name The user given name of the save file
 * @property float version The user defined csv version
 * @property string uploader_uuid The user that imported the csv
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class ImportCsv extends Model
{
    use HasFactory;
    use HasTimestamps;
    use HasUuids;

    //TODO make on delete for csv file

    /** { @inheritdoc } */
    protected $table = 'pksanc__import_csv';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';

    public function configure(): static
    {
        return $this->afterMaking(function (ImportCsv $csv) {
            $csv->save();
        });
    }

    /**
     * Gets the path leading to this csv
     * @return string Returns the csv path
     */
    public function getCsvPath(): string {
        $path = sprintf(StoragePaths::csv, $this->uploader_uuid, $this->name);
        return storage_path(sprintf('app/%s/%s', $path, $this->csv));
    }

    /**
     * Gets all the pokemon imported from this csv
     * @return HasMany Returns a HasMany relationship of this csv's pokemon
     */
    public function getPokemon(): HasMany
    {
        return $this->hasMany(StoredPokemon::class, 'csv_uuid', 'uuid');
    }

    /**
     * Gets the uploader of this file
     * @return User Returns the uploader
     */
    public function Uploader(): User
    {
        /** @var User $user */
        $user = $this->belongsTo(User::class, 'uploader_uuid', 'uuid')->first();
        return $user;
    }

    /**
     * Gets the game this csv belongs to
     * @return Game Returns the game
     */
    public function Game(): Game
    {
        /** @var Game $game */
        $game = $this->belongsTo(Game::class, 'game', 'game')->first();
        return $game;
    }

    /**
     * Sets which factory should be used
     */
    protected static function newFactory(): ImportCsvFactory
    {
        return ImportCsvFactory::new();
    }
}
