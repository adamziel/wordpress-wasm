/* {{{ includes */

#include <stdlib.h>
#include <string.h>
#include "php.h"
#include "mysqli_polyfill.h"
#include "zend_API.h"
#include "ext/standard/info.h"
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_report) */
PHP_FUNCTION(mysqli_report)
{
    RETURN_TRUE;
}
/* }}} */

/* {{{ PHP_MINIT_FUNCTION */
PHP_MINIT_FUNCTION(mysqli_polyfill)
{
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MSHUTDOWN_FUNCTION */
PHP_MSHUTDOWN_FUNCTION(mysqli_polyfill)
{
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION */
PHP_MINFO_FUNCTION(mysqli_polyfill)
{
    php_info_print_table_start();
    php_info_print_table_row(2, "mysqli_polyfill support", "enabled");
    php_info_print_table_end();
}
/* }}} */

/* {{{ mysqli_polyfill_functions[] */
const zend_function_entry mysqli_polyfill_functions[] = {
    ZEND_FE(mysqli_report, arginfo_mysqli_report){NULL, NULL, NULL}};
/* }}} */

/* {{{ mysqli_polyfill_module_entry */
zend_module_entry mysqli_polyfill_module_entry = {
    STANDARD_MODULE_HEADER,
    "mysqli_polyfill",              /* Extension name */
    mysqli_polyfill_functions,      /* zend_function_entry */
    PHP_MINIT(mysqli_polyfill),     /* PHP_MINIT - Module initialization */
    PHP_MSHUTDOWN(mysqli_polyfill), /* PHP_MSHUTDOWN - Module shutdown */
    NULL,                           /* PHP_RINIT - Request initialization */
    NULL,                           /* PHP_RSHUTDOWN - Request shutdown */
    PHP_MINFO(mysqli_polyfill),     /* PHP_MINFO - Module info */
    PHP_MYSQLI_POLYFILL_VERSION,    /* Version */
    STANDARD_MODULE_PROPERTIES};
/* }}} */
