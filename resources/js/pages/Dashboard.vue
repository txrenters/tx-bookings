<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarClock,
    CalendarDays,
    Check,
    Copy,
    ExternalLink,
    LayoutList,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes';
import { index as meetingsIndex } from '@/routes/meetings';
import { index as schedulingIndex } from '@/routes/scheduling';
import type { DashboardInvitation, Team } from '@/types';

type UpcomingMeeting = {
    uid: string;
    name: string;
    eventTypeName: string | null;
    color: string | null;
    startsAt: string;
    dayLabel: string;
    timeLabel: string;
    isToday: boolean;
};

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    greetingName: string;
    bookingUrl: string | null;
    currentTeam?: Team | null;
    stats?: { upcoming: number; thisWeek: number; eventTypes: number };
    upcoming?: UpcomingMeeting[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const teamSlug = computed(() => props.currentTeam?.slug ?? '');

const copied = ref(false);

const copyBookingLink = async () => {
    if (!props.bookingUrl) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.bookingUrl);
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard access can be denied; the link stays visible and selectable.
        copied.value = false;
    }
};

const statCards = computed(() => [
    {
        key: 'upcoming',
        label: 'Upcoming meetings',
        value: props.stats?.upcoming,
        icon: CalendarClock,
        href: teamSlug.value ? meetingsIndex(teamSlug.value).url : undefined,
    },
    {
        key: 'thisWeek',
        label: 'Booked this week',
        value: props.stats?.thisWeek,
        icon: CalendarDays,
        href: teamSlug.value ? meetingsIndex(teamSlug.value).url : undefined,
    },
    {
        key: 'eventTypes',
        label: 'Active event types',
        value: props.stats?.eventTypes,
        icon: LayoutList,
        href: teamSlug.value ? schedulingIndex(teamSlug.value).url : undefined,
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <header>
            <h1 class="text-2xl font-bold tracking-tight">
                Good to see you, {{ greetingName }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Here is what your calendar looks like right now.
            </p>
        </header>

        <!-- Share link -->
        <section
            v-if="bookingUrl"
            class="rounded-xl border border-border bg-card p-5 shadow-flat"
            aria-labelledby="share-heading"
        >
            <h2 id="share-heading" class="text-sm font-semibold">
                Your booking link
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Send this to anyone who needs time with you.
            </p>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <code
                    class="min-w-0 flex-1 rounded-lg border border-border bg-muted px-3 py-2.5 text-sm"
                    data-wrap-anywhere
                >
                    {{ bookingUrl }}
                </code>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        class="cursor-pointer bg-card font-medium"
                        @click="copyBookingLink"
                    >
                        <Check
                            v-if="copied"
                            class="size-4 text-success"
                            aria-hidden="true"
                        />
                        <Copy v-else class="size-4" aria-hidden="true" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </Button>
                    <Button
                        variant="ghost"
                        class="cursor-pointer font-medium"
                        as-child
                    >
                        <a
                            :href="bookingUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Preview
                            <ExternalLink
                                class="size-4"
                                aria-hidden="true"
                            />
                        </a>
                    </Button>
                </div>
            </div>
            <!-- Announced without moving focus when the copy succeeds. -->
            <p aria-live="polite" class="sr-only">
                {{ copied ? 'Booking link copied to clipboard' : '' }}
            </p>
        </section>

        <!-- Counters -->
        <section aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="sr-only">At a glance</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <component
                    :is="card.href ? Link : 'div'"
                    v-for="card in statCards"
                    :key="card.key"
                    :href="card.href"
                    class="rounded-xl border border-border bg-card p-5 shadow-flat transition-colors duration-200"
                    :class="
                        card.href
                            ? 'cursor-pointer hover:border-primary/40 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none'
                            : ''
                    "
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-muted-foreground">
                            {{ card.label }}
                        </span>
                        <component
                            :is="card.icon"
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <Skeleton
                        v-if="card.value === undefined"
                        class="mt-3 h-8 w-12 animate-pulse rounded-md"
                    />
                    <p
                        v-else
                        class="mt-2 text-3xl font-bold tracking-tight"
                        data-numeric
                    >
                        {{ card.value }}
                    </p>
                </component>
            </div>
        </section>

        <!-- Next up -->
        <section
            class="rounded-xl border border-border bg-card shadow-flat"
            aria-labelledby="upcoming-heading"
        >
            <div
                class="flex items-center justify-between gap-3 border-b border-border p-5"
            >
                <h2 id="upcoming-heading" class="text-sm font-semibold">
                    Next up
                </h2>
                <Button
                    v-if="teamSlug"
                    variant="ghost"
                    size="sm"
                    class="cursor-pointer font-medium"
                    as-child
                >
                    <Link :href="meetingsIndex(teamSlug)">
                        All meetings
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>

            <div v-if="upcoming === undefined" class="space-y-3 p-5">
                <Skeleton
                    v-for="index in 3"
                    :key="index"
                    class="h-14 w-full animate-pulse rounded-lg"
                />
                <span class="sr-only">Loading upcoming meetings</span>
            </div>

            <ul v-else-if="upcoming.length" class="divide-y divide-border">
                <li
                    v-for="meeting in upcoming"
                    :key="meeting.uid"
                    class="flex items-center gap-4 p-5"
                >
                    <span
                        class="h-10 w-1.5 shrink-0 rounded-full"
                        :style="{
                            backgroundColor: meeting.color ?? undefined,
                        }"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium" data-wrap-anywhere>
                            {{ meeting.eventTypeName }}
                        </p>
                        <p class="truncate text-sm text-muted-foreground">
                            with {{ meeting.name }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold" data-numeric>
                            {{ meeting.timeLabel }}
                        </p>
                        <p
                            class="text-xs"
                            :class="
                                meeting.isToday
                                    ? 'font-semibold text-success'
                                    : 'text-muted-foreground'
                            "
                            data-numeric
                        >
                            {{ meeting.isToday ? 'Today' : meeting.dayLabel }}
                        </p>
                    </div>
                </li>
            </ul>

            <div v-else class="px-5 py-12 text-center">
                <CalendarClock
                    class="mx-auto size-6 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="mt-3 font-medium">Nothing booked yet</p>
                <p
                    class="mx-auto mt-1 max-w-sm text-sm leading-relaxed text-muted-foreground"
                >
                    Once someone books a time with you it shows up here. Share
                    your booking link to get the first one.
                </p>
                <Button
                    v-if="teamSlug"
                    class="mt-5 cursor-pointer font-semibold"
                    as-child
                >
                    <Link :href="schedulingIndex(teamSlug)">
                        Set up an event type
                    </Link>
                </Button>
            </div>
        </section>
    </div>
</template>
