
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI_PHPDependencies.c                                */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* See LICENSE file for license information                     */
    /****************************************************************/

#include "PancakeSAPI.h"

/* Those functions are copied from PHP since they are not declared in any header files */
static int user_shutdown_function_call(php_shutdown_function_entry *shutdown_function_entry TSRMLS_DC)
{
	zval retval;
	char *function_name;

	if (!zend_is_callable(shutdown_function_entry->arguments[0], 0, &function_name TSRMLS_CC)) {
		php_error(E_WARNING, "(Registered shutdown functions) Unable to call %s() - function does not exist", function_name);
		if (function_name) {
			efree(function_name);
		}
		return 0;
	}
	if (function_name) {
		efree(function_name);
	}

	if (call_user_function(EG(function_table), NULL,
				shutdown_function_entry->arguments[0],
				&retval,
				shutdown_function_entry->arg_count - 1,
				shutdown_function_entry->arguments + 1
				TSRMLS_CC ) == SUCCESS)
	{
		zval_dtor(&retval);
	}
	return 0;
}

void php_free_shutdown_functions(TSRMLS_D)
{
	if (BG(user_shutdown_function_names))
		zend_try {
			zend_hash_destroy(BG(user_shutdown_function_names));
			FREE_HASHTABLE(BG(user_shutdown_function_names));
			BG(user_shutdown_function_names) = NULL;
		} zend_catch {
			/* maybe shutdown method call exit, we just ignore it */
			FREE_HASHTABLE(BG(user_shutdown_function_names));
			BG(user_shutdown_function_names) = NULL;
		} zend_end_try();
}

void php_call_shutdown_functions(TSRMLS_D)
{
	if (BG(user_shutdown_function_names)) {
		zend_try {
			zend_hash_apply(BG(user_shutdown_function_names), (apply_func_t) user_shutdown_function_call TSRMLS_CC);
		}
		zend_end_try();
		php_free_shutdown_functions(TSRMLS_C);
	}
}

