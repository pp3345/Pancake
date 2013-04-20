
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
