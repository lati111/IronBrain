<?php

namespace App\Models\Config;

use App\Models\AbstractModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @inheritdoc
 * @property int id
 * @property int module_id The id of the module this submodule belongs to
 * @property string code The unique code identifying this module
 * @property string name The display name for the module
 * @property int order The sorting order for this module used during displaying
 * @property string|null route The route leading to the main page for this module
 * @property int|null permission_id The id for permission a user needs to have to access the module, if any
 * @property bool requires_login Whether this route requires the user to be logged in to access
 * @property Carbon|null deleted_at Whether or not the item was deleted or not
 */

class Submodule extends AbstractModel
{
    use HasFactory, HasTimestamps, SoftDeletes;

    /** { @inheritdoc } */
    protected $table = 'module__sub';

    /** { @inheritdoc } */
    protected $primaryKey = 'id';

    /**
     * The belongs to relationship to the primary module
     * @return BelongsTo The relationship
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'id');
    }

    /**
     * The belongs to relationship to the required permission
     * @return BelongsTo The relationship
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(BelongsTo::class, 'permission_id');
    }
}
