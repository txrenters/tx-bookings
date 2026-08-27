---
paths:
  - 'resources/js/lib/**'
---

# Lib

## shadcn-vue CLI overwrites resources/js/lib/utils.ts
Running the shadcn-vue CLI rewrites resources/js/lib/utils.ts with only `cn()`, silently dropping `toUrl()` (imported by useCurrentUrl, AppHeader, NavFooter, settings/Layout) and reformatting to 2-space/no-semicolon style.

After any shadcn-vue add/init, run `npx vue-tsc --noEmit` and restore `toUrl()` plus the project's prettier style.
