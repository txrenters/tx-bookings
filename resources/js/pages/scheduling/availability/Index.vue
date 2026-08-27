<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarPlus,
    ChevronDown,
    Copy,
    MoreVertical,
    Plus,
    Repeat,
    Star,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import AvailabilityTabs from '@/components/scheduling/AvailabilityTabs.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCurrentTeam } from '@/composables/useCurrentTeam';
import { destroy, index, store, update } from '@/routes/availability';

type Rule = { day_of_week: number; starts_at: string; ends_at: string };

type Override = {
    date: string;
    is_unavailable: boolean;
    starts_at: string | null;
    ends_at: string | null;
};

type Schedule = {
    id: number;
    name: string;
    timezone: string;
    isDefault: boolean;
    eventTypeCount: number;
    summary: string | null;
    rules: Rule[];
    overrides: Override[];
};

type Props = {
    schedules: Schedule[];
    timezones: string[];
};

const props = defineProps<Props>();

const { teamSlug } = useCurrentTeam();

/** Sunday first, matching how the week reads on the schedule card. */
const days = [
    { value: 0, initial: 'S', name: 'Sunday' },
    { value: 1, initial: 'M', name: 'Monday' },
    { value: 2, initial: 'T', name: 'Tuesday' },
    { value: 3, initial: 'W', name: 'Wednesday' },
    { value: 4, initial: 'T', name: 'Thursday' },
    { value: 5, initial: 'F', name: 'Friday' },
    { value: 6, initial: 'S', name: 'Saturday' },
];

const selectedId = ref<number | null>(props.schedules[0]?.id ?? null);
const creating = ref(false);
const deleting = ref(false);

const selected = computed(
    () =>
        props.schedules.find((schedule) => schedule.id === selectedId.value) ??
        null,
);

const buildForm = (schedule: Schedule | null) =>
    useForm({
        name: schedule?.name ?? 'Working hours',
        timezone:
            schedule?.timezone ??
            Intl.DateTimeFormat().resolvedOptions().timeZone ??
            'UTC',
        is_default: schedule?.isDefault ?? props.schedules.length === 0,
        rules: schedule ? schedule.rules.map((rule) => ({ ...rule })) : [],
        overrides: schedule
            ? schedule.overrides.map((override) => ({ ...override }))
            : [],
    });

const form = ref(buildForm(selected.value));

const selectSchedule = (schedule: Schedule) => {
    creating.value = false;
    selectedId.value = schedule.id;
    form.value = buildForm(schedule);
};

const startCreating = () => {
    creating.value = true;
    selectedId.value = null;
    form.value = buildForm(null);
};

const rulesForDay = (day: number) =>
    form.value.rules.filter((rule) => rule.day_of_week === day);

const addBlock = (day: number) => {
    const existing = rulesForDay(day);

    form.value.rules.push({
        day_of_week: day,
        starts_at: existing.length ? '13:00' : '09:00',
        ends_at: existing.length ? '17:00' : '12:00',
    });
};

const removeRule = (rule: Rule) => {
    form.value.rules.splice(form.value.rules.indexOf(rule), 1);
};

/** Copy a day's hours onto every other day of the week. */
const copyToAllDays = (day: number) => {
    const source = rulesForDay(day);

    if (source.length === 0) {
        return;
    }

    form.value.rules = days.flatMap((target) =>
        source.map((rule) => ({ ...rule, day_of_week: target.value })),
    );
};

const addOverride = () => {
    form.value.overrides.push({
        date: new Date().toISOString().slice(0, 10),
        is_unavailable: true,
        starts_at: null,
        ends_at: null,
    });
};

const errorFor = (key: string) =>
    (form.value.errors as Record<string, string>)[key];

const submit = () => {
    if (selected.value) {
        form.value.patch(
            update({
                current_team: teamSlug.value,
                availability: selected.value.id,
            }).url,
            { preserveScroll: true },
        );

        return;
    }

    form.value.post(store(teamSlug.value).url, {
        preserveScroll: true,
        onSuccess: () => (creating.value = false),
    });
};

const makeDefault = () => {
    form.value.is_default = true;
    submit();
};

const confirmDelete = () => {
    if (!selected.value) {
        return;
    }

    router.delete(
        destroy({
            current_team: teamSlug.value,
            availability: selected.value.id,
        }).url,
        {
            onSuccess: () => {
                deleting.value = false;
                selectedId.value = props.schedules[0]?.id ?? null;
                form.value = buildForm(selected.value);
            },
        },
    );
};

setLayoutProps({
    breadcrumbs: [{ title: 'Availability', href: index(teamSlug.value) }],
});
</script>

<template>
    <Head title="Availability" />

    <div class="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
        <h1 class="text-2xl font-semibold tracking-tight">Availability</h1>

        <AvailabilityTabs />

        <div class="rounded-xl border">
            <!-- Which schedule is being edited. -->
            <div
                class="flex flex-wrap items-start justify-between gap-4 border-b p-6"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium text-muted-foreground">
                        Schedule
                    </p>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="mt-1 flex cursor-pointer items-center gap-1.5 text-lg font-semibold text-primary"
                                data-test="schedule-switcher"
                            >
                                {{ creating ? 'New schedule' : selected?.name }}
                                <span
                                    v-if="selected?.isDefault && !creating"
                                    class="font-normal"
                                >
                                    (default)
                                </span>
                                <ChevronDown class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-64">
                            <DropdownMenuItem
                                v-for="schedule in schedules"
                                :key="schedule.id"
                                :data-test="`schedule-${schedule.id}`"
                                @select="selectSchedule(schedule)"
                            >
                                <Star
                                    v-if="schedule.isDefault"
                                    class="size-3.5 fill-current text-primary"
                                />
                                {{ schedule.name }}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator v-if="schedules.length" />
                            <DropdownMenuItem
                                data-test="new-schedule"
                                @select="startCreating"
                            >
                                <Plus class="size-4" /> New schedule
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <p class="mt-2 text-sm text-muted-foreground">
                        Active on:
                        <span class="font-medium text-foreground">
                            {{ selected?.eventTypeCount ?? 0 }} event
                            {{
                                (selected?.eventTypeCount ?? 0) === 1
                                    ? 'type'
                                    : 'types'
                            }}
                        </span>
                    </p>
                </div>

                <DropdownMenu v-if="selected">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Schedule actions"
                            data-test="schedule-actions"
                        >
                            <MoreVertical class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            :disabled="selected.isDefault"
                            @select="makeDefault"
                        >
                            <Star class="size-4" /> Set as default
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="schedules.length > 1"
                            variant="destructive"
                            @select="deleting = true"
                        >
                            <Trash2 class="size-4" /> Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <form class="p-6" @submit.prevent="submit">
                <div class="grid gap-8 lg:grid-cols-2">
                    <!-- Weekly hours -->
                    <section>
                        <header class="mb-4">
                            <h2 class="flex items-center gap-2 font-semibold">
                                <Repeat class="size-4" /> Weekly hours
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Set when you are typically available for
                                meetings
                            </p>
                        </header>

                        <div class="flex flex-col gap-3">
                            <div
                                v-for="day in days"
                                :key="day.value"
                                class="flex items-start gap-3"
                            >
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                                >
                                    <span aria-hidden="true">{{
                                        day.initial
                                    }}</span>
                                    <span class="sr-only">{{ day.name }}</span>
                                </span>

                                <div class="min-w-0 flex-1 space-y-2">
                                    <p
                                        v-if="
                                            rulesForDay(day.value).length === 0
                                        "
                                        class="py-1.5 text-sm text-muted-foreground"
                                    >
                                        Unavailable
                                    </p>

                                    <div
                                        v-for="rule in rulesForDay(day.value)"
                                        :key="`${day.value}-${form.rules.indexOf(rule)}`"
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Input
                                            v-model="rule.starts_at"
                                            type="time"
                                            class="w-28"
                                            :data-test="`day-${day.value}-start`"
                                        />
                                        <span class="text-muted-foreground"
                                            >-</span
                                        >
                                        <Input
                                            v-model="rule.ends_at"
                                            type="time"
                                            class="w-28"
                                            :data-test="`day-${day.value}-end`"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Remove hours"
                                            @click="removeRule(rule)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                        <InputError
                                            :message="
                                                errorFor(
                                                    `rules.${form.rules.indexOf(rule)}.starts_at`,
                                                )
                                            "
                                        />
                                    </div>
                                </div>

                                <div class="flex shrink-0 gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Add hours on ${day.name}`"
                                        :data-test="`add-hours-${day.value}`"
                                        @click="addBlock(day.value)"
                                    >
                                        <Plus class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Copy ${day.name} to all days`"
                                        :disabled="
                                            !rulesForDay(day.value).length
                                        "
                                        @click="copyToAllDays(day.value)"
                                    >
                                        <Copy class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 max-w-xs">
                            <Select v-model="form.timezone">
                                <SelectTrigger
                                    class="w-full"
                                    data-test="schedule-timezone"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent class="max-h-72">
                                    <SelectItem
                                        v-for="zone in timezones"
                                        :key="zone"
                                        :value="zone"
                                    >
                                        {{ zone }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.timezone" />
                        </div>
                    </section>

                    <!-- Date-specific hours -->
                    <section>
                        <header
                            class="mb-4 flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <h2
                                    class="flex items-center gap-2 font-semibold"
                                >
                                    <CalendarDays class="size-4" />
                                    Date-specific hours
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Adjust hours for specific days
                                </p>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="rounded-full"
                                data-test="add-override"
                                @click="addOverride"
                            >
                                <CalendarPlus class="size-3.5" /> Hours
                            </Button>
                        </header>

                        <p
                            v-if="!form.overrides.length"
                            class="text-sm text-muted-foreground"
                        >
                            No date-specific hours yet.
                        </p>

                        <div class="flex flex-col gap-3">
                            <div
                                v-for="(override, index) in form.overrides"
                                :key="index"
                                class="flex flex-wrap items-center gap-2 rounded-lg border p-3"
                            >
                                <Input
                                    v-model="override.date"
                                    type="date"
                                    class="w-40"
                                    :data-test="`override-date-${index}`"
                                />

                                <label class="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        v-model="override.is_unavailable"
                                    />
                                    Unavailable
                                </label>

                                <template v-if="!override.is_unavailable">
                                    <Input
                                        :model-value="override.starts_at ?? ''"
                                        type="time"
                                        class="w-28"
                                        @update:model-value="
                                            (value) =>
                                                (override.starts_at =
                                                    String(value))
                                        "
                                    />
                                    <span class="text-muted-foreground">-</span>
                                    <Input
                                        :model-value="override.ends_at ?? ''"
                                        type="time"
                                        class="w-28"
                                        @update:model-value="
                                            (value) =>
                                                (override.ends_at =
                                                    String(value))
                                        "
                                    />
                                </template>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Remove override"
                                    @click="form.overrides.splice(index, 1)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </section>
                </div>

                <div
                    class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t pt-6"
                >
                    <div class="grid max-w-xs flex-1 gap-2">
                        <Label for="schedule-name">Schedule name</Label>
                        <Input
                            id="schedule-name"
                            v-model="form.name"
                            data-test="schedule-name"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="save-schedule"
                    >
                        {{ creating ? 'Create schedule' : 'Save availability' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>

    <Dialog v-model:open="deleting">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete {{ selected?.name }}?</DialogTitle>
                <DialogDescription>
                    Event types using this schedule fall back to your default
                    one.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = false">
                    Keep it
                </Button>
                <Button
                    variant="destructive"
                    data-test="confirm-delete-schedule"
                    @click="confirmDelete"
                >
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
