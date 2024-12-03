#!/bin/bash

set -euo pipefail

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
cat ~/website-deployment/cors-proxy-rate-limiting-table.sql | mysql --database="$DB_NAME"

echo Done!
