import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

export type UseCurrentTeamReturn = {
    teamSlug: ComputedRef<string>;
};

/**
 * The current team's slug, which every team scoped route needs as its first
 * parameter. The server fills this in via URL::defaults, but the generated
 * Wayfinder helpers still expect it to be passed explicitly.
 */
export function useCurrentTeam(): UseCurrentTeamReturn {
    const page = usePage();

    const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

    return { teamSlug };
}
