<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as cancelBooking } from '@/routes/booking/cancel';

type Props = {
    booking: {
        uid: string;
        eventTypeName: string;
        hostNames: string[];
        localDate: string;
        localTime: string;
        timezone: string;
        isChangeable: boolean;
    };
};

const props = defineProps<Props>();

const form = useForm({ reason: '' });

const submit = () => {
    form.delete(cancelBooking(props.booking.uid).url);
};
</script>

<template>
    <Head title="Cancel booking" />

    <div class="min-h-svh bg-background px-4 py-12 sm:py-20">
        <div
            class="mx-auto w-full max-w-lg overflow-hidden rounded-2xl border border-border bg-card shadow-raised"
        >
            <div class="border-b border-border p-8">
                <span
                    class="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                >
                    <TriangleAlert class="size-5" aria-hidden="true" />
                </span>
                <h1 class="mt-4 text-xl font-bold tracking-tight">
                    Cancel this booking?
                </h1>
                <p class="mt-1.5 text-sm text-muted-foreground">
                    Everyone on the invite is told, and the time opens back up.
                </p>
            </div>

            <dl class="space-y-5 p-8 text-sm">
                <div>
                    <dt
                        class="text-xs font-semibold text-muted-foreground uppercase"
                    >
                        Event
                    </dt>
                    <dd class="mt-1 font-semibold" data-wrap-anywhere>
                        {{ booking.eventTypeName }} with
                        {{ booking.hostNames.join(', ') }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="text-xs font-semibold text-muted-foreground uppercase"
                    >
                        When
                    </dt>
                    <dd class="mt-1 font-medium" data-numeric>
                        {{ booking.localDate }}, {{ booking.localTime }}
                    </dd>
                    <dd class="mt-1 text-xs text-muted-foreground">
                        {{ booking.timezone }}
                    </dd>
                </div>
            </dl>

            <form
                v-if="booking.isChangeable"
                class="space-y-5 border-t border-border bg-muted/50 p-6 sm:p-8"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="reason">Reason (optional)</Label>
                    <Textarea
                        id="reason"
                        v-model="form.reason"
                        rows="3"
                        class="bg-card"
                        aria-describedby="reason-help"
                        data-test="cancel-reason"
                    />
                    <p id="reason-help" class="text-xs text-muted-foreground">
                        Shared with everyone on the invite.
                    </p>
                    <InputError :message="form.errors.reason" />
                </div>

                <Button
                    type="submit"
                    variant="destructive"
                    class="w-full cursor-pointer font-semibold"
                    :disabled="form.processing"
                    data-test="cancel-submit"
                >
                    {{ form.processing ? 'Canceling…' : 'Cancel booking' }}
                </Button>
            </form>

            <p
                v-else
                class="border-t border-border bg-muted/50 p-6 text-sm text-muted-foreground sm:p-8"
            >
                This booking can no longer be canceled. Contact
                {{ booking.hostNames.join(', ') }} directly if you need to
                change it.
            </p>
        </div>
    </div>
</template>
