
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

extern zend_module_entry PancakeSAPI_module_entry;
#define phpext_PancakeSAPI_ptr &PancakeSAPI_module_entry

#ifdef ZTS
#define PANCAKE_SAPI_GLOBALS(v) TSRMG(PancakeSAPI_globals_id, zend_PancakeSAPI_globals *, v)
#else
#define PANCAKE_SAPI_GLOBALS(v) (PancakeSAPI_globals.v)
#endif

void (*PHP_headers_sent)(INTERNAL_FUNCTION_PARAMETERS);

ZEND_BEGIN_MODULE_GLOBALS(PancakeSAPI)
	zval *request;
	zend_bool inExecution;
ZEND_END_MODULE_GLOBALS(PancakeSAPI)
extern ZEND_DECLARE_MODULE_GLOBALS(PancakeSAPI);

void Pancake_headers_sent(INTERNAL_FUNCTION_PARAMETERS);

PHP_MINIT_FUNCTION(PancakeSAPI);
PHP_RINIT_FUNCTION(PancakeSAPI);

PHP_FUNCTION(SAPIRequest);
PHP_FUNCTION(SAPIFinishRequest);

#endif
