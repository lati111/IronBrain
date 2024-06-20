<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Move extends AbstractModel
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__move';
    protected $primaryKey = 'move';
    public $incrementing = false;

    public function Uploader(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type', 'type');
    }
}
