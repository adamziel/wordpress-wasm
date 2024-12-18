#!/bin/bash

# Builds the standalone dist/core-data-liberation.phar.gz file meant for
# use in the importWxr Blueprint step.
#
# This is a temporary measure until we have a canonical way of distributing,
# versioning, and using the Data Liberation modules and their dependencies.
# Possible solutions might include composer packages, WordPress plugins, or
# tree-shaken zip files with each module and its composer deps.

set -e
echo "Building data liberation plugin"
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DATA_LIBERATION_DIR=$SCRIPT_DIR
BUILD_DIR=$DATA_LIBERATION_DIR/bin/build
DIST_DIR=$DATA_LIBERATION_DIR/dist

rm $DIST_DIR/* > /dev/null 2>&1 || true
export BOX_BASE_PATH=$(type -a box | grep -v 'alias' | awk '{print $3}')
php $BUILD_DIR/box.php compile -d $DATA_LIBERATION_DIR -c $DATA_LIBERATION_DIR/box.json
php -d 'phar.readonly=0' $BUILD_DIR/truncate-composer-checks.php $DIST_DIR/data-liberation-markdown.phar
php $BUILD_DIR/smoke-test.php
PHP=8.0 bun $DATA_LIBERATION_DIR/../../php-wasm/cli/src/main.ts $BUILD_DIR/smoke-test.php
cd $DIST_DIR
gzip data-liberation-markdown.phar
ls -sgh $DIST_DIR
