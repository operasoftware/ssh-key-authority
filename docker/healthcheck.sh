#!/usr/bin/env ash
for PID_FILE in /var/run/crond.pid /var/run/keys-sync.pid /var/run/php-fpm.pid; do
	PID=$(cat ${PID_FILE})
    if ! [ -n "${PID}" -a -d "/proc/${PID}" ]; then
        exit 1
    fi
done
