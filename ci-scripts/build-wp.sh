#!/bin/bash

# Print commands to the screen
set -x

# Catch Errors
set -euo pipefail

# Set variables
## Getting the latest WordPress core version from WordPress.org via API. If you want to manually control the WordPress core
## version in your cluster(recommended) replace the API call with the exact version number you want to install
WORDPRESS_VERSION=$(curl -s "https://api.wordpress.org/core/version-check/1.7/" | jq -r '[.offers[]|select(.response=="upgrade")][0].version')

# Create file structure
mkdir -p plugins
mkdir -p themes

# Install plugins
composer install --no-dev -o

# Download WordPress
curl -O https://wordpress.org/wordpress-${WORDPRESS_VERSION}.tar.gz
tar -xzf wordpress-${WORDPRESS_VERSION}.tar.gz
rm -rf wordpress-${WORDPRESS_VERSION}.tar.gz
rm -rf ./wordpress/wp-content/themes
rm -rf ./wordpress/wp-content/plugins
rsync -ravxc ./ ./wordpress/wp-content/ --exclude-from=./ci-scripts/rsync-excludes.txt
cp ./wp-config.php ./wordpress/wp-config.php

set x
