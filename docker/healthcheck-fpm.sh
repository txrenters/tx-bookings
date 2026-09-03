#!/bin/sh
# Health check for the PHP-FPM container.
#
# Pings FPM's own status endpoint over FastCGI rather than shelling out to a
# process check: `pgrep php-fpm` reports healthy even when every worker is
# wedged, which is precisely the failure this is meant to catch.
set -e

SCRIPT_NAME=/ping \
SCRIPT_FILENAME=/ping \
REQUEST_METHOD=GET \
cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong
