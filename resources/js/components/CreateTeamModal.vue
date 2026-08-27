<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/teams';

const open = ref(false);
const formKey = ref(0);

function handleOpenChange(value: boolean) {
    open.value = value;

    if (!value) {
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>Create a new organization</DialogTitle>
                    <DialogDescription>
                        Create a new organization to collaborate with others.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="name">Organization name</Label>
                    <Input
                        id="name"
                        name="name"
                        data-test="create-team-name"
                        placeholder="My organization"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="create-team-submit"
                        :disabled="processing"
                    >
                        Create organization
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
