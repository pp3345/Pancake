
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI_Hooks.c                                          */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "PancakeSAPI.h"

void Pancake_session_start(INTERNAL_FUNCTION_PARAMETERS) {
	zend_is_auto_global_quick("_GET", sizeof("_GET") - 1, HASH_OF__GET TSRMLS_CC);
	zend_is_auto_global_quick("_POST", sizeof("_POST") - 1, HASH_OF__POST TSRMLS_CC);
	zend_is_auto_global_quick("_COOKIE", sizeof("_COOKIE") - 1, HASH_OF__COOKIE TSRMLS_CC);
	zend_is_auto_global_quick("_SERVER", sizeof("_SERVER") - 1, HASH_OF__SERVER TSRMLS_CC);

	PHP_session_start(INTERNAL_FUNCTION_PARAM_PASSTHRU);
}

void Pancake_debug_backtrace(INTERNAL_FUNCTION_PARAMETERS) {
	long limit = 0;
	long options = DEBUG_BACKTRACE_PROVIDE_OBJECT;

	if(!PANCAKE_SAPI_GLOBALS(inExecution)
	|| zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|ll", &options, &limit) == FAILURE) {
		return;
	}

	if(limit) {
		limit += 2;
	}

	zend_fetch_debug_backtrace(return_value, 1, options, limit TSRMLS_CC);

	// Delete Pancake trace parts
	zend_hash_internal_pointer_end(Z_ARRVAL_P(return_value));
	zend_hash_index_del(Z_ARRVAL_P(return_value), Z_ARRVAL_P(return_value)->pInternalPointer->h);

	zend_hash_internal_pointer_end(Z_ARRVAL_P(return_value));
	zend_hash_index_del(Z_ARRVAL_P(return_value), Z_ARRVAL_P(return_value)->pInternalPointer->h);
}
