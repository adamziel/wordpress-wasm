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

rm $DIST_DIR/data-liberation-core.* > /dev/null 2>&1 || true
export BOX_BASE_PATH=$(type -a box | grep -v 'alias' | awk '{print $3}')
php $BUILD_DIR/box.php compile -d $DATA_LIBERATION_DIR -c $DATA_LIBERATION_DIR/phar-core-box.json
php -d 'phar.readonly=0' $BUILD_DIR/truncate-composer-checks.php $DIST_DIR/data-liberation-core.phar
cd $DIST_DIR
php $BUILD_DIR/smoke-test.php
PHP=7.2 bun $DATA_LIBERATION_DIR/../../php-wasm/cli/src/main.ts $BUILD_DIR/smoke-test.php
gzip data-liberation-core.phar
ls -sgh $DIST_DIR
