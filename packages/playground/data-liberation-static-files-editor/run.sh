#!/bin/bash

bun ../cli/src/cli.ts \
    server \
    --mount=../data-liberation-static-files-editor:/wordpress/wp-content/plugins/z-data-liberation-static-files-editor \
    --mount=../data-liberation-markdown:/wordpress/wp-content/plugins/z-data-liberation-markdown \
    --mount=../data-liberation:/wordpress/wp-content/plugins/data-liberation \
    --mount=./my-notes/workdir:/wordpress/wp-content/uploads/static-pages \
    --blueprint=./blueprint.json
