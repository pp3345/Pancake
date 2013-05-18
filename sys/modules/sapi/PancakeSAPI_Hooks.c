
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

void Pancake_debug_print_backtrace(INTERNAL_FUNCTION_PARAMETERS) {
	char *offset;
	zval *output;

	MAKE_STD_ZVAL(output);

	if(ZEND_NUM_ARGS() == 2) {
		// Ugly stack manipulation
		zval **limit = (zval**) (zend_vm_stack_top(TSRMLS_C) - 2);
		if(Z_LVAL_PP(limit)) {
			Z_LVAL_PP(limit) += 2;
		}
	}

	php_output_start_user(NULL, 0, PHP_OUTPUT_HANDLER_STDFLAGS TSRMLS_CC);
	PHP_debug_print_backtrace(INTERNAL_FUNCTION_PARAM_PASSTHRU);
	php_output_get_contents(output TSRMLS_CC);
	php_output_discard(TSRMLS_C);

	offset = memrchr(Z_STRVAL_P(output), '\n', Z_STRLEN_P(output));
	offset = memrchr(Z_STRVAL_P(output), '\n', offset - Z_STRVAL_P(output));
	offset = memrchr(Z_STRVAL_P(output), '\n', offset - Z_STRVAL_P(output));
	PHPWRITE(Z_STRVAL_P(output), offset - Z_STRVAL_P(output) + 1);
	zval_ptr_dtor(&output);
}
