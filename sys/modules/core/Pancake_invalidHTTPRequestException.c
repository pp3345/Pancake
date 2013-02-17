
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake_invalidHTTPRequestException.c                        */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "Pancake.h"

PHP_METHOD(invalidHTTPRequestException, __construct) {
	zval *message, *code, *header = NULL;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "zz|z", &message, &code, &header) == FAILURE) {
		RETURN_FALSE;
	}

	zend_update_property(invalidHTTPRequestException_ce, this_ptr, "message", sizeof("message") - 1, message TSRMLS_CC);
	zend_update_property(invalidHTTPRequestException_ce, this_ptr, "code", sizeof("code") - 1, code TSRMLS_CC);

	if(header != NULL) {
		zend_update_property(invalidHTTPRequestException_ce, this_ptr, "header", sizeof("header") - 1, header TSRMLS_CC);
	}
}

PHP_METHOD(invalidHTTPRequestException, getHeader) {
	zval *header = zend_read_property(invalidHTTPRequestException_ce, this_ptr, "header", sizeof("header") - 1, 1 TSRMLS_CC);
	RETURN_ZVAL(header, 1, 0);
}
