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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as storeMember } from '@/routes/teams/members';
import type { RoleOption, Team } from '@/types';

type Props = {
    team: Team;
    availableRoles: RoleOption[];
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const memberRole = ref('member');
const formKey = ref(0);

function handleOpenChange(value: boolean) {
    emit('update:open', value);

    if (!value) {
        memberRole.value = 'member';
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="storeMember.form(props.team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <DialogHeader>
                    <DialogTitle>Create a user</DialogTitle>
                    <DialogDescription>
                        The account is created immediately and already belongs
                        to this organization. They will get an email to set
                        their own password — you never choose one for them.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="create-member-name">Name</Label>
                        <Input
                            id="create-member-name"
                            name="name"
                            data-test="create-member-name"
                            type="text"
                            placeholder="Full name"
                            autocomplete="off"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="create-member-email">Email address</Label>
                        <Input
                            id="create-member-email"
                            name="email"
                            data-test="create-member-email"
                            type="email"
                            placeholder="colleague@example.com"
                            autocomplete="off"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="create-member-role">Role</Label>
                        <Select
                            v-model="memberRole"
                            name="role"
                            data-test="create-member-role"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in props.availableRoles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.role" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="create-member-submit"
                        :disabled="processing"
                    >
                        Create user
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
