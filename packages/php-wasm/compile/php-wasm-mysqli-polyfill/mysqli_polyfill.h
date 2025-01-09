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
#ifndef MYSQLI_REPORT_CLOSE
#define MYSQLI_REPORT_CLOSE 8
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

#define PHP_MYSQLI_POLYFILL_VERSION "0.0.1"

#endif