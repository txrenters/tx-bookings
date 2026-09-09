<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { KeyRound, Search, ShieldCheck, UserRound } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as usersIndex, passwordReset } from '@/routes/users';

type Organization = {
    id: number;
    name: string;
    isPersonal: boolean;
    roleLabel: string;
};

type DirectoryUser = {
    id: number;
    name: string;
    email: string;
    initials: string;
    isSuperAdmin: boolean;
    isVerified: boolean;
    joinedAt: string | null;
    organizations: Organization[];
};

const props = defineProps<{
    users: DirectoryUser[];
    search: string;
    page: number;
    lastPage: number;
    total: number;
}>();

const searchTerm = ref(props.search);

const reload = (data: Record<string, string | number>) => {
    router.get(
        usersIndex().url,
        { search: searchTerm.value, ...data },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

let debounce: ReturnType<typeof setTimeout> | null = null;

watch(searchTerm, () => {
    if (debounce) {
        clearTimeout(debounce);
    }

    debounce = setTimeout(() => reload({ page: 1 }), 300);
});

const sendPasswordReset = (user: DirectoryUser) => {
    router.post(
        passwordReset(user.id).url,
        {},
        { preserveScroll: true, preserveState: true },
    );
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: usersIndex() }],
    },
});
</script>

<template>
    <Head title="Users" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <Heading
            variant="small"
            title="Users"
            description="Every account in the installation, the organizations they belong to, and their access."
        />

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-64 flex-1">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="searchTerm"
                    class="pl-9"
                    placeholder="Search by name or email"
                    aria-label="Search users"
                    data-test="user-search"
                />
            </div>
            <p class="text-sm text-muted-foreground">
                {{ total }} {{ total === 1 ? 'account' : 'accounts' }}
            </p>
        </div>

        <ul
            v-if="users.length"
            class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
        >
            <li
                v-for="user in users"
                :key="user.id"
                data-test="user-row"
                class="flex flex-wrap items-center gap-4 p-4"
            >
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold"
                    aria-hidden="true"
                >
                    {{ user.initials }}
                </span>

                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ user.name }}</span>
                        <Badge
                            v-if="user.isSuperAdmin"
                            variant="secondary"
                            class="gap-1"
                        >
                            <ShieldCheck class="size-3" /> Super admin
                        </Badge>
                        <Badge v-if="!user.isVerified" variant="outline">
                            Unverified
                        </Badge>
                    </span>
                    <span class="block text-sm text-muted-foreground">
                        {{ user.email }}
                    </span>
                </span>

                <span class="flex min-w-0 flex-1 flex-wrap gap-1.5">
                    <Badge
                        v-for="organization in user.organizations"
                        :key="organization.id"
                        variant="outline"
                        class="font-normal"
                    >
                        {{ organization.name }} &middot;
                        {{ organization.roleLabel }}
                    </Badge>
                    <span
                        v-if="!user.organizations.length"
                        class="text-sm text-muted-foreground"
                    >
                        No organizations
                    </span>
                </span>

                <Button
                    variant="outline"
                    size="sm"
                    :data-test="`reset-password-${user.id}`"
                    @click="sendPasswordReset(user)"
                >
                    <KeyRound class="size-3.5" /> Send reset link
                </Button>
            </li>
        </ul>

        <div
            v-else
            class="rounded-xl border border-dashed border-border px-6 py-12 text-center text-muted-foreground"
        >
            <UserRound class="mx-auto mb-3 size-6" aria-hidden="true" />
            <p>No accounts match that search.</p>
        </div>

        <div v-if="lastPage > 1" class="flex items-center justify-between">
            <Button
                variant="outline"
                size="sm"
                :disabled="page <= 1"
                @click="reload({ page: page - 1 })"
            >
                Previous
            </Button>
            <span class="text-sm text-muted-foreground">
                Page {{ page }} of {{ lastPage }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="page >= lastPage"
                @click="reload({ page: page + 1 })"
            >
                Next
            </Button>
        </div>
    </div>
</template>
