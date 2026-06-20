#!/bin/sh
set -e

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "$1" = "/usr/bin/supervisord" ]; then
    if [ "${APIGW_PRIMARY:-0}" = "1" ]; then
        exec /usr/bin/supervisord -c /etc/supervisord.conf
    fi
    exec /usr/bin/supervisord -c /etc/supervisord-web.conf
fi

exec "$@"
