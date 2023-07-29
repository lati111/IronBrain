<?php
namespace App\Dataproviders\Cardlists\Project;

use App\Dataproviders\Cardlists\AbstractCardlist;
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Type;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PKSancCardList extends AbstractCardlist
{
    private const ICON_LIST_HTML = "<div class='flex flex-col justify-center items-center h-full w-8 my-2 gap-2'>%s</div>";

    private const ICON_HTML = "<img src='%s' alt='%s' title='%s' class='h-%s'>";

    private const SPRITE_BLOCK_HTML =
        '<div class="pksanc-minimal-block flex flex-col justify-center gap-0 w-24">'.
            '<span class="text-center" title="species">%s</span>'.
            '<img src="%s" alt="%s sprite" title="sprite" class="w-24 h-24">'.
            '<div class="flex flex-col items-center">'.
                '<span class="text-center" title="name">%s</span>'.
            '</div>'.
        '</div>';

    private const MINIMAL_INFO_BLOCK =
        '<div class="pksanc-minimal-block flex flex-col items-center gap-2">'.
            '<span class="text-center" title="level">Level %s</span>'.
            '<span class="text-center" title="nature">%s</span>'.
            '<span class="text-center" title="ability">%s</span>'.
            '<img src="%s" alt="%s hidden power type" title="hidden power" class="w-28 h-6">'.
        '</div>';

    private const MINIMAL_TRAINER_BLOCK =
        '<div>'.
            '<div class="pksanc-minimal-block flex flex-col items-center gap-2">'.
                '<div class="flex flex-col items-center gap-0">'.
                    '<span class="text-center" title="save file">%s</span>'.
                    '<span class="text-center" title="caught game">%s</span>'.
                '</div>'.
                '<span class="text-center" title="caught location">%s</span>'.
                '<div class="flex flex-row items-center gap-1">'.
                    '<span class="text-center" title="trainer">%s</span>'.
                    '<img src="%s" alt="%s gender icon" title="%s" class="h-5">'.
                '</div>'.
            '</div>'.
        '</div>';

    public function overviewData(Request $request)
    {
        $pokemonCollection = StoredPokemon::select()
            ->where('validated_at', '!=', null)
            ->where('owner_uuid', Auth::user()->uuid);
        $pokemonCollection = $this->applyTableFilters($request, $pokemonCollection)->get();

        $data = [];
        foreach ($pokemonCollection as $pokemon) {
            $iconList = $this->getIconList($pokemon);

            $spriteBlock = $this->getSpriteBlock(
                $pokemon->getSprite(),
                $pokemon->Pokemon()->getName(),
                $pokemon->Pokemon()->pokemon,
                ($pokemon->nickname !== null) ? $pokemon->nickname : $pokemon->Pokemon()->species_name
            );

            $infoBlock = $this->getMinimalInfoBlock(
                $pokemon->Nature(),
                $pokemon->Ability(),
                $pokemon->HiddenPower(),
                $pokemon->level
            );

            $origin = $pokemon->Origin();
            $trainer = $origin->Trainer();
            $trainerBlock = $this->getMinimalTrainerBlock(
                $pokemon->Csv()->name,
                $origin->Game()->name,
                $origin->met_location,
                $trainer->name,
                $trainer->gender,
            );

            $data[] = [
                $iconList,
                $spriteBlock,
                $infoBlock,
                $trainerBlock
            ];
        }

        return response()->json($data, 200);
    }

    public function stagingData(Request $request, string $importUuid)
    {
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            return response()->json(sprintf('No import csv matching the uuid %s found', $importUuid), 404);
        }

        $pokemonCollection = $csv->getPokemon();
        $pokemonCollection = $this->applyTableFilters($request, $pokemonCollection)->get();

        $data = [];
        foreach ($pokemonCollection as $pokemon) {
            $iconList = $this->getIconList($pokemon);

            $spriteBlock = $this->getSpriteBlock(
                $pokemon->getSprite(),
                $pokemon->Pokemon()->getName(),
                $pokemon->Pokemon()->pokemon,
                ($pokemon->nickname !== null) ? $pokemon->nickname : $pokemon->Pokemon()->species_name
            );

            $infoBlock = $this->getMinimalInfoBlock(
                $pokemon->Nature(),
                $pokemon->Ability(),
                $pokemon->HiddenPower(),
                $pokemon->level
            );

            $origin = $pokemon->Origin();
            $trainer = $origin->Trainer();
            $trainerBlock = $this->getMinimalTrainerBlock(
                $pokemon->Csv()->name,
                $origin->Game()->name,
                $origin->met_location,
                $trainer->name,
                $trainer->gender,
            );

            $data[] = [
                $iconList,
                $spriteBlock,
                $infoBlock,
                $trainerBlock
            ];
        }

        return response()->json($data, 200);
    }

    private function getSpriteBlock(string $sprite, string $species_name, string $species, string $name)
    {
        return sprintf(
            self::SPRITE_BLOCK_HTML,
            $species_name,
            asset('img/project/pksanc/pokemon/' . $sprite),
            $species,
            $name
        );
    }

    private function getMinimalInfoBlock(Nature $nature, Ability $ability, Type $hiddenPower, int $level): string
    {
        return sprintf(
            self::MINIMAL_INFO_BLOCK,
            $level,
            $nature->name,
            $ability->name,
            asset(sprintf(
                'img/project/pksanc/icon/type/%s_full.png',
                $hiddenPower->type
            )),
            $hiddenPower->name
        );
    }

    private function getMinimalTrainerBlock(string $saveName, string $game, string $location, string $trainerName, string $trainerGender): string
    {
        $genderSrc = asset('img/project/pksanc/icon/gender/male.png');
        $genderAlt = 'male';
        $genderTitle = 'Male';
        if ($trainerGender === 'F') {
            $genderSrc = asset('img/project/pksanc/icon/gender/female.png');
            $genderAlt = 'female';
            $genderTitle = 'Female';
        }

        return sprintf(
            self::MINIMAL_TRAINER_BLOCK,
            $saveName,
            $game,
            $location,
            $trainerName,
            $genderSrc,
            $genderAlt,
            $genderTitle
        );
    }

    private function getIconList(StoredPokemon $pokemon)
    {
        $pokeball = $pokemon->Pokeball();
        $iconString = $this->getIcon(
            asset('img/project/pksanc/pokeball/' . $pokeball->sprite),
            $pokeball->name . ' icon',
            $pokeball->name,
            8
        );

        switch ($pokemon->gender) {
            case 'M':
                $iconString .= $this->getIcon(
                    asset('img/project/pksanc/icon/gender/male.png'),
                    'male icon',
                    'Male',
                    6
                );
                break;
            case 'F':
                $iconString .= $this->getIcon(
                    asset('img/project/pksanc/icon/gender/female.png'),
                    'female icon',
                    'Female',
                    6
                );
                break;
            case '-':
                $iconString .= $this->getIcon(
                    asset('img/project/pksanc/icon/gender/none.png'),
                    'genderless icon',
                    'Genderless',
                    6
                );
                break;
        }

        $teraType = $pokemon->TeraType();
        $iconString .= $this->getIcon(
            asset('img/project/pksanc/icon/tera/' . $teraType->type . '.png'),
            $teraType->type . ' tera type icon',
            $teraType->name . ' tera type',
            8
        );

        if ($pokemon->is_alpha === 1) {
            $iconString .= $this->getIcon(
                asset('img/project/pksanc/icon/alpha.png'),
                'aplha icon',
                'Alpha',
                6
            );
        }

        if ($pokemon->can_gigantamax === 1) {
            $iconString .= $this->getIcon(
                asset('img/project/pksanc/icon/dyna.png'),
                'dynamax icon',
                'Dynamax',
                6
            );
        }

        if ($pokemon->has_n_sparkle === 1) {
            $iconString .= $this->getIcon(
                asset('img/project/pksanc/icon/n_sparkle.png'),
                'N sparkle icon',
                'N sparkle',
                6
            );
        }

        if ($pokemon->is_shiny === 1) {
            $iconString .= $this->getIcon(
                asset('img/project/pksanc/icon/shiny.png'),
                'shiny icon',
                'Shiny',
                6
            );
        }

        return sprintf(self::ICON_LIST_HTML, $iconString);
    }

    private function getIcon(string $src, string $alt, string $title, int $size): string
    {
        return sprintf(self::ICON_HTML, $src, $alt, $title, intval($size));
    }
}
