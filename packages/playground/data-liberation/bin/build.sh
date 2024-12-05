#!/bin/bash

echo "Building data liberation plugin"
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR/../

# Only include the subset of blueprints-library that we need
# for the importers to work. Don't include the PHP Blueprint
# library yet.
# @TODO: Prune unused files and dev dependencies from
#        the vendor directory. They only inflate the zip
#        file size.
zip -r ../blueprints/src/data-liberation-plugin.zip ./*.php ./src \
       ./blueprints-library/src/WordPress/AsyncHttp \
       ./blueprints-library/src/WordPress/Zip \
       ./blueprints-library/src/WordPress/Util \
       ./blueprints-library/src/WordPress/Streams \
       ./vendor

echo "Done"
