
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake_invalidHTTPRequestException.c                        */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* See LICENSE file for license information                     */
    /****************************************************************/

#include "Pancake.h"

PHP_METHOD(invalidHTTPRequestException, __construct) {
	zval *message, *code, *header = NULL;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "zz|z", &message, &code, &header) == FAILURE) {
		RETURN_FALSE;
	}

	PancakeQuickWriteProperty(this_ptr, message, "message", sizeof("message"), HASH_OF_message TSRMLS_CC);
	PancakeQuickWriteProperty(this_ptr, code, "code", sizeof("code"), HASH_OF_code TSRMLS_CC);

	if(header != NULL) {
		PancakeQuickWriteProperty(this_ptr, header, "header", sizeof("header"), HASH_OF_header TSRMLS_CC);
	}
}

PHP_METHOD(invalidHTTPRequestException, getHeader) {
	zval *header = zend_read_property(invalidHTTPRequestException_ce, this_ptr, "header", sizeof("header") - 1, 1 TSRMLS_CC);
	RETURN_ZVAL(header, 1, 0);
}
