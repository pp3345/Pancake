
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeProxy.c                                            	*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "PancakeProxy.h"

ZEND_DECLARE_MODULE_GLOBALS(PancakeProxy)

const zend_function_entry PancakeProxy_functions[] = {
	ZEND_FE_END
};

zend_module_entry PancakeProxy_module_entry = {
	STANDARD_MODULE_HEADER,
	"PancakeProxy",
	PancakeProxy_functions,
	PHP_MINIT(PancakeProxy),
	NULL,
	NULL,
	NULL,
	NULL,
	PANCAKE_VERSION,
	STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_PANCAKE
ZEND_GET_MODULE(PancakeProxy)
#endif

PHP_MINIT_FUNCTION(PancakeProxy) {
#ifdef ZTS
	ZEND_INIT_MODULE_GLOBALS(PancakeProxy, NULL, NULL);
#endif

	return SUCCESS;
}
