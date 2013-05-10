
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
