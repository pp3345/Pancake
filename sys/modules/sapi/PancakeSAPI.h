
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
#include <sys/epoll.h>

extern zend_module_entry PancakeSAPI_module_entry;
#define phpext_PancakeSAPI_ptr &PancakeSAPI_module_entry

#ifdef ZTS
#define PANCAKE_SAPI_GLOBALS(v) TSRMG(PancakeSAPI_globals_id, zend_PancakeSAPI_globals *, v)
#else
#define PANCAKE_SAPI_GLOBALS(v) (PancakeSAPI_globals.v)
#endif

void (*PHP_headers_sent)(INTERNAL_FUNCTION_PARAMETERS);
void Pancake_headers_sent(INTERNAL_FUNCTION_PARAMETERS);

void (*PHP_session_start)(INTERNAL_FUNCTION_PARAMETERS);
void Pancake_session_start(INTERNAL_FUNCTION_PARAMETERS);

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
	HashTable *autoDeleteFunctionsExcludes;
	HashTable *autoDeleteIncludesExcludes;
	uint functionsPre;
	uint classesPre;
	uint includesPre;
	int epoll;
	int listenSocket;
	int controlSocket;
	int clientSocket;
	zval *errorHandler;
	zval *documentRoot;
ZEND_END_MODULE_GLOBALS(PancakeSAPI)
extern ZEND_DECLARE_MODULE_GLOBALS(PancakeSAPI);

PHP_MINIT_FUNCTION(PancakeSAPI);
PHP_RINIT_FUNCTION(PancakeSAPI);
PHP_RSHUTDOWN_FUNCTION(PancakeSAPI);

PHP_FUNCTION(SAPIPrepare);
PHP_FUNCTION(SAPIFinishRequest);
PHP_FUNCTION(SAPIFlushBuffers);
PHP_FUNCTION(SAPIPostRequestCleanup);
PHP_FUNCTION(SAPIWait);
PHP_FUNCTION(SAPIExitHandler);

#endif
