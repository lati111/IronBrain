<?php

namespace App\Http\Controllers;

use App\Models\Auth\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ChangelogController extends Controller
{
    private const CHANGELOG_PATH = '/config/Core/changelog.json';

    /** The permission value in the changelog that only admin roles have */
    private const ADMIN_ONLY = 'admin';

    /**
     * Show the changelog page, newest version first, with only the changes the user may see
     * @return View The changelog page
     */
    public function show(): View
    {
        $json = Storage::json(self::CHANGELOG_PATH);
        $releases = is_array($json) ? array_values(array_filter($json, 'is_array')) : [];

        usort($releases, fn (array $a, array $b) => [$b['date'] ?? '', $b['version'] ?? ''] <=> [$a['date'] ?? '', $a['version'] ?? '']
            ?: version_compare($b['version'] ?? '0', $a['version'] ?? '0'));

        $user = Auth::user();
        $releases = array_values(array_filter(array_map(fn (array $release) => $this->filterRelease($release, $user), $releases)));

        return $this->view('changelog', ['releases' => $releases]);
    }

    /**
     * Removes the changes from a release that the user may not see
     * @param array $release The release
     * @param User|null $user The user looking
     * @return array|null The release with only the visible changes, grouped per module and submodule, or null when none are left
     */
    private function filterRelease(array $release, ?User $user): ?array
    {
        $modules = [];
        foreach ($release['modules'] ?? [] as $module => $groups) {
            $groups = (array) $groups;
            $visible = [];
            foreach (array_is_list($groups) ? ['' => $groups] : $groups as $submodule => $group) {
                $changes = $this->filterChanges((array) $group, $user);
                if ($changes !== []) {
                    $visible[$submodule] = $changes;
                }
            }

            if ($visible !== []) {
                $modules[$module] = $visible;
            }
        }

        return $modules === [] ? null : array_merge($release, ['modules' => $modules]);
    }

    /**
     * Gets the texts of the changes in a group that the user may see. A group is a list of changes, or an object with
     * a permission and its changes, and a change is a text or an object with a text and a permission
     * @param array $group The group
     * @param User|null $user The user looking
     * @return string[] The visible change texts
     */
    private function filterChanges(array $group, ?User $user): array
    {
        if (!array_is_list($group)) {
            if (!$this->mayView($group['permission'] ?? null, $user)) {
                return [];
            }
            $group = (array) ($group['changes'] ?? []);
        }

        $changes = [];
        foreach ($group as $change) {
            if (is_array($change)) {
                if (!$this->mayView($change['permission'] ?? null, $user)) {
                    continue;
                }
                $change = $change['text'] ?? '';
            }

            if (is_string($change) && $change !== '') {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    /**
     * Checks whether a user may see something that needs a permission
     * @param string|null $permission The permission, "admin" for admin roles only, or null for everyone
     * @param User|null $user The user looking
     * @return bool Whether they may see it
     */
    private function mayView(?string $permission, ?User $user): bool
    {
        if ($permission === null) {
            return true;
        }
        if ($user === null) {
            return false;
        }

        return $permission === self::ADMIN_ONLY
            ? (bool) $user->role()->first()?->is_admin
            : $user->hasPermission($permission);
    }
}
