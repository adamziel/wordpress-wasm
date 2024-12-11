#!/bin/bash

echo "Running the full build of the data liberation plugin"
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Only include the subset of blueprints-library that we need
# for the importers to work. Don't include the PHP Blueprint
# library yet.
zip -r ./dist/data-liberation-plugin.zip ./*.php ./src \
       ./blueprints-library/src/WordPress/AsyncHttp \
       ./blueprints-library/src/WordPress/Zip \
       ./blueprints-library/src/WordPress/Util \
       ./blueprints-library/src/WordPress/Streams \
       ./vendor

echo "Done"
