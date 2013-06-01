
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeProxy.h                                            	*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* See LICENSE file for license information                     */
    /****************************************************************/

#ifndef PANCAKE_PROXY_H
#define PANCAKE_PROXY_H

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "../core/Pancake.h"

extern zend_module_entry PancakeProxy_module_entry;
#define phpext_PancakeProxy_ptr &PancakeProxy_module_entry

#ifdef ZTS
#define PANCAKE_PROXY_GLOBALS(v) TSRMG(PancakeProxy_globals_id, zend_PancakeProxy_globals *, v)
#else
#define PANCAKE_PROXY_GLOBALS(v) (PancakeProxy_globals.v)
#endif

ZEND_BEGIN_MODULE_GLOBALS(PancakeProxy)
ZEND_END_MODULE_GLOBALS(PancakeProxy)
extern ZEND_DECLARE_MODULE_GLOBALS(PancakeProxy);

PHP_MINIT_FUNCTION(PancakeProxy);

#endif
