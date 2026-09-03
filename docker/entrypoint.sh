#!/bin/sh
# Container entrypoint, shared by the app, queue and scheduler services.
#
# Caches are built per container rather than by `docker compose exec` on one of
# them. bootstrap/cache lives inside the container (it is not a volume), so
# during a rolling deploy each replica caches its own copy from its own code
# and env -- there is no shared state to race on, and no window where a new
# container serves traffic with a stale or missing cache.
#
# Migrations are deliberately NOT run here: several containers start from this
# image and would race each other. They run once, from a dedicated one-shot
# service. See bin/deploy.sh.
set -e

# storage/app/public is a volume, so the symlink has to be made after mount.
php artisan storage:link --force >/dev/null 2>&1 || true

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec "$@"
