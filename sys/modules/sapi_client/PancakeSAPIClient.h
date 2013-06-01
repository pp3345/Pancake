
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPIClient.h                                          */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* See LICENSE file for license information                     */
    /****************************************************************/

#ifndef PANCAKE_SAPI_CLIENT_H
#define PANCAKE_SAPI_CLIENT_H

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "../core/Pancake.h"

extern zend_module_entry PancakeSAPIClient_module_entry;
#define phpext_PancakeSAPIClient_ptr &PancakeSAPIClient_module_entry

#ifdef ZTS
#define PANCAKE_SAPI_CLIENT_GLOBALS(v) TSRMG(PancakeSAPIClient_globals_id, zend_PancakeSAPIClient_globals *, v)
#else
#define PANCAKE_SAPI_CLIENT_GLOBALS(v) (PancakeSAPIClient_globals.v)
#endif

extern zend_class_entry *SAPIClient_ce;

ZEND_BEGIN_MODULE_GLOBALS(PancakeSAPIClient)
ZEND_END_MODULE_GLOBALS(PancakeSAPIClient)
extern ZEND_DECLARE_MODULE_GLOBALS(PancakeSAPIClient);

PHP_MINIT_FUNCTION(PancakeSAPIClient);

PHP_METHOD(SAPIClient, __construct);
PHP_METHOD(SAPIClient, makeRequest);
PHP_METHOD(SAPIClient, SAPIData);

#define PANCAKE_SAPI_HEADER_DATA 0
#define PANCAKE_SAPI_BODY_DATA 1
#define PANCAKE_SAPI_FINISH_REQUEST 2

#endif
