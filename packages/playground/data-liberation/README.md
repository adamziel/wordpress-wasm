## Data Liberation

This project aims to help the Data Liberation project and unlock powerful new
use cases for WordPress. See [the rationale](RATIONALE.md) and [the plan](PLAN.md)
for more details.

### Development

Every time you change the core libraries, you need to run one of the build
commands below before it will have effect in Playground. If you have an idea
how to automate this, please propose a PR!

### Building

#### Core libraries (data-liberation-core.phar)

The `nx build:phar playground-data-liberation` command builds the standalone
dist/core-data-liberation.phar.gz file meant for use in the importWxr Blueprint
step. It is built with Box and brings in all the classes required to import the
WXR files, including WP_Stream_Importer, AsyncHttp\Client, and WP_XML_Processor.

The phar is optimized for size and doesn't include the markdown importer and its
dependencies.

#### Full plugin (data-liberation-plugin.zip)

The `nx build:plugin playground-data-liberation` command builds the
dist/data-liberation-plugin.zip file, which contains all the plugin libraries, the
wp-admin page, JavaScript files, etc.
