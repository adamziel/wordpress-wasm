/* {{{ includes */

#include <stdlib.h>
#include <string.h>
#include "php.h"
#include "mysqli_polyfill.h"
#include "zend_API.h"
#include "ext/standard/info.h"
#include "zend_interfaces.h"
#include "zend_exceptions.h"
#include "zend_objects.h"
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_report) */
PHP_FUNCTION(mysqli_report)
{
    RETURN_TRUE;
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_get_server_info) */
PHP_FUNCTION(mysqli_get_server_info)
{
    const char *version_prefix = "php-wasm-";
    char version[strlen(version_prefix) + strlen(PHP_MYSQLI_POLYFILL_VERSION) + 1];
    strcat(strcpy(version, version_prefix), PHP_MYSQLI_POLYFILL_VERSION);
    RETURN_STRING(version);
}
/* }}} */

zend_class_entry *get_mysqli_class_instance()
{
    zend_class_entry *ce;
    zend_string *mysqli_class_name = zend_string_init("mysqli", strlen("mysqli"), 0);
    ce = zend_fetch_class(mysqli_class_name, ZEND_FETCH_CLASS_DEFAULT);
    zend_string_release(mysqli_class_name);
    return ce;
}

/* {{{ PHP_FUNCTION(mysqli_init) */
PHP_FUNCTION(mysqli_init)
{
    zend_class_entry *ce = get_mysqli_class_instance();

    if (!ce)
    {
        RETURN_FALSE;
    }

    object_init_ex(return_value, ce);
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_connect) */
PHP_FUNCTION(mysqli_connect)
{
    zend_class_entry *ce = get_mysqli_class_instance();

    if (!ce)
    {
        RETURN_FALSE;
    }

    object_init_ex(return_value, ce);
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_real_connect) */
PHP_FUNCTION(mysqli_real_connect)
{
    RETURN_TRUE;
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_query) */
PHP_FUNCTION(mysqli_query)
{
    RETURN_TRUE;
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_fetch_array) */
PHP_FUNCTION(mysqli_fetch_array)
{
    RETURN_NULL();
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_select_db) */
PHP_FUNCTION(mysqli_select_db)
{
    RETURN_TRUE;
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_close) */
PHP_FUNCTION(mysqli_close)
{
    RETURN_TRUE;
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_real_escape_string) */
PHP_FUNCTION(mysqli_real_escape_string)
{
    char *string;
    RETURN_STRING(string);
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_errno) */
PHP_FUNCTION(mysqli_errno)
{
    RETURN_LONG(0);
}
/* }}} */

/* {{{ PHP_FUNCTION(mysqli_error) */
PHP_FUNCTION(mysqli_error)
{
    RETURN_STRING("");
}
/* }}} */

/* {{{ PHP_MINIT_FUNCTION */
PHP_MINIT_FUNCTION(mysqli_polyfill)
{
    zend_class_entry ce;
    zend_class_entry *mysqli_ce;

    // Initialize the class entry
    INIT_CLASS_ENTRY(ce, "mysqli", NULL);
    mysqli_ce = zend_register_internal_class(&ce);

    // Add default properties
    zend_declare_property_string(mysqli_ce, "host", strlen("host"), "", ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "username", strlen("username"), "", ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "password", strlen("password"), "", ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "database", strlen("database"), "", ZEND_ACC_PUBLIC);
    zend_declare_property_long(mysqli_ce, "port", strlen("port"), 3306, ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "socket", strlen("socket"), "", ZEND_ACC_PUBLIC);
    zend_declare_property_null(mysqli_ce, "errno", strlen("errno"), ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "error", strlen("error"), "", ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "sqlstate", strlen("sqlstate"), "00000", ZEND_ACC_PUBLIC);
    zend_declare_property_long(mysqli_ce, "affected_rows", strlen("affected_rows"), 0, ZEND_ACC_PUBLIC);
    zend_declare_property_long(mysqli_ce, "insert_id", strlen("insert_id"), 0, ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "client_info", strlen("client_info"), "mysqli_polyfill", ZEND_ACC_PUBLIC);
    zend_declare_property_long(mysqli_ce, "client_version", strlen("client_version"), 0, ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "server_info", strlen("server_info"), "mysqli_polyfill", ZEND_ACC_PUBLIC);
    zend_declare_property_long(mysqli_ce, "server_version", strlen("server_version"), 0, ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "character_set_name", strlen("character_set_name"), "utf8", ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "protocol_version", strlen("protocol_version"), "10", ZEND_ACC_PUBLIC);
    zend_declare_property_long(mysqli_ce, "thread_id", strlen("thread_id"), 0, ZEND_ACC_PUBLIC);
    zend_declare_property_long(mysqli_ce, "warning_count", strlen("warning_count"), 0, ZEND_ACC_PUBLIC);
    zend_declare_property_null(mysqli_ce, "info", strlen("info"), ZEND_ACC_PUBLIC);
    zend_declare_property_bool(mysqli_ce, "connect_errno", strlen("connect_errno"), 0, ZEND_ACC_PUBLIC);
    zend_declare_property_string(mysqli_ce, "connect_error", strlen("connect_error"), "", ZEND_ACC_PUBLIC);

    // Register constants
    REGISTER_LONG_CONSTANT("MYSQLI_REPORT_OFF", MYSQLI_REPORT_OFF, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("MYSQLI_REPORT_ERROR", MYSQLI_REPORT_ERROR, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("MYSQLI_REPORT_STRICT", MYSQLI_REPORT_STRICT, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("MYSQLI_REPORT_INDEX", MYSQLI_REPORT_INDEX, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("MYSQLI_REPORT_ALL", MYSQLI_REPORT_ALL, CONST_CS | CONST_PERSISTENT);
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
    ZEND_FE(mysqli_report, arginfo_mysqli_report)
        ZEND_FE(mysqli_get_server_info, arginfo_mysqli_get_server_info)
            ZEND_FE(mysqli_init, arginfo_mysqli_init)
                ZEND_FE(mysqli_connect, arginfo_mysqli_connect)
                    ZEND_FE(mysqli_real_connect, arginfo_mysqli_real_connect)
                        ZEND_FE(mysqli_query, arginfo_mysqli_query)
                            ZEND_FE(mysqli_fetch_array, arginfo_mysqli_fetch_array)
                                ZEND_FE(mysqli_select_db, arginfo_mysqli_select_db)
                                    ZEND_FE(mysqli_close, arginfo_mysqli_close)
                                        ZEND_FE(mysqli_real_escape_string, arginfo_mysqli_real_escape_string)
                                            ZEND_FE(mysqli_errno, arginfo_mysqli_errno)
                                                ZEND_FE(mysqli_error, arginfo_mysqli_error)
                                                    ZEND_FE_END};
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
