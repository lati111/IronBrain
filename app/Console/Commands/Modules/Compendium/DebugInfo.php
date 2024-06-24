<?php

namespace App\Console\Commands\Modules\Compendium;

use App\Enum\Compendium\Elements;
use App\Models\Compendium\CreatureTemplate;
use App\Models\Compendium\Statblock;
use App\Service\Compendium\ResistanceService;
use Illuminate\Console\Command;

class DebugInfo extends Command
{
    /** {@inheritdoc} */
    protected $signature = 'compendium:debug-import';

    /** {@inheritdoc} */
    protected $description = 'Imports a set of debug data for compendium testing';

    public function handle(): void
    {
        $this->line('Importing debug data...');

        $statblock = new Statblock();
        $statblock->name = 'River Serpent';
        $statblock->base_ac = 10;
        $statblock->base_hp = '16';
        $statblock->base_strength = 8;
        $statblock->base_dexterity = 14;
        $statblock->base_constitution = 13;
        $statblock->base_intelligence = 4;
        $statblock->base_wisdom = 8;
        $statblock->base_charisma = 6;
        $statblock->base_walk_speed = 20;
        $statblock->base_swim_speed = 45;
        $statblock->save();


        $scaledTemplate = new CreatureTemplate();
        $scaledTemplate->code = 'scaled';
        $scaledTemplate->name = 'Scaled';
        $scaledTemplate->save();

        $scaledTemplate->addResistanceModifier(ResistanceService::getOrCreateModifier(Elements::SLASHING, 1, true));
        $statblock->addCreatureTemplate($scaledTemplate);

        $lowIntelligenceTemplate = new CreatureTemplate();
        $lowIntelligenceTemplate->code = 'low-int';
        $lowIntelligenceTemplate->name = 'Low Intelligence';
        $lowIntelligenceTemplate->save();

        $lowIntelligenceTemplate->addResistanceModifier(ResistanceService::getOrCreateModifier(Elements::PSYCHIC, -1, true));
        $statblock->addCreatureTemplate($lowIntelligenceTemplate);

        $coldBloodedTemplate = new CreatureTemplate();
        $coldBloodedTemplate->code = 'cold-blooded';
        $coldBloodedTemplate->name = 'Cold Blooded';
        $coldBloodedTemplate->save();

        $coldBloodedTemplate->addResistanceModifier(ResistanceService::getOrCreateModifier(Elements::FIRE, -1, true));
        $coldBloodedTemplate->addResistanceModifier(ResistanceService::getOrCreateModifier(Elements::COLD, -1, true));
        $statblock->addCreatureTemplate($coldBloodedTemplate);


        $lesserWaterElementalTemplate = new CreatureTemplate();
        $lesserWaterElementalTemplate->code = 'lesser-water-elemental';
        $lesserWaterElementalTemplate->name = 'Water Elemental (lesser)';
        $lesserWaterElementalTemplate->save();

        $lesserWaterElementalTemplate->addResistanceModifier(ResistanceService::getOrCreateModifier(Elements::FIRE, 1, true));
        $lesserWaterElementalTemplate->addResistanceModifier(ResistanceService::getOrCreateModifier(Elements::COLD, -1, true));
        $statblock->addCreatureTemplate($lesserWaterElementalTemplate);

        $this->info('Import successful!');
    }
}
