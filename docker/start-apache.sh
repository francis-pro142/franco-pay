#!/bin/sh
set -e

# The php:*-apache images run PHP through mod_php, which only works with the
# prefork MPM. Some builds end up with mpm_event or mpm_worker enabled as
# well, and Apache then refuses to start with:
#   AH00534: apache2: Configuration error: More than one MPM loaded.
# Normalise to prefork before starting, clearing any leftover symlinks.
a2dismod mpm_event mpm_worker 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
a2enmod mpm_prefork 2>/dev/null || true

# Railway and similar platforms assign the listening port at runtime via
# $PORT. Apache bakes its port into ports.conf and the vhost, so rewrite
# both before handing off to the image's normal entrypoint.
PORT="${PORT:-80}"

sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground "$@"
