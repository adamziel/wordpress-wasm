#!/bin/bash

rm -rf ./my-notes/workdir/*
cp -r ./my-notes/safe-copy/* ./my-notes/workdir/

bun --inspect ../cli/src/cli.ts \
    server \
    --mount=../data-liberation-static-files-editor:/wordpress/wp-content/plugins/z-data-liberation-static-files-editor \
    --mount=../data-liberation-markdown:/wordpress/wp-content/plugins/z-data-liberation-markdown \
    --mount=../data-liberation:/wordpress/wp-content/plugins/data-liberation \
    --mount=../../../../gutenberg:/wordpress/wp-content/plugins/gutenberg \
    --mount=./my-notes/workdir:/wordpress/wp-content/uploads/static-pages \
    --blueprint=./blueprint.json
