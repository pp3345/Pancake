
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeTLS.c                                            		*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "PancakeTLS.h"

ZEND_DECLARE_MODULE_GLOBALS(PancakeTLS)

const zend_function_entry PancakeTLS_functions[] = {
	ZEND_NS_FE("Pancake", TLSCreateContext, NULL)
	ZEND_NS_FE("Pancake", TLSInitializeConnection, NULL)
	ZEND_NS_FE("Pancake", TLSAccept, NULL)
	ZEND_NS_FE("Pancake", TLSRead, NULL)
	ZEND_NS_FE("Pancake", TLSWrite, NULL)
	ZEND_NS_FE("Pancake", TLSShutdown, NULL)
	ZEND_NS_FE("Pancake", TLSCipherName, NULL)
	ZEND_FE_END
};

zend_module_entry PancakeTLS_module_entry = {
	STANDARD_MODULE_HEADER,
	"PancakeTLS",
	PancakeTLS_functions,
	PHP_MINIT(PancakeTLS),
	NULL,
	NULL,
	PHP_RSHUTDOWN(PancakeTLS),
	NULL,
	PANCAKE_VERSION,
	STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_PANCAKE
ZEND_GET_MODULE(PancakeTLS)
#endif

PANCAKE_API void TLSFree(SSL **ssl) {
	SSL_free(*ssl);
}

PHP_MINIT_FUNCTION(PancakeTLS) {
	SSL_library_init();
	OpenSSL_add_all_algorithms();
	ALLOC_HASHTABLE(PANCAKE_TLS_GLOBALS(TLSSessions));
	zend_hash_init(PANCAKE_TLS_GLOBALS(TLSSessions), 0, NULL, (void (*)(void *)) TLSFree, 0);

	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(PancakeTLS) {
	SSL_CTX_free(PANCAKE_TLS_GLOBALS(context));
	zend_hash_destroy(PANCAKE_TLS_GLOBALS(TLSSessions));
	FREE_HASHTABLE(PANCAKE_TLS_GLOBALS(TLSSessions));

	return SUCCESS;
}

PHP_FUNCTION(TLSCreateContext) {
	char *certificateChainFile, *privateKeyFile;
	int certificateChainFile_len, privateKeyFile_len;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &certificateChainFile, &certificateChainFile_len, &privateKeyFile, &privateKeyFile_len) == FAILURE) {
		RETURN_FALSE;
	}

	PANCAKE_TLS_GLOBALS(context) = SSL_CTX_new(SSLv23_server_method());

	if(SSL_CTX_use_certificate_chain_file(PANCAKE_TLS_GLOBALS(context), certificateChainFile) <= 0) {
		zend_error(E_WARNING, "Failed to load certificate chain file %s", certificateChainFile);
		free(PANCAKE_TLS_GLOBALS(context));
		RETURN_FALSE;
	}

	if(SSL_CTX_use_PrivateKey_file(PANCAKE_TLS_GLOBALS(context), privateKeyFile, X509_FILETYPE_PEM) <= 0) {
		zend_error(E_WARNING, "Failed to load private key file %s", privateKeyFile);
		free(PANCAKE_TLS_GLOBALS(context));
		RETURN_FALSE;
	}

	if(!SSL_CTX_check_private_key(PANCAKE_TLS_GLOBALS(context))) {
		zend_error(E_WARNING, "Private key and certificate don't match");
		free(PANCAKE_TLS_GLOBALS(context));
		RETURN_FALSE;
	}

	RETURN_TRUE;
}

PHP_FUNCTION(TLSInitializeConnection) {
	zval *socket;
	php_socket *php_sock;
	SSL *ssl = SSL_new(PANCAKE_TLS_GLOBALS(context));

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r", &socket) == FAILURE) {
		RETURN_FALSE;
	}

	ZEND_FETCH_RESOURCE(php_sock, php_socket*, &socket, -1, "Socket", php_sockets_le_socket());

	SSL_set_fd(ssl, php_sock->bsd_socket);

	zend_hash_index_update(PANCAKE_TLS_GLOBALS(TLSSessions), Z_LVAL_P(socket), (void*) &ssl, sizeof(SSL*), NULL);
}

PHP_FUNCTION(TLSAccept) {
	long socketID;
	SSL **ssl;
	int retval, error;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &socketID) == FAILURE) {
		RETURN_FALSE;
	}

	zend_hash_index_find(PANCAKE_TLS_GLOBALS(TLSSessions), socketID, (void**) &ssl);

	switch(retval = SSL_accept(*ssl)) {
		case 1:
			RETURN_LONG(1);
			break;
		case 0:
			RETURN_LONG(0);
			break;
		default:
			error = SSL_get_error(*ssl, retval);
			if(error == SSL_ERROR_WANT_READ) {
				RETURN_LONG(2);
			} else if(error == SSL_ERROR_WANT_WRITE) {
				RETURN_LONG(3);
			} else {
				RETURN_LONG(0);
			}
			break;
	}
}

PHP_FUNCTION(TLSRead) {
	long socketID, length;
	SSL **ssl;
	char *buffer;
	int retval;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ll", &socketID, &length) == FAILURE) {
		RETURN_FALSE;
	}

	zend_hash_index_find(PANCAKE_TLS_GLOBALS(TLSSessions), socketID, (void**) &ssl);

	buffer = emalloc(length + 1);

	retval = SSL_read(*ssl, buffer, length);

	if(retval > 0) {
		buffer = erealloc(buffer, retval + 1);
		buffer[retval] = '\0';
		RETURN_STRINGL(buffer, retval, 0);
	} else if(retval == 0) {
		efree(buffer);
		RETURN_STRINGL("", 0, 1);
	} else {
		int error = SSL_get_error(*ssl, retval);

		efree(buffer);
		if(error == SSL_ERROR_WANT_READ) {
			RETURN_LONG(2);
		} else if(error == SSL_ERROR_WANT_WRITE) {
			RETURN_LONG(3);
		} else {
			RETURN_STRINGL("", 0, 1);
		}
	}
}

PHP_FUNCTION(TLSWrite) {
	long socketID, length;
	char *buffer;
	SSL **ssl;
	int retval;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ls", &socketID, &buffer, &length) == FAILURE) {
		RETURN_FALSE;
	}

	zend_hash_index_find(PANCAKE_TLS_GLOBALS(TLSSessions), socketID, (void**) &ssl);

	retval = SSL_write(*ssl, buffer, length);

	if(retval > 0) {
		RETURN_LONG(retval);
	} else {
		int error = SSL_get_error(*ssl, retval);

		if(error == SSL_ERROR_WANT_READ) {
			RETURN_LONG(-1);
		} else if(error == SSL_ERROR_WANT_WRITE) {
			RETURN_LONG(0);
		} else {
			RETURN_FALSE;
		}
	}
}

PHP_FUNCTION(TLSShutdown) {
	long socketID;
	SSL **ssl;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &socketID) == FAILURE) {
		RETURN_FALSE;
	}

	zend_hash_index_find(PANCAKE_TLS_GLOBALS(TLSSessions), socketID, (void**) &ssl);

	SSL_shutdown(*ssl);
	// We don't check for the return value currently

	zend_hash_index_del(PANCAKE_TLS_GLOBALS(TLSSessions), socketID);
}

PHP_FUNCTION(TLSCipherName) {
	long socketID;
	SSL **ssl;
	const SSL_CIPHER *cipher;
	const char *cipherName;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &socketID) == FAILURE) {
		RETURN_FALSE;
	}

	zend_hash_index_find(PANCAKE_TLS_GLOBALS(TLSSessions), socketID, (void**) &ssl);

	cipher = SSL_get_current_cipher(*ssl);

	if(cipher == NULL) {
		RETURN_STRINGL("", 0, 1);
	}

	cipherName = SSL_CIPHER_get_name(cipher);
	RETURN_STRING(cipherName, 1);
}
