<?php

namespace App\Console\Commands\Modules\Compendium;

use App\Enum\Compendium\Elements;
use App\Models\Compendium\Action;
use App\Models\Compendium\ActionCost;
use App\Models\Compendium\CreatureTemplate;
use App\Models\Compendium\Resource;
use App\Models\Compendium\Statblock;
use App\Models\Compendium\Traits\ActionTrait;
use App\Models\Compendium\Traits\CompendiumTrait;
use App\Models\Compendium\Traits\ProficiencyTrait;
use App\Models\Compendium\Traits\ResourceTrait;
use App\Models\Compendium\Traits\RollModifierTrait;
use App\Models\Compendium\Traits\StatTrait;
use App\Service\Compendium\CompendiumFactory;
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

        $this->addCreateTemplates($statblock);
        $this->addTraits($statblock);

        $this->info('Import successful!');
    }

    public function addCreateTemplates(Statblock $statblock): void {
        $scaledTemplate = new CreatureTemplate();
        $scaledTemplate->code = 'scaled';
        $scaledTemplate->name = 'Scaled';
        $scaledTemplate->save();

        $scaledTemplate->addResistanceModifier(CompendiumFactory::getOrCreateResistanceModifier(Elements::SLASHING, 1, true));
        $statblock->addCreatureTemplate($scaledTemplate);

        $lowIntelligenceTemplate = new CreatureTemplate();
        $lowIntelligenceTemplate->code = 'low-int';
        $lowIntelligenceTemplate->name = 'Low Intelligence';
        $lowIntelligenceTemplate->save();

        $lowIntelligenceTemplate->addResistanceModifier(CompendiumFactory::getOrCreateResistanceModifier(Elements::PSYCHIC, -1, true));
        $statblock->addCreatureTemplate($lowIntelligenceTemplate);

        $coldBloodedTemplate = new CreatureTemplate();
        $coldBloodedTemplate->code = 'cold-blooded';
        $coldBloodedTemplate->name = 'Cold Blooded';
        $coldBloodedTemplate->save();

        $coldBloodedTemplate->addResistanceModifier(CompendiumFactory::getOrCreateResistanceModifier(Elements::FIRE, -1, true));
        $coldBloodedTemplate->addResistanceModifier(CompendiumFactory::getOrCreateResistanceModifier(Elements::COLD, -1, true));
        $statblock->addCreatureTemplate($coldBloodedTemplate);


        $lesserWaterElementalTemplate = new CreatureTemplate();
        $lesserWaterElementalTemplate->code = 'lesser-water-elemental';
        $lesserWaterElementalTemplate->name = 'Water Elemental (lesser)';
        $lesserWaterElementalTemplate->save();

        $lesserWaterElementalTemplate->addResistanceModifier(CompendiumFactory::getOrCreateResistanceModifier(Elements::FIRE, 1, true));
        $lesserWaterElementalTemplate->addResistanceModifier(CompendiumFactory::getOrCreateResistanceModifier(Elements::COLD, -1, true));
        $statblock->addCreatureTemplate($lesserWaterElementalTemplate);
    }

    public function addTraits(Statblock $statblock): void {
        $blessingOfHealthTrait = new CompendiumTrait();
        $blessingOfHealthTrait->name = 'Blessing of vitality';
        $blessingOfHealthTrait->description = 'This creature has received a blessing from the goddess of healing, granting it greater health and the ability to regenerate';
        $blessingOfHealthTrait->save();

        $blessingOfHealthTrait->addStatModifier('MAX_HP', '+<10>');
        $blessingOfHealthTrait->addRollModifier('HEALING', '+<2>');
        $blessingOfHealthTrait->addProficiency('CONSTITUTION', 1);

        $healAction = new Action();
        $healAction->name = 'Heal wounds';
        $healAction->type = 2;
        $healAction->description = "The creature uses it's internal vitality to heal it's wounds";
        $healAction->save();

        $vitalityResource = new Resource();
        $vitalityResource->code = 'vitality';
        $vitalityResource->name = 'Vitality';
        $vitalityResource->recharge_interval = 2;
        $vitalityResource->save();

        $healActionCost = new ActionCost();
        $healActionCost->action_uuid = $healAction->uuid;
        $healActionCost->resource_uuid = $vitalityResource->uuid;
        $healActionCost->cost_formula = '-<1>';
        $healActionCost->save();

        $blessingOfHealthTrait->addAction($healAction);
        $blessingOfHealthTrait->addResource($vitalityResource, '+<CONSTITUTION_MODIFIER>*<3>');

        $statblock->addTrait($blessingOfHealthTrait);

        $windSwimmerTrait = new CompendiumTrait();
        $windSwimmerTrait->name = 'Windstream swimmer';
        $windSwimmerTrait->description = 'This creature is capable of swimming through the air like water.';
        $windSwimmerTrait->save();

        $windSwimmerTrait->addStatModifier('MOVEMENT_FLY', '<MOVEMENT_SWIM>*0.5');
        $windSwimmerTrait->addResistanceModifier(CompendiumFactory::getOrCreateResistanceModifier(Elements::THUNDER, 1, false));

        $statblock->addTrait($windSwimmerTrait);
    }
}
