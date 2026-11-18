<?php

namespace App\Models\Arsenal;

use App\Models\AbstractModel;
use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string uuid
 * @property string component_uuid The uuid of the component
 * @property string name The relic's name
 * @property string grade The relic's grade, eg lith, meso, neo, axi
 * @property string key The relic's key, eg A2 or V13
 * @property string rarity The component's rarity, eg uncommon
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Relic extends ArsenalDataModel
{
    use hasUuids;

    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__relic';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;


    /** @inheritdoc */
    protected $primaryKey = 'uuid';

    /** @inheritdoc */
    protected $keyType = 'uuid';

    /** @inheritdoc */
    public $incrementing = false;


    /**
     * Get the owned component
     * @return Component|null
     */
    public function getComponent(): Component|null
    {
        /** @var Component $component */
        $component = $this->Component()->first();
        return $component;
    }

    /**
     * The relationship for the owned component
     * @return BelongsTo
     */
    public function Component(): BelongsTo {
        return $this->belongsTo(Component::class, 'component_uuid', 'uuid');
    }
}
