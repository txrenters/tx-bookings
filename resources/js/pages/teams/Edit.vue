<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    ImageUp,
    Mail,
    Trash2,
    UserPlus,
    UserRoundPlus,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CancelInvitationModal from '@/components/CancelInvitationModal.vue';
import CreateMemberModal from '@/components/CreateMemberModal.vue';
import DeleteTeamModal from '@/components/DeleteTeamModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/components/RemoveMemberModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { edit, index, update } from '@/routes/teams';
import { update as updateMember } from '@/routes/teams/members';
import type {
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';

type Props = {
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
    timezones: string[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { team: Team }) => ({
        breadcrumbs: [
            {
                title: 'Organizations',
                href: index(),
            },
            {
                title: props.team.name,
                href: edit(props.team.slug),
            },
        ],
    }),
});

const { getInitials } = useInitials();

const page = usePage();

/**
 * Creating an account outright is super admin only, matching
 * TeamPolicy::createMember. Everyone else invites.
 */
const canCreateMember = computed(() => page.props.isSuperAdmin);

const inviteDialogOpen = ref(false);
const createMemberDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<TeamMember | null>(null);
const cancelInvitationDialogOpen = ref(false);
const invitationToCancel = ref<TeamInvitation | null>(null);

const pageTitle = computed(() =>
    props.permissions.canUpdateTeam
        ? `Edit ${props.team.name}`
        : `View ${props.team.name}`,
);

const updateMemberRole = (member: TeamMember, newRole: string) => {
    router.visit(updateMember([props.team.slug, member.id]), {
        data: { role: newRole },
        preserveScroll: true,
    });
};

const confirmRemoveMember = (member: TeamMember) => {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
};

const confirmCancelInvitation = (invitation: TeamInvitation) => {
    invitationToCancel.value = invitation;
    cancelInvitationDialogOpen.value = true;
};

/*
  The logo makes this a multipart submit, and Laravel cannot read multipart on
  a PATCH, so the profile posts with a spoofed method.
*/
const profileForm = useForm({
    _method: 'patch',
    name: props.team.name,
    logo: null as File | null,
    remove_logo: false as boolean,
    welcome_message: props.team.welcomeMessage ?? '',
    website_url: props.team.websiteUrl ?? '',
    timezone: props.team.timezone ?? '',
});

const logoInput = ref<HTMLInputElement | null>(null);
const logoPreview = ref<string | null>(null);

/** What the logo slot should show right now: a new pick, the stored file, or nothing. */
const shownLogo = computed(() => {
    if (logoPreview.value) {
        return logoPreview.value;
    }

    return profileForm.remove_logo ? null : (props.team.logoUrl ?? null);
});

const selectLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (!file) {
        return;
    }

    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
    }

    profileForm.logo = file;
    profileForm.remove_logo = false;
    logoPreview.value = URL.createObjectURL(file);
};

const clearLogo = () => {
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
        logoPreview.value = null;
    }

    profileForm.logo = null;
    profileForm.remove_logo = true;

    if (logoInput.value) {
        logoInput.value.value = '';
    }
};

const submitProfile = () => {
    profileForm.post(update(props.team.slug).url, {
        preserveScroll: true,
        onSuccess: () => {
            if (logoPreview.value) {
                URL.revokeObjectURL(logoPreview.value);
                logoPreview.value = null;
            }

            profileForm.logo = null;
            profileForm.remove_logo = false;
        },
    });
};
</script>

<template>
    <Head :title="pageTitle" />

    <h1 class="sr-only">{{ pageTitle }}</h1>

    <div class="flex flex-col space-y-10">
        <!-- Organization Name Section -->
        <div v-if="permissions.canUpdateTeam" class="space-y-6">
            <Heading
                variant="small"
                title="Organization settings"
                description="Update your organization name and settings"
            />

            <form class="space-y-6" @submit.prevent="submitProfile">
                <!-- Logo -->
                <div class="grid gap-2">
                    <Label for="logo">Logo</Label>
                    <p class="text-xs text-muted-foreground">
                        Shown on your public booking page. PNG, JPG or WebP, up
                        to 2 MB.
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-4">
                        <span
                            class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted"
                        >
                            <img
                                v-if="shownLogo"
                                :src="shownLogo"
                                :alt="`${team.name} logo`"
                                class="size-full object-contain"
                            />
                            <ImageUp
                                v-else
                                class="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </span>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="cursor-pointer"
                                @click="logoInput?.click()"
                            >
                                {{ shownLogo ? 'Replace' : 'Upload' }}
                            </Button>
                            <Button
                                v-if="shownLogo"
                                type="button"
                                variant="ghost"
                                class="cursor-pointer text-destructive hover:text-destructive"
                                @click="clearLogo"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                Remove
                            </Button>
                        </div>

                        <input
                            id="logo"
                            ref="logoInput"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="sr-only"
                            data-test="team-logo-input"
                            @change="selectLogo"
                        />
                    </div>
                    <InputError :message="profileForm.errors.logo" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Organization name</Label>
                    <Input
                        id="name"
                        v-model="profileForm.name"
                        name="name"
                        data-test="team-name-input"
                        required
                        :aria-invalid="Boolean(profileForm.errors.name)"
                    />
                    <InputError :message="profileForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="welcome_message">Welcome message</Label>
                    <Textarea
                        id="welcome_message"
                        v-model="profileForm.welcome_message"
                        rows="3"
                        aria-describedby="welcome_message-help"
                        data-test="team-welcome-input"
                    />
                    <p
                        id="welcome_message-help"
                        class="text-xs text-muted-foreground"
                    >
                        Greets people at the top of your booking page.
                    </p>
                    <InputError
                        :message="profileForm.errors.welcome_message"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="website_url">Website</Label>
                    <Input
                        id="website_url"
                        v-model="profileForm.website_url"
                        type="url"
                        inputmode="url"
                        placeholder="https://texasrenters.com"
                        data-test="team-website-input"
                        :aria-invalid="Boolean(profileForm.errors.website_url)"
                    />
                    <InputError :message="profileForm.errors.website_url" />
                </div>

                <div class="grid gap-2">
                    <Label for="timezone">Default time zone</Label>
                    <Select v-model="profileForm.timezone">
                        <SelectTrigger
                            id="timezone"
                            class="w-full cursor-pointer sm:max-w-sm"
                            data-test="team-timezone-select"
                        >
                            <SelectValue placeholder="Choose a time zone" />
                        </SelectTrigger>
                        <SelectContent class="max-h-72">
                            <SelectItem
                                v-for="zone in timezones"
                                :key="zone"
                                :value="zone"
                            >
                                {{ zone }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="profileForm.errors.timezone" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        type="submit"
                        class="cursor-pointer font-semibold"
                        data-test="team-save-button"
                        :disabled="profileForm.processing"
                    >
                        {{ profileForm.processing ? 'Saving…' : 'Save' }}
                    </Button>
                </div>
            </form>
        </div>

        <div v-else class="space-y-6">
            <Heading variant="small" :title="team.name" />
        </div>

        <!-- Members Section -->
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    title="Organization members"
                    :description="
                        permissions.canCreateInvitation
                            ? 'Manage who belongs to this organization'
                            : ''
                    "
                />

                <div class="flex items-center gap-2">
                    <Button
                        v-if="canCreateMember"
                        variant="outline"
                        data-test="create-member-button"
                        @click="createMemberDialogOpen = true"
                    >
                        <UserRoundPlus /> Create user
                    </Button>

                    <Button
                        v-if="permissions.canCreateInvitation"
                        data-test="invite-member-button"
                        @click="inviteDialogOpen = true"
                    >
                        <UserPlus /> Invite member
                    </Button>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="member in members"
                    :key="member.id"
                    data-test="member-row"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="flex items-center gap-4">
                        <Avatar class="h-10 w-10">
                            <AvatarImage
                                v-if="member.avatar"
                                :src="member.avatar"
                                :alt="member.name"
                            />
                            <AvatarFallback>{{
                                getInitials(member.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <div>
                            <div class="font-medium">
                                {{ member.name }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ member.email }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <DropdownMenu
                            v-if="
                                member.role !== 'owner' &&
                                permissions.canUpdateMember
                            "
                        >
                            <DropdownMenuTrigger as-child>
                                <Button
                                    data-test="member-role-trigger"
                                    variant="outline"
                                    size="sm"
                                >
                                    {{ member.role_label }}
                                    <ChevronDown
                                        class="ml-2 h-4 w-4 opacity-50"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent>
                                <DropdownMenuItem
                                    v-for="role in availableRoles"
                                    :key="role.value"
                                    data-test="member-role-option"
                                    @click="
                                        updateMemberRole(member, role.value)
                                    "
                                >
                                    {{ role.label }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Badge v-else variant="secondary">
                            {{ member.role_label }}
                        </Badge>

                        <TooltipProvider
                            v-if="
                                member.role !== 'owner' &&
                                permissions.canRemoveMember
                            "
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        data-test="member-remove-button"
                                        variant="ghost"
                                        size="sm"
                                        @click="confirmRemoveMember(member)"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Remove member</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Invitations Section -->
        <div v-if="invitations.length > 0" class="space-y-6">
            <Heading
                variant="small"
                title="Pending invitations"
                description="Invitations that haven't been accepted yet"
            />

            <div class="space-y-3">
                <div
                    v-for="invitation in invitations"
                    :key="invitation.code"
                    data-test="invitation-row"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                        >
                            <Mail class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <div class="font-medium">
                                {{ invitation.email }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ invitation.role_label }}
                            </div>
                        </div>
                    </div>

                    <TooltipProvider v-if="permissions.canCancelInvitation">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="invitation-cancel-button"
                                    variant="ghost"
                                    size="sm"
                                    @click="confirmCancelInvitation(invitation)"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Cancel invitation</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div
            v-if="permissions.canDeleteTeam && !team.isPersonal"
            class="space-y-6"
        >
            <Heading
                variant="small"
                title="Delete organization"
                description="Permanently delete your organization"
            />
            <div
                class="space-y-4 rounded-lg border border-destructive/25 bg-destructive/5 p-4"
            >
                <div
                    class="relative space-y-0.5 text-destructive"
                >
                    <p class="font-medium">Warning</p>
                    <p class="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>
                <Button
                    data-test="delete-team-button"
                    variant="destructive"
                    @click="deleteDialogOpen = true"
                    >Delete organization</Button
                >
            </div>
        </div>
    </div>

    <CreateMemberModal
        v-if="canCreateMember"
        :team="team"
        :available-roles="availableRoles"
        :open="createMemberDialogOpen"
        @update:open="createMemberDialogOpen = $event"
    />

    <InviteMemberModal
        v-if="permissions.canCreateInvitation"
        :team="team"
        :available-roles="availableRoles"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />

    <RemoveMemberModal
        :team="team"
        :member="memberToRemove"
        :open="removeMemberDialogOpen"
        @update:open="removeMemberDialogOpen = $event"
    />

    <CancelInvitationModal
        :team="team"
        :invitation="invitationToCancel"
        :open="cancelInvitationDialogOpen"
        @update:open="cancelInvitationDialogOpen = $event"
    />

    <DeleteTeamModal
        v-if="permissions.canDeleteTeam && !team.isPersonal"
        :team="team"
        :open="deleteDialogOpen"
        @update:open="deleteDialogOpen = $event"
    />
</template>
