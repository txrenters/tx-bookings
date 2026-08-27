<?php

namespace App\Services\Scheduling;

use App\Models\Group;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The "All Users & Teams" picker shared by the scheduling and meetings lists.
 *
 * A scope is one of: "all", "mine", "user:{id}" or "group:{id}".
 */
class ScopeFilter
{
    /**
     * Build the options offered in the picker.
     *
     * @return array<string, mixed>
     */
    public function options(Team $team, User $viewer): array
    {
        return [
            'primary' => [
                ['value' => 'mine', 'label' => 'My '.config('app.name'), 'initial' => $this->initial($viewer->name)],
                ['value' => 'all', 'label' => 'All Users & Teams', 'initial' => null],
            ],
            'groups' => $team->groups()->orderBy('name')->get()
                ->map(fn (Group $group) => [
                    'value' => "group:{$group->id}",
                    'label' => $group->name,
                    'initial' => $this->initial($group->name),
                ])->values(),
            'users' => $team->members()->orderBy('name')->get()
                ->map(fn (User $member) => [
                    'value' => "user:{$member->id}",
                    'label' => $member->name,
                    'initial' => $this->initial($member->name),
                ])->values(),
        ];
    }

    /**
     * Get the user ids a scope resolves to, or null when it means everyone.
     *
     * @return array<int, int>|null
     */
    public function userIds(string $scope, Team $team, User $viewer): ?array
    {
        if ($scope === 'mine') {
            return [$viewer->id];
        }

        if (str_starts_with($scope, 'user:')) {
            $id = (int) Str::after($scope, 'user:');

            return $team->members()->whereKey($id)->exists() ? [$id] : [$viewer->id];
        }

        if (str_starts_with($scope, 'group:')) {
            $group = $team->groups()->whereKey((int) Str::after($scope, 'group:'))->first();

            return $group?->members()->pluck('users.id')->all() ?? [];
        }

        return null;
    }

    /**
     * Get the group a scope points at, if any.
     */
    public function group(string $scope, Team $team): ?Group
    {
        if (! str_starts_with($scope, 'group:')) {
            return null;
        }

        return $team->groups()->whereKey((int) Str::after($scope, 'group:'))->first();
    }

    /**
     * Constrain a query of things that have a host to the given scope.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, int>|null  $userIds
     * @return Builder<TModel>
     */
    public function applyToHosts(Builder $query, ?array $userIds, string $hostsRelation): Builder
    {
        if ($userIds === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($userIds, $hostsRelation) {
            $query->whereIn('user_id', $userIds)
                ->orWhereHas($hostsRelation, fn (Builder $hosts) => $hosts->whereIn('users.id', $userIds));
        });
    }

    /**
     * Get the initial shown in the picker's avatar.
     */
    protected function initial(string $name): string
    {
        return strtoupper(mb_substr(trim($name), 0, 1));
    }

    /**
     * Get every scope value that is valid for the team.
     *
     * @return Collection<int, string>
     */
    public function validValues(Team $team): Collection
    {
        return collect(['all', 'mine'])
            ->merge($team->groups()->pluck('id')->map(fn ($id) => "group:{$id}"))
            ->merge($team->members()->pluck('users.id')->map(fn ($id) => "user:{$id}"));
    }
}
