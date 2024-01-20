<?php

namespace App\Models\PKSanc;

use Database\Factories\Modules\PKSanc\TrainerFactory;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string uuid
 * @property string trainer_id The identifier code used in the pokemon games
 * @property string secret_id The hidden identifier code used in the pokemon games
 * @property string name The player given name for the trainer
 * @property string gender Which gender the trainer is. Either 'M' or 'F'
 * @property string game Which game this trainer came from
 * @property string owner_uuid Which user owns this trainer
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Trainer extends Model
{
    use HasFactory;
    use HasTimestamps;
    use HasUuids;

    protected $table = 'pksanc__trainer';
    protected $primaryKey = 'uuid';

    /**
     * Sets which factory should be used
     */
    protected static function newFactory(): TrainerFactory
    {
        return TrainerFactory::new();
    }
}
