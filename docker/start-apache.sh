#!/bin/sh
set -e

# Railway and similar platforms assign the listening port at runtime via
# $PORT. Apache bakes its port into ports.conf and the vhost, so rewrite
# both before handing off to the image's normal entrypoint.
PORT="${PORT:-80}"

sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground "$@"
