<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarCheck,
    Check,
    Clock,
    Globe,
    RefreshCw,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const page = usePage();

const name = page.props.name;

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const features = [
    {
        icon: CalendarCheck,
        title: 'Share one link',
        body: 'Publish a booking page for every meeting you offer. Invitees pick from the times you are actually free — no back-and-forth email.',
    },
    {
        icon: RefreshCw,
        title: 'Stays in sync',
        body: 'Google Calendar and Microsoft 365 are read for conflicts and written to on every booking, so a busy block never becomes a double booking.',
    },
    {
        icon: Users,
        title: 'Works for a team',
        body: 'Round-robin to spread the load, collective for panels, or group events with seats. Host pools resolve from the group you point the event at.',
    },
    {
        icon: Globe,
        title: 'Right time, every time',
        body: 'Working hours live in your timezone, invitees see theirs, and everything is stored in UTC. Buffers and notice periods are respected.',
    },
];

/** Illustrative only — the hero mockup is decorative, not live data. */
const mockSlots = ['9:00 am', '9:30 am', '10:00 am', '11:30 am'];
</script>

<template>
    <Head title="Scheduling for TexasRenters" />

    <div class="min-h-svh bg-background">
        <header
            class="sticky top-0 z-40 border-b border-border bg-background/85 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
            >
                <Link
                    :href="'/'"
                    class="flex items-center gap-2.5 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <AppLogoIcon class="size-8" />
                    <span class="font-bold tracking-tight">{{ name }}</span>
                </Link>

                <nav class="flex items-center gap-2">
                    <Button v-if="page.props.auth.user" as-child>
                        <Link :href="dashboardUrl">Dashboard</Link>
                    </Button>
                    <Button
                        v-else
                        class="cursor-pointer font-semibold"
                        as-child
                    >
                        <Link :href="login()">Log in</Link>
                    </Button>
                </nav>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section
                class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-24"
            >
                <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <p
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold text-muted-foreground"
                        >
                            <span
                                class="size-1.5 rounded-full bg-success"
                                aria-hidden="true"
                            />
                            Scheduling for TexasRenters
                        </p>

                        <h1
                            class="mt-5 text-4xl font-bold tracking-tight text-balance sm:text-5xl"
                        >
                            Stop emailing back and forth to find a time
                        </h1>
                        <p
                            class="mt-5 max-w-prose text-lg leading-relaxed text-muted-foreground"
                        >
                            Share a link. Your calendar decides what is open,
                            the invitee picks a slot, and both calendars update
                            themselves.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Button
                                size="lg"
                                class="cursor-pointer font-semibold"
                                as-child
                            >
                                <Link
                                    :href="
                                        page.props.auth.user
                                            ? dashboardUrl
                                            : login().url
                                    "
                                >
                                    {{
                                        page.props.auth.user
                                            ? 'Go to dashboard'
                                            : 'Log in'
                                    }}
                                    <ArrowRight
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        </div>

                        <ul
                            class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground"
                        >
                            <li
                                v-for="item in [
                                    'Google & Microsoft calendars',
                                    'Team round-robin and panels',
                                    'Accounts are issued by invitation',
                                ]"
                                :key="item"
                                class="inline-flex items-center gap-1.5"
                            >
                                <Check
                                    class="size-4 shrink-0 text-success"
                                    aria-hidden="true"
                                />
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <!--
                      A static rendering of the real booking page, so the hero
                      shows the actual product rather than stock art. Decorative:
                      hidden from the accessibility tree.
                    -->
                    <div
                        class="rounded-2xl border border-border bg-card p-5 shadow-raised sm:p-6"
                        aria-hidden="true"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-border pb-4"
                        >
                            <AppLogoIcon class="size-9" />
                            <div>
                                <p class="text-sm font-semibold">
                                    Property tour
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    30 min · Video call
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-5 sm:grid-cols-[1fr_auto]">
                            <div>
                                <p class="mb-3 text-xs font-semibold">
                                    March 2026
                                </p>
                                <div
                                    class="grid grid-cols-7 gap-1 text-center text-[11px]"
                                >
                                    <span
                                        v-for="day in [
                                            'M',
                                            'T',
                                            'W',
                                            'T',
                                            'F',
                                            'S',
                                            'S',
                                        ]"
                                        :key="day"
                                        class="py-1 font-medium text-muted-foreground"
                                    >
                                        {{ day }}
                                    </span>
                                    <span
                                        v-for="day in 28"
                                        :key="day"
                                        :class="[
                                            'flex aspect-square items-center justify-center rounded-full',
                                            day === 12
                                                ? 'bg-primary font-semibold text-primary-foreground'
                                                : [
                                                        4, 5, 11, 18, 19, 25,
                                                    ].includes(day)
                                                  ? 'bg-accent font-semibold text-accent-foreground'
                                                  : 'text-muted-foreground/50',
                                        ]"
                                    >
                                        {{ day }}
                                    </span>
                                </div>
                            </div>

                            <div class="w-full sm:w-28">
                                <p class="mb-3 text-xs font-semibold">Thu 12</p>
                                <div class="grid gap-2">
                                    <span
                                        v-for="(slot, index) in mockSlots"
                                        :key="slot"
                                        :class="[
                                            'rounded-lg border py-2 text-center text-xs font-semibold',
                                            index === 1
                                                ? 'border-foreground bg-foreground text-background'
                                                : 'border-primary/40 text-primary',
                                        ]"
                                    >
                                        {{ slot }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section
                class="border-y border-border bg-card"
                aria-labelledby="features-heading"
            >
                <div
                    class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-20"
                >
                    <h2
                        id="features-heading"
                        class="text-2xl font-bold tracking-tight text-balance sm:text-3xl"
                    >
                        Everything the back-and-forth was doing, automatically
                    </h2>

                    <ul class="mt-10 grid gap-8 sm:grid-cols-2">
                        <li v-for="feature in features" :key="feature.title">
                            <span
                                class="flex size-10 items-center justify-center rounded-lg bg-accent text-accent-foreground"
                            >
                                <component
                                    :is="feature.icon"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <h3 class="mt-4 font-semibold">
                                {{ feature.title }}
                            </h3>
                            <p
                                class="mt-1.5 max-w-prose text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ feature.body }}
                            </p>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Closing CTA -->
            <section
                class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-24"
            >
                <div
                    class="rounded-2xl bg-primary px-6 py-12 text-center text-primary-foreground sm:px-12"
                >
                    <Clock
                        class="mx-auto size-7 opacity-80"
                        aria-hidden="true"
                    />
                    <h2
                        class="mt-4 text-2xl font-bold tracking-tight text-balance sm:text-3xl"
                    >
                        Give people one link instead of six emails
                    </h2>
                    <p
                        class="mx-auto mt-3 max-w-prose leading-relaxed opacity-90"
                    >
                        Set your hours once. Everything after that runs itself.
                    </p>
                    <Button
                        size="lg"
                        variant="secondary"
                        class="mt-8 cursor-pointer font-semibold"
                        as-child
                    >
                        <Link
                            :href="
                                page.props.auth.user
                                    ? dashboardUrl
                                    : login().url
                            "
                        >
                            {{
                                page.props.auth.user
                                    ? 'Go to dashboard'
                                    : 'Log in'
                            }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
            </section>
        </main>

        <footer class="border-t border-border">
            <div
                class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:px-6"
            >
                <div class="flex items-center gap-2.5">
                    <AppLogoIcon class="size-6" />
                    <span class="font-medium text-foreground">{{ name }}</span>
                </div>
                <p>&copy; {{ new Date().getFullYear() }} TexasRenters.com</p>
            </div>
        </footer>
    </div>
</template>
