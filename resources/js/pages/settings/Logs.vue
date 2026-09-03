<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, Search, Trash2, TriangleAlert } from '@lucide/vue';
import { h, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { destroy as clearLog, index as logsIndex } from '@/routes/logs';

type LogFile = {
    name: string;
    sizeBytes: number;
    sizeLabel: string;
    modifiedAt: string;
};

type Entry = {
    id: string;
    level: string;
    environment: string;
    loggedAt: string;
    message: string;
    context: string | null;
};

const props = defineProps<{
    files: LogFile[];
    file: string;
    level: string;
    search: string;
    levels: string[];
    entries?: Entry[];
}>();

/*
 * The log viewer is a data table, not a settings form. Opt out of the narrow
 * settings column so long messages and stack traces have room to breathe
 * instead of wrapping inside 36rem.
 */
defineOptions({
    layout: (_h: unknown, page: unknown) =>
        h(SettingsLayout, { wide: true }, () => page),
});

const searchTerm = ref(props.search);
const expanded = ref<string | null>(null);

/** Levels that should read as a problem rather than information. */
const severe = new Set(['emergency', 'alert', 'critical', 'error']);

const levelClass = (level: string) => {
    if (severe.has(level)) {
        return 'bg-destructive/10 text-destructive';
    }

    if (level === 'warning') {
        return 'bg-warning-muted text-warning-muted-foreground';
    }

    return 'bg-muted text-muted-foreground';
};

const reload = (params: Record<string, string>) => {
    router.get(
        logsIndex().url,
        {
            file: props.file,
            level: props.level,
            search: searchTerm.value,
            ...params,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(searchTerm, () => {
    // Debounced so a query does not fire on every keystroke.
    window.clearTimeout(searchTimer);
    searchTimer = setTimeout(() => reload({ search: searchTerm.value }), 400);
});

const clearFile = () => {
    router.delete(clearLog().url, {
        data: { file: props.file },
        preserveScroll: true,
    });
};

const toggle = (id: string) => {
    expanded.value = expanded.value === id ? null : id;
};
</script>

<template>
    <Head title="Logs" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Application log"
            description="Laravel's own log, for debugging. Not available in production."
        />

        <div
            v-if="!files.length"
            class="rounded-xl border border-dashed border-border px-6 py-12 text-center"
        >
            <FileText
                class="mx-auto size-6 text-muted-foreground"
                aria-hidden="true"
            />
            <p class="mt-3 font-medium">No log files yet</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Nothing has been written to storage/logs.
            </p>
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center gap-2">
                <Select
                    :model-value="file"
                    @update:model-value="
                        (value) => reload({ file: value as string })
                    "
                >
                    <SelectTrigger
                        class="w-60 cursor-pointer"
                        aria-label="Log file"
                        data-test="log-file"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in files"
                            :key="option.name"
                            :value="option.name"
                        >
                            {{ option.name }} ({{ option.sizeLabel }})
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="level"
                    @update:model-value="
                        (value) => reload({ level: value as string })
                    "
                >
                    <SelectTrigger
                        class="w-40 cursor-pointer"
                        aria-label="Log level"
                        data-test="log-level"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All levels</SelectItem>
                        <SelectItem
                            v-for="option in levels"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <div class="relative min-w-52 flex-1">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="searchTerm"
                        class="pl-9"
                        placeholder="Search the log"
                        aria-label="Search the log"
                        data-test="log-search"
                    />
                </div>

                <Button
                    variant="outline"
                    class="cursor-pointer text-destructive hover:text-destructive"
                    data-test="log-clear"
                    @click="clearFile"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Clear
                </Button>
            </div>

            <div v-if="entries === undefined" class="space-y-2">
                <Skeleton
                    v-for="index in 5"
                    :key="index"
                    class="h-16 w-full animate-pulse rounded-lg"
                />
                <span class="sr-only">Loading log entries</span>
            </div>

            <ul
                v-else-if="entries.length"
                class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
            >
                <li v-for="entry in entries" :key="entry.id">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-start gap-3 p-4 text-left transition-colors duration-200 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:-outline-offset-2 focus-visible:outline-none"
                        :aria-expanded="expanded === entry.id"
                        @click="toggle(entry.id)"
                    >
                        <span
                            class="w-20 shrink-0 rounded-md px-2 py-0.5 text-center text-xs font-semibold uppercase"
                            :class="levelClass(entry.level)"
                        >
                            {{ entry.level }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                class="block font-mono text-sm"
                                data-wrap-anywhere
                            >
                                {{ entry.message }}
                            </span>
                            <span
                                class="mt-1 block text-xs text-muted-foreground"
                                data-numeric
                            >
                                {{ entry.loggedAt }} · {{ entry.environment }}
                            </span>
                        </span>
                    </button>

                    <pre
                        v-if="expanded === entry.id && entry.context"
                        class="max-h-80 overflow-auto border-t border-border bg-muted px-4 py-3 font-mono text-xs"
                        >{{ entry.context }}</pre>
                </li>
            </ul>

            <div
                v-else
                class="rounded-xl border border-dashed border-border px-6 py-12 text-center"
            >
                <TriangleAlert
                    class="mx-auto size-6 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="mt-3 font-medium">Nothing matches</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    No entries in this file match the current level or search.
                </p>
            </div>
        </template>
    </div>
</template>
