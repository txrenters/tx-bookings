<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

type Member = {
    id: number;
    name: string;
    email: string;
};

const props = defineProps<{
    members: Member[];
    /** Host ids, highest priority first. */
    modelValue: number[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number[]): void;
}>();

/** Announced after a reorder so the change is not visual-only. */
const announcement = ref('');

const selected = computed(() => props.modelValue ?? []);

const byId = computed(
    () => new Map(props.members.map((member) => [member.id, member])),
);

const chosen = computed(() =>
    selected.value
        .map((id) => byId.value.get(id))
        .filter((member): member is Member => member !== undefined),
);

const available = computed(() =>
    props.members.filter((member) => !selected.value.includes(member.id)),
);

const commit = (ids: number[]) => emit('update:modelValue', ids);

const add = (member: Member) => {
    commit([...selected.value, member.id]);
    announcement.value = `${member.name} added at position ${selected.value.length + 1}.`;
};

const remove = (member: Member) => {
    commit(selected.value.filter((id) => id !== member.id));
    announcement.value = `${member.name} removed from the host pool.`;
};

const move = (index: number, delta: number) => {
    const next = index + delta;

    if (next < 0 || next >= selected.value.length) {
        return;
    }

    const ids = [...selected.value];
    [ids[index], ids[next]] = [ids[next], ids[index]];
    commit(ids);

    const member = byId.value.get(ids[next]);
    announcement.value = `${member?.name} moved to position ${next + 1} of ${ids.length}.`;
};
</script>

<template>
    <div class="space-y-4">
        <!-- Chosen hosts, in the order bookings will be offered to them. -->
        <div v-if="chosen.length" class="space-y-2">
            <ol class="space-y-2">
                <li
                    v-for="(member, index) in chosen"
                    :key="member.id"
                    class="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
                    :data-test="`host-row-${member.id}`"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                        :class="
                            index === 0
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground'
                        "
                        data-numeric
                        aria-hidden="true"
                    >
                        {{ index + 1 }}
                    </span>

                    <span class="min-w-0 flex-1 text-sm">
                        <span class="font-medium" data-wrap-anywhere>
                            {{ member.name }}
                        </span>
                        <span
                            class="block text-muted-foreground"
                            data-wrap-anywhere
                        >
                            {{ member.email }}
                        </span>
                        <span class="sr-only">
                            Priority {{ index + 1 }} of {{ chosen.length }}
                        </span>
                    </span>

                    <span class="flex shrink-0 items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 cursor-pointer disabled:cursor-not-allowed"
                            :disabled="index === 0"
                            :aria-label="`Move ${member.name} up`"
                            :data-test="`host-up-${member.id}`"
                            @click="move(index, -1)"
                        >
                            <ArrowUp class="size-4" aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 cursor-pointer disabled:cursor-not-allowed"
                            :disabled="index === chosen.length - 1"
                            :aria-label="`Move ${member.name} down`"
                            :data-test="`host-down-${member.id}`"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="size-4" aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 cursor-pointer text-muted-foreground hover:text-destructive"
                            :aria-label="`Remove ${member.name} from the host pool`"
                            :data-test="`host-remove-${member.id}`"
                            @click="remove(member)"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </Button>
                    </span>
                </li>
            </ol>

            <p class="text-xs text-muted-foreground">
                Bookings go to the highest host who is free at that time.
                Everyone below is a fallback, in this order.
            </p>
        </div>

        <p
            v-else
            class="rounded-lg border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
        >
            No hosts chosen yet — the event type's owner will take every
            booking.
        </p>

        <!-- Everyone not yet in the pool. -->
        <div v-if="available.length" class="space-y-2">
            <p class="text-xs font-semibold text-muted-foreground uppercase">
                Add a host
            </p>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="member in available"
                    :key="member.id"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="cursor-pointer bg-card font-medium"
                    :data-test="`host-add-${member.id}`"
                    @click="add(member)"
                >
                    <Plus class="size-3.5" aria-hidden="true" />
                    {{ member.name }}
                </Button>
            </div>
        </div>

        <p aria-live="polite" class="sr-only">{{ announcement }}</p>
    </div>
</template>
