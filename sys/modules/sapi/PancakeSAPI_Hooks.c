
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI_Hooks.c                                          */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "PancakeSAPI.h"

void Pancake_headers_sent(INTERNAL_FUNCTION_PARAMETERS) {
	SG(headers_sent) = 0;
	PHP_headers_sent(INTERNAL_FUNCTION_PARAM_PASSTHRU);
}

void Pancake_session_start(INTERNAL_FUNCTION_PARAMETERS) {
	zend_is_auto_global_quick("_GET", sizeof("_GET") - 1, HASH_OF__GET TSRMLS_CC);
	zend_is_auto_global_quick("_POST", sizeof("_POST") - 1, HASH_OF__POST TSRMLS_CC);
	zend_is_auto_global_quick("_COOKIE", sizeof("_COOKIE") - 1, HASH_OF__COOKIE TSRMLS_CC);
	zend_is_auto_global_quick("_SERVER", sizeof("_SERVER") - 1, HASH_OF__SERVER TSRMLS_CC);

	PHP_session_start(INTERNAL_FUNCTION_PARAM_PASSTHRU);
}
