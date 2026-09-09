<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import AvailabilityTabs from '@/components/scheduling/AvailabilityTabs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useCurrentTeam } from '@/composables/useCurrentTeam';
import { index } from '@/routes/availability';
import { update as updateAdvanced } from '@/routes/availability/advanced';

type Holiday = {
    key: string;
    label: string;
    date: string;
    isObserved: boolean;
};

type Props = {
    limits: Array<{ period: string; max_bookings: number }>;
    periods: Array<{ value: string; label: string }>;
    holidayCountry: string | null;
    countries: Array<{ value: string; label: string }>;
    holidays: Holiday[];
    enabledHolidays: string[];
};

const props = defineProps<Props>();

const { teamSlug } = useCurrentTeam();

const form = useForm({
    limits: props.limits.map((limit) => ({ ...limit })),
    holiday_country: props.holidayCountry,
    holidays: [...props.enabledHolidays],
});

const usedPeriods = computed(() => form.limits.map((limit) => limit.period));

const canAddLimit = computed(() => form.limits.length < props.periods.length);

const addLimit = () => {
    const next = props.periods.find(
        (period) => !usedPeriods.value.includes(period.value),
    );

    if (next) {
        form.limits.push({ period: next.value, max_bookings: 5 });
    }
};

const toggleHoliday = (key: string, enabled: boolean) => {
    const keys = new Set(form.holidays);

    if (enabled) {
        keys.add(key);
    } else {
        keys.delete(key);
    }

    form.holidays = [...keys];
};

const formatDate = (date: string) =>
    new Date(`${date}T00:00:00Z`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    });

const errorFor = (key: string) => (form.errors as Record<string, string>)[key];

setLayoutProps({
    breadcrumbs: [{ title: 'Availability', href: index(teamSlug.value) }],
});
</script>

<template>
    <Head title="Advanced settings" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Availability"
            description="When you can be booked, and which calendars are consulted."
        />

        <AvailabilityTabs />

        <form
            class="flex max-w-3xl flex-col gap-5"
            @submit.prevent="form.patch(updateAdvanced(teamSlug).url)"
        >
            <div>
                <h2 class="font-semibold">Advanced settings</h2>
                <p class="text-sm text-muted-foreground">
                    Control availability across all your event types
                </p>
            </div>

            <section class="rounded-xl border p-6">
                <h3 class="font-semibold">Meeting limits</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Set a maximum number of total meetings. You can also set
                    specific limits within individual events.
                </p>

                <div v-if="form.limits.length" class="mt-4 flex flex-col gap-3">
                    <div
                        v-for="(limit, index) in form.limits"
                        :key="index"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Input
                            v-model.number="limit.max_bookings"
                            type="number"
                            min="1"
                            class="w-24"
                            :data-test="`limit-max-${index}`"
                            :aria-label="`Maximum meetings ${limit.period}`"
                        />
                        <span class="text-sm text-muted-foreground">
                            meetings
                        </span>
                        <Select v-model="limit.period">
                            <SelectTrigger
                                class="w-40"
                                :data-test="`limit-period-${index}`"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="period in periods"
                                    :key="period.value"
                                    :value="period.value"
                                    :disabled="
                                        usedPeriods.includes(period.value) &&
                                        period.value !== limit.period
                                    "
                                >
                                    {{ period.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove limit"
                            @click="form.limits.splice(index, 1)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                        <InputError
                            :message="errorFor(`limits.${index}.max_bookings`)"
                        />
                    </div>
                </div>

                <InputError :message="errorFor('limits')" />

                <Button
                    v-if="canAddLimit"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-4 rounded-full"
                    data-test="add-meeting-limit"
                    @click="addLimit"
                >
                    <Plus class="size-3.5" /> Add a meeting limit
                </Button>
            </section>

            <section class="rounded-xl border p-6">
                <h3 class="font-semibold">Holidays</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    You are automatically marked as unavailable for the selected
                    holidays
                </p>

                <div class="mt-4 grid max-w-xs gap-2">
                    <Label for="holiday-country">Country for holidays</Label>
                    <Select
                        :model-value="form.holiday_country ?? 'none'"
                        @update:model-value="
                            (value) =>
                                (form.holiday_country =
                                    value === 'none' ? null : String(value))
                        "
                    >
                        <SelectTrigger
                            id="holiday-country"
                            class="w-full"
                            data-test="holiday-country"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No holidays</SelectItem>
                            <SelectItem
                                v-for="country in countries"
                                :key="country.value"
                                :value="country.value"
                            >
                                {{ country.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <p
                    v-if="!holidays.length"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    Choose a country, then save, to pick which holidays you take
                    off.
                </p>

                <ul v-else class="mt-4 divide-y">
                    <li
                        v-for="holiday in holidays"
                        :key="holiday.key"
                        class="flex items-center justify-between gap-4 py-3"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">
                                {{ holiday.label }}
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                Next: {{ formatDate(holiday.date) }}
                                <template v-if="holiday.isObserved">
                                    (observed)
                                </template>
                            </span>
                        </span>

                        <Switch
                            :model-value="form.holidays.includes(holiday.key)"
                            :data-test="`holiday-${holiday.key}`"
                            @update:model-value="
                                (value) =>
                                    toggleHoliday(holiday.key, Boolean(value))
                            "
                        />
                    </li>
                </ul>
            </section>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-advanced"
                >
                    Save settings
                </Button>
            </div>
        </form>
    </div>
</template>
