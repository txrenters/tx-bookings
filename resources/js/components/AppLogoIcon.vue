<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!--
      The brand mark ships as a full-colour raster in public/, so it is never
      recoloured by the theme. Callers size the box; object-contain keeps the
      472x528 ratio inside whatever square they pick. Decorative by default —
      every caller renders the product name alongside it.
    -->
    <img
        src="/logo.png"
        alt=""
        width="472"
        height="528"
        decoding="async"
        class="object-contain"
        :class="className"
        v-bind="$attrs"
    />
</template>
