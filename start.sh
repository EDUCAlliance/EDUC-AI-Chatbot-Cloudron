#!/bin/bash

set -eu

mkdir -p /run/app/sessions

mkdir -p /app/code
chmod -R 775 /app/code/
chown -R www-data.www-data /app/code/

APACHE_CONFDIR="" source /etc/apache2/envvars
rm -f "${APACHE_PID_FILE}"
exec /usr/sbin/apache2 -DFOREGROUND
