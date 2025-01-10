/* MySQLi Polyfill extension for PHP */

#ifndef MYSQLI_REPORT_OFF
#define MYSQLI_REPORT_OFF 0
#endif
#ifndef MYSQLI_REPORT_ERROR
#define MYSQLI_REPORT_ERROR 1
#endif
#ifndef MYSQLI_REPORT_STRICT
#define MYSQLI_REPORT_STRICT 2
#endif
#ifndef MYSQLI_REPORT_INDEX
#define MYSQLI_REPORT_INDEX 4
#endif
#ifndef MYSQLI_REPORT_ALL
#define MYSQLI_REPORT_ALL 255
#endif

#ifndef MYSQLI_POLYFILL_H
#define MYSQLI_POLYFILL_H

extern zend_module_entry mysqli_polyfill_module_entry;
#define phpext_mysqli_polyfill_ptr &mysqli_polyfill_module_entry

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_report, 0, 0, 1)
ZEND_ARG_INFO(0, flags)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_report);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_init, 0, 0, 0)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_init);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_connect, 0, 0, 6)
ZEND_ARG_INFO(0, hostname)
ZEND_ARG_INFO(0, username)
ZEND_ARG_INFO(0, password)
ZEND_ARG_INFO(0, database)
ZEND_ARG_INFO(0, port)
ZEND_ARG_INFO(0, socket)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_connect);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_real_connect, 0, 0, 8)
ZEND_ARG_INFO(0, mysql)
ZEND_ARG_INFO(0, hostname)
ZEND_ARG_INFO(0, username)
ZEND_ARG_INFO(0, password)
ZEND_ARG_INFO(0, database)
ZEND_ARG_INFO(0, port)
ZEND_ARG_INFO(0, socket)
ZEND_ARG_INFO(0, flags)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_real_connect);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_get_server_info, 0, 0, 1)
ZEND_ARG_INFO(0, mysql)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_get_server_info);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_query, 0, 0, 3)
ZEND_ARG_INFO(0, mysql)
ZEND_ARG_INFO(0, query)
ZEND_ARG_INFO(0, result_mode)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_query);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_fetch_array, 0, 0, 2)
ZEND_ARG_INFO(0, result)
ZEND_ARG_INFO(0, mode)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_fetch_array);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_select_db, 0, 0, 2)
ZEND_ARG_INFO(0, mysql)
ZEND_ARG_INFO(0, database)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_select_db);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_close, 0, 0, 1)
ZEND_ARG_INFO(0, mysql)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_close);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_real_escape_string, 0, 0, 2)
ZEND_ARG_INFO(0, mysql)
ZEND_ARG_INFO(0, string)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_real_escape_string);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_errno, 0, 0, 1)
ZEND_ARG_INFO(0, mysql)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_errno);

ZEND_BEGIN_ARG_INFO_EX(arginfo_mysqli_error, 0, 0, 1)
ZEND_ARG_INFO(0, mysql)
ZEND_END_ARG_INFO()
PHP_FUNCTION(mysqli_error);

#define PHP_MYSQLI_POLYFILL_VERSION "0.0.1"

#endif