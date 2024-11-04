#!/bin/bash

bun ../../../cli/src/cli.ts \
    server \
    --mount=../../:/wordpress/wp-content/plugins/data-liberation \
    --mount=../../../../docs:/wordpress/wp-content/docs \
    --blueprint=/Users/cloudnik/www/Automattic/core/plugins/playground/packages/playground/data-liberation/tests/import/blueprint-import.json
