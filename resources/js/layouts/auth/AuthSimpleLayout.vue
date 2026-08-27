<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const name = usePage().props.name;
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <Link
                :href="home()"
                class="mx-auto flex w-fit items-center gap-2.5 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
            >
                <AppLogoIcon class="size-9" />
                <span class="font-bold tracking-tight">{{ name }}</span>
            </Link>

            <div
                class="mt-8 rounded-2xl border border-border bg-card p-6 shadow-raised sm:p-8"
            >
                <div v-if="title || description" class="mb-6 text-center">
                    <h1 v-if="title" class="text-xl font-bold tracking-tight">
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
