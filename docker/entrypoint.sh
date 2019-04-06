#!/usr/bin/env ash
if [ `whoami` == 'keys-sync' ]; then
  if [ ! -r /ska/config/config.ini ]; then
      echo "config.ini not found or incorrect permissions."
      echo "Permissions must be $(id -u keys-sync):$(id -g keys-sync) with at least 400"
      exit 1
  fi
  if [ ! -r /ska/config/keys-sync ]; then
      echo "private key not found or incorrect permissions."
      echo "Permissions must be $(id -u keys-sync):$(id -g keys-sync) with 400"
      exit 1
  fi
  if [ ! -r /ska/config/keys-sync.pub ]; then
      echo "public key not found or incorrect permissions."
      echo "Permissions must be $(id -u keys-sync):$(id -g keys-sync) with at least 400"
      exit 1
  fi
  if ! grep "^timeout_util = BusyBox$" /ska/config/config.ini > /dev/null; then
      echo "timeout_util must be set to BusyBox."
      echo "Change it to: timeout_util = BusyBox"
      exit 1
  fi
elif [ $(id -u) = 0 ]; then
  if ! sudo -u keys-sync /entrypoint.sh; then
    exit 1
  fi
  rsync -a --delete /ska/public_html/ /public_html/
  /usr/sbin/crond
  echo "Waiting for database..."
  sleep 5
  /ska/scripts/syncd.php --user keys-sync
  /usr/sbin/php-fpm7 -F
else
  echo "Must be executed with root"
fi
