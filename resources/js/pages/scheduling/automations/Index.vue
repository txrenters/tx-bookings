<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Workflow } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrentTeam } from '@/composables/useCurrentTeam';
import { create, destroy, edit, index } from '@/routes/automations';

type Automation = {
    id: number;
    name: string;
    isActive: boolean;
    appliesTo: string[];
    when: string;
    action: string;
};

type Props = {
    automations: Automation[];
    canManage: boolean;
};

defineProps<Props>();

const { teamSlug } = useCurrentTeam();

const deleting = ref<Automation | null>(null);

const confirmDelete = () => {
    if (!deleting.value) {
        return;
    }

    router.delete(
        destroy({ current_team: teamSlug.value, automation: deleting.value.id })
            .url,
        { preserveScroll: true, onFinish: () => (deleting.value = null) },
    );
};

setLayoutProps({
    breadcrumbs: [{ title: 'Workflows', href: index(teamSlug.value) }],
});
</script>

<template>
    <Head title="Workflows" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Workflows"
            description="Send an email automatically when a meeting is booked, before it starts, or after it ends."
        >
            <template #actions>
                <Button v-if="canManage" data-test="new-automation" as-child>
                    <Link :href="create(teamSlug)">
                        <Plus /> New workflow
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="automations.length"
            class="overflow-hidden rounded-lg border border-border bg-card shadow-flat"
        >
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-muted/50">
                    <tr class="text-left text-xs text-muted-foreground">
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="hidden px-4 py-3 font-medium sm:table-cell">
                            Applies to
                        </th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">
                            When this happens
                        </th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">
                            Do this
                        </th>
                        <th class="px-4 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="automation in automations"
                        :key="automation.id"
                        data-test="automation-row"
                        class="border-b border-border last:border-0"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">
                                    {{ automation.name }}
                                </span>
                                <Badge
                                    v-if="!automation.isActive"
                                    variant="secondary"
                                >
                                    Off
                                </Badge>
                            </div>
                            <p
                                class="mt-1 text-xs text-muted-foreground md:hidden"
                            >
                                {{ automation.when }} — {{ automation.action }}
                            </p>
                        </td>
                        <td
                            class="hidden px-4 py-3 text-muted-foreground sm:table-cell"
                        >
                            {{ automation.appliesTo.join(', ') }}
                        </td>
                        <td
                            class="hidden px-4 py-3 text-muted-foreground md:table-cell"
                        >
                            {{ automation.when }}
                        </td>
                        <td
                            class="hidden px-4 py-3 text-muted-foreground md:table-cell"
                        >
                            {{ automation.action }}
                        </td>
                        <td class="px-4 py-3">
                            <div
                                v-if="canManage"
                                class="flex items-center justify-end gap-1"
                            >
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Edit ${automation.name}`"
                                    :data-test="`edit-automation-${automation.id}`"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            edit({
                                                current_team: teamSlug,
                                                automation: automation.id,
                                            })
                                        "
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Delete ${automation.name}`"
                                    @click="deleting = automation"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            class="rounded-lg border border-dashed p-12 text-center text-muted-foreground"
        >
            <Workflow class="mx-auto mb-3 size-8 opacity-50" />
            <p>No workflows yet.</p>
            <p class="mt-1 text-sm">
                A workflow emails whoever you choose when a meeting is booked,
                before it starts, or after it ends.
            </p>
            <Button v-if="canManage" class="mt-4" as-child>
                <Link :href="create(teamSlug)">Create a workflow</Link>
            </Button>
        </div>
    </div>

    <Dialog :open="deleting !== null" @update:open="deleting = null">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete {{ deleting?.name }}?</DialogTitle>
                <DialogDescription>
                    Emails it has already sent stay sent. Anything it had queued
                    for an upcoming meeting will not go out.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">
                    Keep it
                </Button>
                <Button
                    variant="destructive"
                    data-test="confirm-delete-automation"
                    @click="confirmDelete"
                >
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
