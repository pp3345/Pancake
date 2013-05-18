
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI_Functions.c                                      */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "PancakeSAPI.h"

PHP_FUNCTION(apache_child_terminate) {
	PANCAKE_SAPI_GLOBALS(exit) = 1;
}

PHP_FUNCTION(apache_request_headers) {
	zval *headers;
	FAST_READ_PROPERTY(headers, PANCAKE_SAPI_GLOBALS(request), "requestHeaders", sizeof("requestHeaders") - 1, HASH_OF_requestHeaders);

	RETVAL_ZVAL(headers, 1, 0);
}

PHP_FUNCTION(apache_response_headers) {
	zval *headers;
	FAST_READ_PROPERTY(headers, PANCAKE_SAPI_GLOBALS(request), "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);

	RETVAL_ZVAL(headers, 1, 0);
}
