import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

/**
 * Resolve an Inertia link href — a plain string or a Wayfinder action
 * object — down to the URL string.
 */
export function toUrl(href: NonNullable<InertiaLinkProps['href']>): string {
    return typeof href === 'string' ? href : href.url;
}
