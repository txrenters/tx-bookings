<script setup lang="ts">
import { Check, ChevronDown, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type Option = { value: string; label: string; initial: string | null };

type Props = {
    modelValue: string;
    options: { primary: Option[]; groups: Option[]; users: Option[] };
};

const props = defineProps<Props>();

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const open = ref(false);
const filter = ref('');

const all = computed(() => [
    ...props.options.primary,
    ...props.options.groups,
    ...props.options.users,
]);

const label = computed(
    () =>
        all.value.find((option) => option.value === props.modelValue)?.label ??
        'All Users & Organizations',
);

const matches = (options: Option[]) => {
    const term = filter.value.trim().toLowerCase();

    return term === ''
        ? options
        : options.filter((option) => option.label.toLowerCase().includes(term));
};

const select = (value: string) => {
    open.value = false;
    filter.value = '';
    emit('update:modelValue', value);
};
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                class="justify-between gap-2"
                data-test="scope-picker"
            >
                {{ label }}
                <ChevronDown class="size-4 opacity-70" />
            </Button>
        </PopoverTrigger>

        <PopoverContent align="start" class="w-72 p-0">
            <div class="relative border-b p-2">
                <Search
                    class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filter"
                    placeholder="Filter"
                    class="border-0 pl-8 shadow-none focus-visible:ring-0"
                />
            </div>

            <div class="max-h-80 overflow-y-auto p-1">
                <template
                    v-for="section in [
                        { title: null, items: matches(options.primary) },
                        { title: 'Groups', items: matches(options.groups) },
                        { title: 'Users', items: matches(options.users) },
                    ]"
                    :key="section.title ?? 'primary'"
                >
                    <div v-if="section.items.length" class="py-1">
                        <p
                            v-if="section.title"
                            class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ section.title }}
                        </p>
                        <button
                            v-for="option in section.items"
                            :key="option.value"
                            type="button"
                            :data-test="`scope-${option.value}`"
                            class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent"
                            :class="{
                                'bg-accent': option.value === modelValue,
                            }"
                            @click="select(option.value)"
                        >
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-medium"
                            >
                                {{ option.initial ?? '∗' }}
                            </span>
                            <span class="min-w-0 flex-1 truncate">
                                {{ option.label }}
                            </span>
                            <Check
                                v-if="option.value === modelValue"
                                class="size-4 shrink-0 text-primary"
                            />
                        </button>
                    </div>
                </template>
            </div>
        </PopoverContent>
    </Popover>
</template>
