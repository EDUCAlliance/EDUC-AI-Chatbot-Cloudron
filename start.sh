#!/bin/bash

set -eu

mkdir -p /run/app/sessions

mkdir -p /app/code
mkdir -p /app/code/public



APACHE_CONFDIR="" source /etc/apache2/envvars
rm -f "${APACHE_PID_FILE}"
exec /usr/sbin/apache2 -DFOREGROUND
