<script setup lang="ts">
/**
 * A yes/no dialog for an action that cannot be undone.
 *
 * Pages were each building their own out of the dialog primitives, which is
 * how one of them ends up without a cancel button.
 */
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        confirmLabel?: string;
        cancelLabel?: string;
    }>(),
    {
        description: undefined,
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
    },
);

const emit = defineEmits<{ confirm: []; cancel: [] }>();
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(value) => (value ? null : emit('cancel'))"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="emit('cancel')">
                    {{ cancelLabel }}
                </Button>
                <Button
                    variant="destructive"
                    data-test="confirm-dialog-confirm"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
