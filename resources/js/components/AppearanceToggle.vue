<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

/**
 * Flip between light and dark explicitly. Switching to light reveals the new
 * theme as a circle growing out of the toggle; switching to dark plays the
 * opposite move — the light theme collapses back into the button. Following
 * the system preference is still available on the appearance settings page.
 *
 * The circle originates at the button's centre so keyboard activation animates
 * from the same spot as a click. Browsers without the View Transitions API and
 * users preferring reduced motion get an instant switch.
 */
const toggle = async (event: MouseEvent) => {
    const next = isDark.value ? 'light' : 'dark';

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    if (reduceMotion || typeof document.startViewTransition !== 'function') {
        updateAppearance(next);

        return;
    }

    const button = event.currentTarget as HTMLElement;
    const { left, top, width, height } = button.getBoundingClientRect();
    const x = left + width / 2;
    const y = top + height / 2;
    const radius = Math.hypot(
        Math.max(x, window.innerWidth - x),
        Math.max(y, window.innerHeight - y),
    );

    const transition = document.startViewTransition(() =>
        updateAppearance(next),
    );

    await transition.ready;

    const grow = [
        `circle(0px at ${x}px ${y}px)`,
        `circle(${radius}px at ${x}px ${y}px)`,
    ];
    const shrink = [...grow].reverse();

    /*
     * To light, the incoming snapshot expands over the dark one. To dark, the
     * outgoing light snapshot shrinks away instead — it is kept on top during
     * the transition by the `.dark::view-transition-old(root)` rule in app.css.
     */
    document.documentElement.animate(
        { clipPath: next === 'dark' ? shrink : grow },
        {
            duration: 500,
            easing: 'ease-in-out',
            // Hold the last frame until the snapshot is torn down. Without
            // this the shrunk light layer springs back to full screen for one
            // frame at the end — a visible flash when going dark.
            fill: 'forwards',
            pseudoElement:
                next === 'dark'
                    ? '::view-transition-old(root)'
                    : '::view-transition-new(root)',
        },
    );
};
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        class="cursor-pointer"
        data-test="appearance-toggle"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggle"
    >
        <Sun v-if="isDark" class="size-4" aria-hidden="true" />
        <Moon v-else class="size-4" aria-hidden="true" />
    </Button>
</template>
