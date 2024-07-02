#!/bin/bash

# Set file ownership and permissions
chown -R nobody:nobody /var/www/html/wordpress
chown -R nginx:nginx /var/www/html/wordpress/wp-content/uploads
chmod 755 -R /var/www/html/wordpress
