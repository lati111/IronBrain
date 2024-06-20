<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PokemonRibbons extends AbstractModel
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__pokemon_ribbons';
    protected $primaryKey = 'uuid';

    public function Ribbon(): BelongsTo
    {
        return $this->belongsTo(Ribbon::class, 'ribbon', 'ribbon');
    }
}
