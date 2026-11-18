<?php

namespace App\Service;

use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Cookie;

class ChangelogService
{
    private const CHANGELOG_PATH = '/config/Core/changelog.json';

    /** The permission value in the changelog that only admin roles have */
    private const ADMIN_ONLY = 'admin';

    /** The cookie holding the latest version of the changelog of each module the browser has looked at */
    private const SEEN_COOKIE = 'changelog_seen';

    /**
     * Gets the releases of the changelog, newest first, with only the changes the user may see
     * @param User|null $user The user looking
     * @param string|null $module Only include the changes of the module with this name
     * @param string|null $submodule Only include the changes of the submodule with this name
     * @return array[] The releases, with their modules grouped per submodule ('' for changes without one)
     */
    public function getReleases(?User $user, ?string $module = null, ?string $submodule = null): array
    {
        $json = Storage::json(self::CHANGELOG_PATH);
        $releases = is_array($json) ? array_values(array_filter($json, 'is_array')) : [];

        usort($releases, fn (array $a, array $b) => [$b['date'] ?? '', $b['version'] ?? ''] <=> [$a['date'] ?? '', $a['version'] ?? '']
            ?: version_compare($b['version'] ?? '0', $a['version'] ?? '0'));

        return array_values(array_filter(array_map(fn (array $release) => $this->filterRelease($release, $user, $module, $submodule), $releases)));
    }

    /**
     * Gets the newest release with changes for a module or submodule that the user may see
     * @param User|null $user The user looking
     * @param string $module The name of the module
     * @param string|null $submodule The name of the submodule, or null for the whole module
     * @return array|null The release, or null when there is none
     */
    public function getLatestRelease(?User $user, string $module, ?string $submodule = null): ?array
    {
        return $this->getReleases($user, $module, $submodule)[0] ?? null;
    }

    /**
     * Gets the key the seen releases are stored under for a module or submodule
     * @param string $moduleCode The code of the module
     * @param string|null $submoduleCode The code of the submodule
     * @return string The key
     */
    public function getSeenKey(string $moduleCode, ?string $submoduleCode = null): string
    {
        return $submoduleCode === null ? $moduleCode : sprintf('%s.%s', $moduleCode, $submoduleCode);
    }

    /**
     * Checks whether the browser has already looked at a version of the changelog of a module or submodule
     * @param Request $request The request
     * @param string $seenKey The key of the module or submodule
     * @param string $version The version
     * @return bool Whether it has
     */
    public function hasSeen(Request $request, string $seenKey, string $version): bool
    {
        return ($this->getSeen($request)[$seenKey] ?? null) === $version;
    }

    /**
     * Creates the cookie that remembers a version of the changelog of a module or submodule was looked at
     * @param Request $request The request
     * @param string $seenKey The key of the module or submodule
     * @param string $version The version
     * @return Cookie The cookie
     */
    public function makeSeenCookie(Request $request, string $seenKey, string $version): Cookie
    {
        $seen = array_merge($this->getSeen($request), [$seenKey => $version]);

        return cookie(self::SEEN_COOKIE, json_encode($seen), 60 * 24 * 365);
    }

    /**
     * Gets the versions of the changelog the browser has looked at, per module or submodule
     * @param Request $request The request
     * @return array<string, string> The versions
     */
    private function getSeen(Request $request): array
    {
        $seen = json_decode((string) $request->cookie(self::SEEN_COOKIE), true);

        return is_array($seen) ? array_filter($seen, 'is_string') : [];
    }

    /**
     * Removes the changes from a release that the user may not see, or that are for another module
     * @param array $release The release
     * @param User|null $user The user looking
     * @param string|null $onlyModule Only keep the changes of the module with this name
     * @param string|null $onlySubmodule Only keep the changes of the submodule with this name
     * @return array|null The release with only the visible changes, grouped per module and submodule, or null when none are left
     */
    private function filterRelease(array $release, ?User $user, ?string $onlyModule, ?string $onlySubmodule): ?array
    {
        $modules = [];
        foreach ($release['modules'] ?? [] as $module => $groups) {
            if ($onlyModule !== null && $module !== $onlyModule) {
                continue;
            }

            $groups = (array) $groups;
            $visible = [];
            foreach (array_is_list($groups) ? ['' => $groups] : $groups as $submodule => $group) {
                if ($onlySubmodule !== null && (string) $submodule !== $onlySubmodule) {
                    continue;
                }

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
