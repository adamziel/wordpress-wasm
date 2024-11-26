#!/bin/bash

bun ../../../cli/src/cli.ts \
    server \
    --mount=../../:/wordpress/wp-content/plugins/data-liberation \
    --mount=../../../../docs:/wordpress/wp-content/docs \
    --blueprint=./blueprint-import.json
