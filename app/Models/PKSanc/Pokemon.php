<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pokemon extends Model
{
    use HasFactory;
    use HasTimestamps;

    //TODO make on delete for sprites

    protected $table = 'pksanc__pokemon';
    protected $primaryKey = 'pokemon';
    public $incrementing = false;

    public function PrimaryType(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'primary_type', 'type');
    }

    public function SecondaryType(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'secondary_type', 'type');
    }
}
