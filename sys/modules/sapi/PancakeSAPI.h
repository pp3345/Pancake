
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI.h                                            	*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#ifndef PANCAKE_SAPI_H
#define PANCAKE_SAPI_H

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "../core/Pancake.h"
#include "ext/standard/php_string.h"
#include "ext/standard/file.h"
#include <sys/epoll.h>

extern zend_module_entry PancakeSAPI_module_entry;
#define phpext_PancakeSAPI_ptr &PancakeSAPI_module_entry

#ifdef ZTS
#define PANCAKE_SAPI_GLOBALS(v) TSRMG(PancakeSAPI_globals_id, zend_PancakeSAPI_globals *, v)
#else
#define PANCAKE_SAPI_GLOBALS(v) (PancakeSAPI_globals.v)
#endif

void (*PHP_session_start)(INTERNAL_FUNCTION_PARAMETERS);
void Pancake_session_start(INTERNAL_FUNCTION_PARAMETERS);
void Pancake_debug_backtrace(INTERNAL_FUNCTION_PARAMETERS);

void (*PHP_debug_print_backtrace)(INTERNAL_FUNCTION_PARAMETERS);
void Pancake_debug_print_backtrace(INTERNAL_FUNCTION_PARAMETERS);

void (*PHP_list_entry_destructor)(void *ptr);

void PancakeSAPIExceptionHook(zval *exception TSRMLS_DC);
ZEND_API void (*PancakeSAPIPreviousExceptionHook)(zval *ex TSRMLS_DC);

void PancakeSAPIGlobalsPrepare(TSRMLS_D);

ZEND_BEGIN_MODULE_GLOBALS(PancakeSAPI)
	zval *request;
	zend_bool inExecution;
	char *output;
	unsigned int outputLength;
	zend_module_entry *DeepTrace;
	zval *vHost;
	zend_bool autoDeleteFunctions;
	zend_bool autoDeleteClasses;
	zend_bool autoDeleteIncludes;
	zend_bool autoDeleteConstants;
	HashTable *autoDeleteFunctionsExcludes;
	HashTable *autoDeleteIncludesExcludes;
	HashTable *autoDeleteClassesExcludes;
	uint functionsPre;
	uint classesPre;
	uint includesPre;
	int epoll;
	int listenSocket;
	int controlSocket;
	int clientSocket;
	zval *errorHandler;
	zval *documentRoot;
	long processingLimit;
	long processedRequests;
	zend_bool exit;
	long timeout;
	HashTable *persistentSymbols;
	zend_bool CodeCache;
	zend_bool haveCriticalDeletions;
	char *SAPIPHPVersionHeader;
	ushort SAPIPHPVersionHeader_len;

	int JIT_GET;
	int JIT_COOKIE;
	int JIT_SERVER;
	int JIT_REQUEST;
	int JIT_POST;
	int JIT_FILES;
	int JIT_ENV;
ZEND_END_MODULE_GLOBALS(PancakeSAPI)
extern ZEND_DECLARE_MODULE_GLOBALS(PancakeSAPI);

PHP_MINIT_FUNCTION(PancakeSAPI);
PHP_RINIT_FUNCTION(PancakeSAPI);
PHP_RSHUTDOWN_FUNCTION(PancakeSAPI);

PHP_FUNCTION(SAPIPrepare);
PHP_FUNCTION(SAPIFinishRequest);
PHP_FUNCTION(SAPIWait);
PHP_FUNCTION(SAPIExitHandler);
PHP_FUNCTION(SAPICodeCachePrepare);

PHP_FUNCTION(SAPIFetchSERVER);
PHP_FUNCTION(SAPICodeCacheJIT);

PHP_FUNCTION(apache_child_terminate);
PHP_FUNCTION(apache_request_headers);
PHP_FUNCTION(apache_response_headers);

/* Persistent constants that are not actually persistent */
#define PANCAKE_PSEUDO_PERSISTENT 1 << 5

#endif
