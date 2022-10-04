#include "sapi/embed/php_embed.h"
#include <emscripten.h>
#include <stdlib.h>

#include "zend_globals_macros.h"
#include "zend_exceptions.h"
#include "zend_closures.h"
#include "php_main.h"
#include "SAPI.h"

#define ZEND_INCLUDE_FULL_WINDOWS_HEADERS

#include "php.h"
#include <stdio.h>
#include <fcntl.h>
#ifdef PHP_WIN32
#include "win32/time.h"
#include "win32/signal.h"
#include "win32/php_win32_globals.h"
#include "win32/winutil.h"
#include <process.h>
#endif
#if HAVE_SYS_TIME_H
#include <sys/time.h>
#endif
#if HAVE_UNISTD_H
#include <unistd.h>
#endif

#include <signal.h>
#include <locale.h>
#include "zend.h"
#include "zend_types.h"
#include "zend_extensions.h"
#include "php_ini.h"
#include "php_globals.h"
#include "php_main.h"
#include "php_syslog.h"
#include "fopen_wrappers.h"
#include "ext/standard/php_standard.h"
#include "ext/date/php_date.h"
#include "php_variables.h"
#include "ext/standard/credits.h"
#ifdef PHP_WIN32
#include <io.h>
#include "win32/php_registry.h"
#include "ext/standard/flock_compat.h"
#endif
#include "php_syslog.h"
#include "Zend/zend_exceptions.h"

#if PHP_SIGCHILD
#include <sys/types.h>
#include <sys/wait.h>
#endif

#include "zend_compile.h"
#include "zend_execute.h"
#include "zend_highlight.h"
#include "zend_extensions.h"
#include "zend_ini.h"
#include "zend_dtrace.h"
#include "zend_observer.h"
#include "zend_system_id.h"

#include "php_content_types.h"
#include "php_ticks.h"
#include "php_streams.h"
#include "php_open_temporary_file.h"

#include "SAPI.h"
#include "rfc1867.h"

#include "ext/standard/html_tables.h"

/*
   +----------------------------------------------------------------------+
   | Copyright (c) The PHP Group                                          |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | https://www.php.net/license/3_01.txt                                 |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Authors: Andi Gutmans <andi@php.net>                                 |
   |          Zeev Suraski <zeev@php.net>                                 |
   +----------------------------------------------------------------------+
*/

#ifndef BASIC_FUNCTIONS_H
#define BASIC_FUNCTIONS_H

#include <sys/stat.h>
#include <wchar.h>

#include "php_filestat.h"

#include "zend_highlight.h"

#include "url_scanner_ex.h"

/* for MT_N */
#include "ext/random/php_random.h"

#if defined(_WIN32) && !defined(__clang__)
#include <intrin.h>
#endif

extern zend_module_entry basic_functions_module;
#define basic_functions_module_ptr &basic_functions_module

PHP_MINIT_FUNCTION(basic);
PHP_MSHUTDOWN_FUNCTION(basic);
PHP_RINIT_FUNCTION(basic);
PHP_RSHUTDOWN_FUNCTION(basic);
PHP_MINFO_FUNCTION(basic);

ZEND_API void php_get_highlight_struct(zend_syntax_highlighter_ini *syntax_highlighter_ini);

PHP_MINIT_FUNCTION(user_filters);
PHP_RSHUTDOWN_FUNCTION(user_filters);
PHP_RSHUTDOWN_FUNCTION(browscap);

/* Left for BC (not binary safe!) */
PHPAPI int _php_error_log(int opt_err, const char *message, const char *opt, const char *headers);
PHPAPI int _php_error_log_ex(int opt_err, const char *message, size_t message_len, const char *opt, const char *headers);
PHPAPI int php_prefix_varname(zval *result, zend_string *prefix, const char *var_name, size_t var_name_len, bool add_underscore);

/* Deprecated type aliases -- use the standard types instead */
typedef uint32_t php_uint32;
typedef int32_t php_int32;

typedef struct _php_basic_globals {
	HashTable *user_shutdown_function_names;
	HashTable putenv_ht;
	zend_string *strtok_string;
	zend_string *ctype_string; /* current LC_CTYPE locale (or NULL for 'C') */
	bool locale_changed;   /* locale was changed and has to be restored */
	char *strtok_last;
	char strtok_table[256];
	size_t strtok_len;
	zend_fcall_info user_compare_fci;
	zend_fcall_info_cache user_compare_fci_cache;
	zend_llist *user_tick_functions;

	zval active_ini_file_section;

	/* pageinfo.c */
	zend_long page_uid;
	zend_long page_gid;
	zend_long page_inode;
	time_t page_mtime;

	/* filestat.c && main/streams/streams.c */
	zend_string *CurrentStatFile, *CurrentLStatFile;
	php_stream_statbuf ssb, lssb;

	/* syslog.c */
	char *syslog_device;

	/* var.c */
	unsigned serialize_lock; /* whether to use the locally supplied var_hash instead (__sleep/__wakeup) */
	struct {
		struct php_serialize_data *data;
		unsigned level;
	} serialize;
	struct {
		struct php_unserialize_data *data;
		unsigned level;
	} unserialize;

	/* url_scanner_ex.re */
	url_adapt_state_ex_t url_adapt_session_ex;
	HashTable url_adapt_session_hosts_ht;
	url_adapt_state_ex_t url_adapt_output_ex;
	HashTable url_adapt_output_hosts_ht;
	HashTable *user_filter_map;

	/* file.c */
#if defined(_REENTRANT)
	mbstate_t mblen_state;
#endif

	int umask;
	zend_long unserialize_max_depth;
} php_basic_globals;

#ifdef ZTS
#define BG(v) ZEND_TSRMG(basic_globals_id, php_basic_globals *, v)
PHPAPI extern int basic_globals_id;
#else
#define BG(v) (basic_globals.v)
PHPAPI extern php_basic_globals basic_globals;
#endif

PHPAPI zend_string *php_getenv(const char *str, size_t str_len);

PHPAPI double php_get_nan(void);
PHPAPI double php_get_inf(void);


PHPAPI extern bool register_user_shutdown_function(const char *function_name, size_t function_len, php_shutdown_function_entry2 *shutdown_function_entry);
PHPAPI extern bool remove_user_shutdown_function(const char *function_name, size_t function_len);
PHPAPI extern bool append_user_shutdown_function(php_shutdown_function_entry2 *shutdown_function_entry);


#endif /* BASIC_FUNCTIONS_H */

typedef struct _php_shutdown_function_entry2 {
	zend_fcall_info fci;
	zend_fcall_info_cache fci_cache;
} php_shutdown_function_entry2;


#ifdef ZTS
invalid!
#endif
#if defined(PHP_NEED_REENTRANCY)
invalid!
#endif

int main() { return 0; }


static int user_shutdown_function_call2(zval *zv) /* {{{ */
{
	php_shutdown_function_entry2 *shutdown_function_entry = Z_PTR_P(zv);
	zval retval;
	zend_result call_status;

	/* set retval zval for FCI struct */
	shutdown_function_entry->fci.retval = &retval;
	call_status = zend_call_function(&shutdown_function_entry->fci, &shutdown_function_entry->fci_cache);
	ZEND_ASSERT(call_status == SUCCESS);
	zval_ptr_dtor(&retval);

	return 0;
}

PHPAPI void php_call_shutdown_functions2(void) /* {{{ */
{
	if (BG(user_shutdown_function_names)) {
		zend_try {
			zend_hash_apply(BG(user_shutdown_function_names), user_shutdown_function_call2);
		} zend_end_try();
	}
}
/* }}} */

PHPAPI void php_free_shutdown_functions2(void) /* {{{ */
{
	if (BG(user_shutdown_function_names))
		zend_try {
			zend_hash_destroy(BG(user_shutdown_function_names));
			FREE_HASHTABLE(BG(user_shutdown_function_names));
			BG(user_shutdown_function_names) = NULL;
		} zend_catch {
			/* maybe shutdown method call exit, we just ignore it */
			FREE_HASHTABLE(BG(user_shutdown_function_names));
			BG(user_shutdown_function_names) = NULL;
		} zend_end_try();
}

int EMSCRIPTEN_KEEPALIVE pib_init()
{
    php_call_shutdown_functions2();

    // This works:
    return 1;
}

