<script setup lang="ts">
import { Info } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: 'Log in' | 'Register';
};

defineProps<Props>();
</script>

<template>
    <div class="mb-6" data-test="team-invitation-alert">
        <Alert
            class="border-primary/25 bg-accent text-accent-foreground [&>svg]:text-accent-foreground"
        >
            <Info class="size-4" />
            <AlertDescription class="text-accent-foreground">
                {{ action }} to join the "{{ invitation.teamName }}"
                organization.
            </AlertDescription>
        </Alert>
    </div>
</template>
