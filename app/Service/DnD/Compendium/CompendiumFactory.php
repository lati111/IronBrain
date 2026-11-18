<?php

namespace App\Service\Compendium;

use App\Models\Compendium\ResistanceModifier;

class CompendiumFactory
{
    /** Create or get a resistance modifier
     * @param string $element The element to be modified
     * @param int $stage The amount of stages the resistance should be modified by
     * @param bool $isBase If this resistance determines the base resistance, or if it modifies them
     * @return ResistanceModifier The modifier
     */
    public static function getOrCreateResistanceModifier(string $element, int $stage, bool $isBase): ResistanceModifier
    {
        $modifier = ResistanceModifier::where('element', $element)
            ->where('stage', $stage)
            ->where('is_base', $isBase)
            ->first();

        if ($modifier !== null) {
            return $modifier;
        }

        $modifier = new ResistanceModifier();
        $modifier->element = $element;
        $modifier->stage = $stage;
        $modifier->is_base = $isBase;
        $modifier->save();

        return $modifier;
    }
}
