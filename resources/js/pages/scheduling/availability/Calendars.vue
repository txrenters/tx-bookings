<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarPlus,
    CircleCheck,
    PlaneTakeoff,
    Plug,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AvailabilityTabs from '@/components/scheduling/AvailabilityTabs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { useCurrentTeam } from '@/composables/useCurrentTeam';
import { index } from '@/routes/availability';
import { connect, destroy, sync } from '@/routes/integrations';
import { update as updateCalendar } from '@/routes/integrations/calendars';

type CalendarRow = {
    id: number;
    name: string;
    isPrimary: boolean;
    checksConflicts: boolean;
    isWriteTarget: boolean;
};

type Leave = {
    startsAt: string;
    endsAt: string;
    message: string | null;
};

type Props = {
    providers: Array<{
        value: string;
        label: string;
        isConfigured: boolean;
        isConnected: boolean;
    }>;
    accounts: Array<{
        id: number;
        provider: string;
        providerLabel: string;
        email: string;
        lastSyncedAt: string | null;
        syncError: string | null;
        detectsLeave: boolean;
        leave: Leave | null;
        calendars: CalendarRow[];
    }>;
};

const props = defineProps<Props>();

const { teamSlug } = useCurrentTeam();

const connectableProviders = computed(() =>
    props.providers.filter((provider) => provider.isConfigured),
);

const formatSynced = (value: string | null) =>
    value ? new Date(value).toLocaleString() : 'Not yet';

/**
 * Leave ends at the minute the automatic reply is set to stop, which is often
 * mid morning rather than midnight, so the time is part of the answer: a bare
 * date reads as "the whole day is off" and the afternoon is bookable.
 */
const formatMoment = (value: string) =>
    new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

/**
 * Describe the leave the mailbox is announcing, from the reader's point of
 * view: away right now reads as a return time, away later as a range.
 */
const leaveLabel = (leave: Leave) =>
    new Date(leave.startsAt) <= new Date()
        ? `On leave until ${formatMoment(leave.endsAt)}`
        : `On leave ${formatMoment(leave.startsAt)} - ${formatMoment(leave.endsAt)}`;

const save = (calendar: CalendarRow, changes: Partial<CalendarRow>) => {
    router.patch(
        updateCalendar(calendar.id).url,
        {
            checks_conflicts:
                changes.checksConflicts ?? calendar.checksConflicts,
            is_write_target: changes.isWriteTarget ?? calendar.isWriteTarget,
        },
        { preserveScroll: true },
    );
};

setLayoutProps({
    breadcrumbs: [{ title: 'Availability', href: index(teamSlug.value) }],
});
</script>

<template>
    <Head title="Calendar settings" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Availability"
            description="When you can be booked, and which calendars are consulted."
        />

        <AvailabilityTabs />

        <div class="flex flex-wrap gap-3">
            <Button
                v-for="provider in connectableProviders"
                :key="provider.value"
                :variant="provider.isConnected ? 'secondary' : 'outline'"
                as-child
                :data-test="`connect-${provider.value}`"
            >
                <a :href="connect(provider.value).url">
                    <component
                        :is="provider.isConnected ? CircleCheck : Plug"
                        class="size-4"
                        :class="provider.isConnected ? 'text-primary' : ''"
                    />
                    <template v-if="provider.isConnected">
                        {{ provider.label }} connected
                    </template>
                    <template v-else> Connect {{ provider.label }} </template>
                </a>
            </Button>
        </div>

        <div v-if="accounts.length" class="flex flex-col gap-4">
            <section
                v-for="account in accounts"
                :key="account.id"
                class="rounded-xl border border-border bg-card shadow-flat"
                data-test="calendar-settings-account"
            >
                <header
                    class="flex flex-wrap items-start justify-between gap-3 border-b p-5"
                >
                    <div>
                        <p class="font-medium">{{ account.providerLabel }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ account.email }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Last synced:
                            {{ formatSynced(account.lastSyncedAt) }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="
                                router.post(
                                    sync(account.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            "
                        >
                            <RefreshCw class="size-3.5" /> Sync now
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            :data-test="`disconnect-${account.id}`"
                            @click="
                                router.delete(destroy(account.id).url, {
                                    preserveScroll: true,
                                })
                            "
                        >
                            <Trash2 class="size-3.5" /> Disconnect
                        </Button>
                    </div>
                </header>

                <p
                    v-if="account.syncError"
                    class="border-b border-destructive/40 bg-destructive/5 p-4 text-sm"
                >
                    {{ account.syncError }}
                </p>

                <div class="divide-y">
                    <div
                        v-for="calendar in account.calendars"
                        :key="calendar.id"
                        class="flex flex-wrap items-center justify-between gap-4 p-5"
                    >
                        <span class="flex items-center gap-2 font-medium">
                            {{ calendar.name }}
                            <Badge
                                v-if="calendar.isPrimary"
                                variant="secondary"
                            >
                                Primary
                            </Badge>
                        </span>

                        <div class="flex flex-wrap items-center gap-6">
                            <label class="flex items-center gap-2 text-sm">
                                <CalendarCheck
                                    class="size-4 text-muted-foreground"
                                />
                                <span class="text-muted-foreground">
                                    Check for conflicts
                                </span>
                                <Switch
                                    :model-value="calendar.checksConflicts"
                                    :data-test="`conflicts-${calendar.id}`"
                                    @update:model-value="
                                        (value) =>
                                            save(calendar, {
                                                checksConflicts: Boolean(value),
                                            })
                                    "
                                />
                            </label>

                            <label class="flex items-center gap-2 text-sm">
                                <CalendarPlus
                                    class="size-4 text-muted-foreground"
                                />
                                <span class="text-muted-foreground">
                                    Add meetings here
                                </span>
                                <Switch
                                    :model-value="calendar.isWriteTarget"
                                    :data-test="`write-target-${calendar.id}`"
                                    @update:model-value="
                                        (value) =>
                                            save(calendar, {
                                                isWriteTarget: Boolean(value),
                                            })
                                    "
                                />
                            </label>
                        </div>
                    </div>
                </div>

                <div
                    v-if="account.detectsLeave"
                    class="flex flex-wrap items-center justify-between gap-3 border-t p-5"
                    data-test="account-leave"
                >
                    <span class="flex items-center gap-2 text-sm">
                        <PlaneTakeoff
                            class="size-4"
                            :class="
                                account.leave
                                    ? 'text-primary'
                                    : 'text-muted-foreground'
                            "
                        />
                        <span v-if="account.leave" class="font-medium">
                            {{ leaveLabel(account.leave) }}
                        </span>
                        <span v-else class="text-muted-foreground">
                            No out of office reply switched on
                        </span>
                    </span>

                    <p class="max-w-md text-xs text-muted-foreground">
                        <template v-if="account.leave?.message">
                            &ldquo;{{ account.leave.message }}&rdquo;
                        </template>
                        <template v-else>
                            Turn on your Outlook automatic reply and we will
                            stop offering your time until you are back.
                        </template>
                    </p>
                </div>
            </section>
        </div>

        <div
            v-else
            class="rounded-xl border border-dashed p-12 text-center text-muted-foreground"
        >
            <Plug class="mx-auto mb-3 size-8 opacity-50" />
            <p>No calendars connected.</p>
            <p class="mt-1 text-sm">
                Connect one above so busy time is respected and meetings land in
                it.
            </p>
        </div>
    </div>
</template>
