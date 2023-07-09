<?php

namespace App\Models\PKSanc;

use App\Enum\PKSanc\StoragePaths;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportCsv extends Model
{
    use HasFactory;
    use HasTimestamps;

    //TODO make on delete for csv file

    protected $table = 'pksanc__import_csv';
    protected $primaryKey = 'csv';
    public $incrementing = false;

    public function getCsvPath(): string {
        $path = sprintf(StoragePaths::csv, $this->uploader_uuid, $this->name);
        return storage_path(sprintf('app/%s/%s', $path, $this->csv));
    }

    public function Uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_uuid', 'uuid');
    }

    public function Game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game', 'game');
    }
}
