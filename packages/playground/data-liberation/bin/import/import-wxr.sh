#!/bin/bash
#
# A script that accepts a folder and imports all WXR files into a WordPress site
#
# Usage:
#    ./import-wxr.sh <folder-name>
#

# Display help message
show_help() {
	echo "Usage: $0 [-h|--help] <folder-name>"
	echo "Options:"
	echo "  -h, --help Show this help message"
}

# Check if no arguments were provided. If so, display help message
if [ $# -eq 0 ]; then
	show_help
	exit 1
fi

# Parse command line arguments. If an invalid argument is provided, display help message
while [[ "$1" =~ ^- && ! "$1" == "--" ]]; do case $1 in
  -h | --help )
	show_help
	exit 0
	;;
esac; shift; done
if [[ "$1" == '--' ]]; then shift; fi

# Check if filename is provided. If not, display error message.
if [ -z "$1" ]; then
	echo "Error: No folder provided"
	show_help
	exit 1
fi

# Check if the file exists
if [ -d "$1" ]; then
	bun ../../../cli/src/cli.ts \
		server \
		--mount=../../:/wordpress/wp-content/plugins/data-liberation \
		--mount=$1:/wordpress/wp-content/uploads/import-wxr \
		--blueprint=./blueprint-import-wxr.json
else
	echo "Error: Folder '$1' does not exist"
	exit 1
fi
