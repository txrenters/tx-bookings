<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    ChevronDown,
    Copy,
    Crown,
    ExternalLink,
    EyeOff,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CreateEventTypePanel from '@/components/scheduling/CreateEventTypePanel.vue';
import ScopePicker from '@/components/scheduling/ScopePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCurrentTeam } from '@/composables/useCurrentTeam';
import { destroy, edit, index } from '@/routes/scheduling';

type EventTypeRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string;
    kind: string;
    kindLabel: string;
    durationMinutes: number;
    availabilitySummary: string;
    isShared: boolean;
    locationLabel: string;
    isActive: boolean;
    isHidden: boolean;
    upcomingBookings: number;
    ownerName: string;
    ownerLandingUrl: string;
    hosts: Array<{ id: number; name: string; initial: string }>;
    publicUrl: string;
};

type Kind = {
    value: string;
    label: string;
    flow: string;
    description: string;
    requiresTeam: boolean;
};

type Props = {
    eventTypes: EventTypeRow[];
    canCreate: boolean;
    kinds: Kind[];
    locationTypes: any[];
    schedules: any[];
    teamMembers: any[];
    isPersonalTeam: boolean;
    currentUser: { id: number; name: string };
    groups: Array<{ id: number; name: string; memberNames: string[] }>;
    canAssignOwner: boolean;
    scope: string;
    scopeOptions: {
        primary: Array<{
            value: string;
            label: string;
            initial: string | null;
        }>;
        groups: Array<{ value: string; label: string; initial: string | null }>;
        users: Array<{ value: string; label: string; initial: string | null }>;
    };
};

const props = defineProps<Props>();

const { teamSlug } = useCurrentTeam();

const deleting = ref<EventTypeRow | null>(null);
const search = ref('');
const kindFilter = ref('all');

const filtered = computed(() =>
    props.eventTypes.filter((eventType) => {
        const term = search.value.trim().toLowerCase();
        const matchesTerm =
            term === '' ||
            eventType.name.toLowerCase().includes(term) ||
            eventType.ownerName.toLowerCase().includes(term);

        const matchesKind =
            kindFilter.value === 'all' || eventType.kind === kindFilter.value;

        return matchesTerm && matchesKind;
    }),
);

/** Event types listed under whoever owns them, the way Calendly groups them. */
const grouped = computed(() => {
    const sections = new Map<
        string,
        { name: string; landingUrl: string; eventTypes: EventTypeRow[] }
    >();

    for (const eventType of filtered.value) {
        const section = sections.get(eventType.ownerName) ?? {
            name: eventType.ownerName,
            landingUrl: eventType.ownerLandingUrl,
            eventTypes: [],
        };

        section.eventTypes.push(eventType);
        sections.set(eventType.ownerName, section);
    }

    return [...sections.values()].sort((a, b) => a.name.localeCompare(b.name));
});

const applyScope = (value: string) => {
    router.get(
        index(teamSlug.value).url,
        { scope: value },
        { preserveState: true, preserveScroll: true },
    );
};

/** The kind being created, which also drives the side panel's visibility. */
const creatingKind = ref<string | null>(null);

/*
  Only offer a filter for a kind you could actually be looking at: the ones
  creatable here, plus any kind already on the list. A personal organization
  cannot make round robin or collective event types, so filtering by them
  would always come back empty.
*/
const filterableKinds = computed(() => {
    const present = new Set(
        props.eventTypes.map((eventType) => eventType.kind),
    );

    return props.kinds.filter(
        (kind) =>
            present.has(kind.value) ||
            !kind.requiresTeam ||
            !props.isPersonalTeam,
    );
});

const copyLink = async (url: string) => {
    await navigator.clipboard.writeText(url);
    toast.success('Booking link copied');
};

const confirmDelete = () => {
    if (!deleting.value) {
        return;
    }

    router.delete(
        destroy({
            current_team: teamSlug.value,
            event_type: deleting.value.slug,
        }).url,
        {
            onFinish: () => (deleting.value = null),
        },
    );
};

setLayoutProps({
    breadcrumbs: [{ title: 'Scheduling', href: index(teamSlug.value) }],
});
</script>

<template>
    <Head title="Scheduling" />

    <div class="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Scheduling</h1>

            <DropdownMenu v-if="canCreate">
                <DropdownMenuTrigger as-child>
                    <Button data-test="new-event-type">
                        <Plus /> Create
                        <ChevronDown class="size-4 opacity-70" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-72">
                    <DropdownMenuLabel class="text-muted-foreground">
                        Event type
                    </DropdownMenuLabel>
                    <!--
                      Team-only kinds stay listed but disabled on a personal
                      organization: hiding them just raises the question of why
                      only two of the four are here.
                    -->
                    <DropdownMenuItem
                        v-for="kind in kinds"
                        :key="kind.value"
                        class="flex-col items-start gap-0.5 py-2.5"
                        :disabled="kind.requiresTeam && isPersonalTeam"
                        :data-test="`new-event-type-${kind.value}`"
                        @select="creatingKind = kind.value"
                    >
                        <span class="font-medium text-primary">
                            {{ kind.label }}
                        </span>
                        <span class="text-sm">{{ kind.flow }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{
                                kind.requiresTeam && isPersonalTeam
                                    ? 'Needs a shared organization with more than one member.'
                                    : kind.description
                            }}
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <ScopePicker
                :model-value="scope"
                :options="scopeOptions"
                @update:model-value="applyScope"
            />

            <div class="relative min-w-56 flex-1">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Search users or event types"
                    data-test="search-event-types"
                />
            </div>

            <Select v-model="kindFilter">
                <SelectTrigger class="w-44" data-test="filter-event-types">
                    <SlidersHorizontal class="size-4 opacity-70" />
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All types</SelectItem>
                    <SelectItem
                        v-for="kind in filterableKinds"
                        :key="kind.value"
                        :value="kind.value"
                    >
                        {{ kind.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div v-if="grouped.length" class="flex flex-col gap-6">
            <section v-for="group in grouped" :key="group.name">
                <header class="mb-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-medium"
                        >
                            {{ group.name.charAt(0) }}
                        </span>
                        <span class="font-semibold">{{ group.name }}</span>
                    </div>

                    <Button
                        variant="link"
                        size="sm"
                        class="h-auto p-0"
                        as-child
                    >
                        <a :href="group.landingUrl" target="_blank">
                            View landing page
                            <ExternalLink class="size-3.5" />
                        </a>
                    </Button>
                </header>

                <div class="flex flex-col gap-3">
                    <article
                        v-for="eventType in group.eventTypes"
                        :key="eventType.id"
                        data-test="event-type-row"
                        class="flex items-center gap-4 overflow-hidden rounded-lg border bg-background"
                    >
                        <span
                            class="w-1.5 self-stretch"
                            :style="{ backgroundColor: eventType.color }"
                        />

                        <div class="min-w-0 flex-1 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="
                                        edit({
                                            current_team: teamSlug,
                                            event_type: eventType.slug,
                                        })
                                    "
                                    class="font-semibold hover:underline"
                                >
                                    {{ eventType.name }}
                                </Link>
                                <Crown
                                    v-if="eventType.isShared"
                                    class="size-3.5 text-muted-foreground"
                                    aria-label="Organization event type"
                                />
                                <Badge
                                    v-if="!eventType.isActive"
                                    variant="outline"
                                >
                                    Off
                                </Badge>
                                <EyeOff
                                    v-if="eventType.isHidden"
                                    class="size-3.5 text-muted-foreground"
                                    aria-label="Hidden from the booking page"
                                />
                            </div>

                            <p class="mt-0.5 text-sm text-muted-foreground">
                                {{ eventType.durationMinutes }} min &middot;
                                {{ eventType.locationLabel }} &middot;
                                {{ eventType.kindLabel }}
                            </p>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                {{ eventType.availabilitySummary }}
                            </p>
                        </div>

                        <div class="flex shrink-0 -space-x-2 pr-2">
                            <span
                                v-for="host in eventType.hosts.slice(0, 3)"
                                :key="host.id"
                                :title="host.name"
                                class="flex size-7 items-center justify-center rounded-full border-2 border-background bg-muted text-xs font-medium"
                            >
                                {{ host.initial }}
                            </span>
                            <span
                                v-if="eventType.hosts.length > 3"
                                class="flex size-7 items-center justify-center rounded-full border-2 border-background bg-muted text-xs font-medium"
                            >
                                +{{ eventType.hosts.length - 3 }}
                            </span>
                        </div>

                        <div class="flex shrink-0 items-center gap-1 pr-4">
                            <Button
                                variant="outline"
                                size="sm"
                                class="rounded-full"
                                @click="copyLink(eventType.publicUrl)"
                            >
                                <Copy class="size-3.5" /> Copy link
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Preview ${eventType.name}`"
                                as-child
                            >
                                <a :href="eventType.publicUrl" target="_blank">
                                    <ExternalLink class="size-4" />
                                </a>
                            </Button>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`More actions for ${eventType.name}`"
                                        :data-test="`event-type-menu-${eventType.slug}`"
                                    >
                                        <MoreVertical class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem as-child>
                                        <Link
                                            :href="
                                                edit({
                                                    current_team: teamSlug,
                                                    event_type: eventType.slug,
                                                })
                                            "
                                        >
                                            <Pencil class="size-4" /> Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @select="deleting = eventType"
                                    >
                                        <Trash2 class="size-4" /> Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <div
            v-else-if="eventTypes.length"
            class="rounded-lg border border-dashed p-12 text-center text-muted-foreground"
        >
            Nothing matches that search.
        </div>

        <div
            v-else
            class="rounded-lg border border-dashed p-12 text-center text-muted-foreground"
        >
            <p>No event types yet.</p>
            <Button
                v-if="canCreate"
                class="mt-4"
                data-test="create-first-event-type"
                @click="creatingKind = 'one_on_one'"
            >
                Create your first one
            </Button>
        </div>
    </div>

    <CreateEventTypePanel
        :kind="creatingKind"
        :team-slug="teamSlug"
        :kinds="kinds"
        :location-types="locationTypes"
        :schedules="schedules"
        :team-members="teamMembers"
        :current-user="currentUser"
        :groups="groups"
        :can-assign-owner="canAssignOwner"
        @close="creatingKind = null"
    />

    <Dialog :open="deleting !== null" @update:open="deleting = null">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete {{ deleting?.name }}?</DialogTitle>
                <DialogDescription>
                    The booking link stops working immediately. Existing
                    bookings are kept.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">
                    Keep it
                </Button>
                <Button
                    variant="destructive"
                    data-test="confirm-delete-event-type"
                    @click="confirmDelete"
                >
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
