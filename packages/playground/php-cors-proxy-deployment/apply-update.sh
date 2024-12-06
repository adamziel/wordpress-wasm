#!/bin/bash

set -euo pipefail

# Explicitly use the site's declared PHP version.
# Otherwise, we've seen this defaulting to older versions which can break our scripts.
SITE_PHP="/usr/local/php${PHP_VERSION}/bin/php"
SITE_API_BASE="$( "$SITE_PHP" -r 'require "/scripts/env.php"; echo SITE_API_BASE;')"

echo Adding config file to updated proxy files
cp ~/cors-proxy-deployment/cors-proxy-config.php ~/updated-proxy-files/
cp -R ~/cors-proxy-deployment/__wp__ ~/updated-proxy-files/

echo Syncing staged files to production
rsync -av --delete --no-perms --omit-dir-times ~/updated-proxy-files/ /srv/htdocs/

echo Purging edge cache
curl -sS -X POST -H "Auth: $ATOMIC_SITE_API_KEY" "$SITE_API_BASE/edge-cache/$ATOMIC_SITE_ID/purge" \
        > /dev/null \
        && echo "Edge cache purged" \
        || (>&2 echo "Failed to purge edge cache" && false)

echo Applying latest CORS proxy rate-limiting schema
# NOTE: This will reset rate-limiting token buckets, but that should be tolerable
# as long as we're generally discouraging abuse of the proxy.
cat ~/cors-proxy-deployment/cors-proxy-rate-limiting-table.sql | mysql --database="$DB_NAME"

echo Done!
