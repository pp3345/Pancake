
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeTLS.h                                            		*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* See LICENSE file for license information                     */
    /****************************************************************/

#ifndef PANCAKE_TLS_H
#define PANCAKE_TLS_H

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "../core/Pancake.h"
#include "openssl/ssl.h"
#include "Zend/zend_list.h"

extern zend_module_entry PancakeTLS_module_entry;
#define phpext_PancakeTLS_ptr &PancakeTLS_module_entry

#ifdef ZTS
#define PANCAKE_TLS_GLOBALS(v) TSRMG(PancakeTLS_globals_id, zend_PancakeTLS_globals *, v)
#else
#define PANCAKE_TLS_GLOBALS(v) (PancakeTLS_globals.v)
#endif

ZEND_BEGIN_MODULE_GLOBALS(PancakeTLS)
	SSL_CTX *context;
	HashTable *TLSSessions;
ZEND_END_MODULE_GLOBALS(PancakeTLS)
extern ZEND_DECLARE_MODULE_GLOBALS(PancakeTLS);

PHP_MINIT_FUNCTION(PancakeTLS);
PHP_RSHUTDOWN_FUNCTION(PancakeTLS);

PHP_FUNCTION(TLSCreateContext);
PHP_FUNCTION(TLSInitializeConnection);
PHP_FUNCTION(TLSAccept);
PHP_FUNCTION(TLSRead);
PHP_FUNCTION(TLSWrite);
PHP_FUNCTION(TLSShutdown);

#endif
