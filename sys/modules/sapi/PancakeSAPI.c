
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI.c                                            	*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "PancakeSAPI.h"

ZEND_DECLARE_MODULE_GLOBALS(PancakeSAPI)

const zend_function_entry PancakeSAPI_functions[] = {
	ZEND_NS_FE("Pancake", SAPIRequest, NULL)
	ZEND_FE_END
};

zend_module_entry PancakeSAPI_module_entry = {
	STANDARD_MODULE_HEADER,
	"PancakeSAPI",
	PancakeSAPI_functions,
	PHP_MINIT(PancakeSAPI),
	NULL,
	PHP_RINIT(PancakeSAPI),
	NULL,
	NULL,
	PANCAKE_VERSION,
	STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_PANCAKE
ZEND_GET_MODULE(PancakeSAPI)
#endif

PHP_MINIT_FUNCTION(PancakeSAPI) {
#ifdef ZTS
	ZEND_INIT_MODULE_GLOBALS(PancakeSAPI, NULL, NULL);
#endif

	return SUCCESS;
}

PHP_RINIT_FUNCTION(PancakeSAPI) {
	zend_function *function;

	sapi_module.name = "pancake";

	// Hook some functions
	zend_hash_find(EG(function_table), "headers_sent", sizeof("headers_sent"), (void**) &function);
	PHP_headers_sent = function->internal_function.handler;
	function->internal_function.handler = Pancake_headers_sent;

	return SUCCESS;
}

PHP_FUNCTION(SAPIRequest) {
	zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z", &PANCAKE_SAPI_GLOBALS(request));
	PANCAKE_GLOBALS(JITGlobalsHTTPRequest) = PANCAKE_SAPI_GLOBALS(request);

	zend_activate_auto_globals(TSRMLS_C);
}
