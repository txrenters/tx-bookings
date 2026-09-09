<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

/** Show the chosen file straight away, before it has been saved. */
const preview = ref<string | null>(null);

const onPhotoChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = file ? URL.createObjectURL(file) : null;
};
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="photo">Photo</Label>
                <div class="flex items-center gap-4">
                    <Avatar class="size-16">
                        <AvatarImage
                            v-if="preview ?? user.avatar"
                            :src="(preview ?? user.avatar)!"
                            :alt="user.name"
                        />
                        <AvatarFallback class="text-lg">
                            {{ getInitials(user.name) }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="flex flex-wrap items-center gap-2">
                        <Input
                            id="photo"
                            ref="photoInput"
                            type="file"
                            name="photo"
                            class="max-w-64 cursor-pointer"
                            accept="image/png,image/jpeg,image/webp"
                            data-test="profile-photo"
                            @change="onPhotoChange"
                        />
                        <Button
                            v-if="user.avatar && !preview"
                            type="submit"
                            name="remove_photo"
                            value="1"
                            variant="ghost"
                            size="sm"
                            data-test="remove-photo"
                        >
                            Remove
                        </Button>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    PNG, JPG or WebP, up to 2 MB.
                </p>
                <InputError class="mt-2" :message="errors.photo" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="cursor-pointer text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-success"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
