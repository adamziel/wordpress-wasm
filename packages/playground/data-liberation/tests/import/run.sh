#!/bin/bash

bun ../../../cli/src/cli.ts \
    server \
    --mount=../../:/wordpress/wp-content/plugins/data-liberation \
    --blueprint=/Users/cloudnik/www/Automattic/core/plugins/playground/packages/playground/data-liberation/tests/import/blueprint-import.json
