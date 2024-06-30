#!/bin/bash

# Reload services
if ! systemctl is-active --quiet php-fpm; then
  echo "PHP-FPM service seems to be inactive... skipping"
else
  systemctl reload  php-fpm
fi

if ! systemctl is-active --quiet nginx; then
  echo "Nginx service seems to be inactive... skipping"
else
  systemctl reload nginx
fi