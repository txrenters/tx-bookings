<script setup lang="ts">
/**
 * The header every app page opens with.
 *
 * Pages had drifted into three different headers -- an h1 at text-2xl, another
 * at text-3xl, and Heading's h2 at text-base -- so this is the one place the
 * page title is styled. Heading stays for section titles INSIDE a page, which
 * is a different job and deliberately smaller.
 *
 * The title is a slot as well as a prop so a page can decorate it (the
 * dashboard's greeting carries an emoji) without reinventing the styling.
 */
type Props = {
    title?: string;
    description?: string;
};

defineProps<Props>();
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                <slot name="title">{{ title }}</slot>
            </h1>
            <p
                v-if="description || $slots.description"
                class="mt-1 text-sm text-muted-foreground"
            >
                <slot name="description">{{ description }}</slot>
            </p>
        </div>

        <slot name="actions" />
    </div>
</template>
