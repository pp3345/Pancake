
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPIClient.c                                          */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "PancakeSAPIClient.h"

ZEND_DECLARE_MODULE_GLOBALS(PancakeSAPIClient)

const zend_function_entry PancakeSAPIClient_functions[] = {
	ZEND_ME(SAPIClient, __construct, NULL, ZEND_ACC_PUBLIC | ZEND_ACC_CTOR)
	ZEND_ME(SAPIClient, makeRequest, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(SAPIClient, SAPIData, NULL, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

zend_module_entry PancakeSAPIClient_module_entry = {
	STANDARD_MODULE_HEADER,
	"PancakeSAPIClient",
	NULL,
	PHP_MINIT(PancakeSAPIClient),
	NULL,
	NULL,
	NULL,
	NULL,
	PANCAKE_VERSION,
	STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_PANCAKE
ZEND_GET_MODULE(PancakeSAPIClient)
#endif

zend_class_entry *SAPIClient_ce;

PHP_MINIT_FUNCTION(PancakeSAPIClient) {
	zend_class_entry SAPIClient;

#ifdef ZTS
	ZEND_INIT_MODULE_GLOBALS(PancakeSAPIClient, NULL, NULL);
#endif

	INIT_NS_CLASS_ENTRY(SAPIClient, "Pancake", "SAPIClient", PancakeSAPIClient_functions);
	SAPIClient.create_object = PancakeCreateObject;
	SAPIClient_ce = zend_register_internal_class(&SAPIClient TSRMLS_CC);
	zend_declare_property_long(SAPIClient_ce, "freeSocket", sizeof("freeSocket") - 1, 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(SAPIClient_ce, "address", sizeof("address") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);

	return SUCCESS;
}

PHP_METHOD(SAPIClient, __construct) {
	zval *vHost, *address;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z", &vHost) == FAILURE) {
		return;
	}

	address = zend_read_property(NULL, vHost, "phpSocketName", sizeof("phpSocketName") - 1, 0 TSRMLS_CC);
	PancakeQuickWriteProperty(this_ptr, address, "address", sizeof("address"), HASH_OF_address TSRMLS_CC);

	/* We must set this property in order to be able to directly modify freeSocket later on */
	PancakeQuickWritePropertyLong(this_ptr, "freeSocket", sizeof("freeSocket"), HASH_OF_freeSocket, 0);
}

PHP_METHOD(SAPIClient, makeRequest) {
	zval **HTTPRequest, *freeSocket;
	int sock;
	php_serialize_data_t varHash;
	smart_str buf = {0};
	ssize_t offset = 0;
	zend_bool newSocketUsed = 0;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "Z", &HTTPRequest) == FAILURE) {
		RETURN_FALSE;
	}

	FAST_READ_PROPERTY(freeSocket, this_ptr, "freeSocket", sizeof("freeSocket") - 1, HASH_OF_freeSocket);

	/* Get socket for transmission */
	if(Z_LVAL_P(freeSocket)) {
		sock = Z_LVAL_P(freeSocket);
		Z_LVAL_P(freeSocket) = 0;
	} else {
		fetchSocket: {
			zval *address;
			struct sockaddr_un s_un = {0};

			newSocketUsed = 1;

			FAST_READ_PROPERTY(address, this_ptr, "address", sizeof("address") - 1, HASH_OF_address);

			s_un.sun_family = AF_UNIX;
			memcpy(&s_un.sun_path, Z_STRVAL_P(address), Z_STRLEN_P(address));

			sock = socket(AF_UNIX, SOCK_STREAM, 0);

			if(connect(sock, (struct sockaddr*) &s_un, (socklen_t)(XtOffsetOf(struct sockaddr_un, sun_path) + Z_STRLEN_P(address)))) {
				close(sock);
				zend_error(E_WARNING, "%s", strerror(errno));
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Failed to establish connection to PHP SAPI",
					sizeof("Failed to establish connection to PHP SAPI") - 1, 500);
				return;
			}
		}
	}

	PHP_VAR_SERIALIZE_INIT(varHash);
	php_var_serialize(&buf, HTTPRequest, &varHash TSRMLS_CC);
	PHP_VAR_SERIALIZE_DESTROY(varHash);

	/* PHP serialize() checks for EG(exception) so probably it is a good idea here too */
	if(EG(exception)) {
		smart_str_free(&buf);
		close(sock);
		RETURN_FALSE;
	}

	/* Write packet length */
	if(write(sock, &buf.len, sizeof(size_t)) == -1) {
		smart_str_free(&buf);
		close(sock);

		if(!newSocketUsed) {
			goto fetchSocket;
		} else {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Failed to send request to PHP SAPI",
				sizeof("Failed to send request to PHP SAPI") - 1, 500);
			return;
		}
	}

	/* Write packet */
	while(offset < buf.len) {
		ssize_t result = write(sock, &buf.c[offset], buf.len - offset);
		if(result == -1) {
			close(sock);
			smart_str_free(&buf);
			zend_error(E_WARNING, "%s", strerror(errno));

			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Failed to send request to PHP SAPI",
				sizeof("Failed to send request to PHP SAPI") - 1, 500);
			return;
		}

		offset += result;
	}

	smart_str_free(&buf);

	RETURN_LONG(sock);
}

PHP_METHOD(SAPIClient, SAPIData) {
	long fd;
	zval *HTTPRequest;
	char packetType = -1;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "lz", &fd, &HTTPRequest) == FAILURE) {
		RETURN_FALSE;
	}

	if(read(fd, &packetType, 1) == -1) {
		close(fd);
		zend_error(E_WARNING, "%s", strerror(errno));

		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("PHP SAPI unexpectedly closed network connection",
				sizeof("PHP SAPI unexpectedly closed network connection") - 1, 500);
		return;
	}

	switch(packetType) {
		case PANCAKE_SAPI_HEADER_DATA: {
			size_t headerLength = 0, offset = 0;
			char *headerBuf, *statusLine, *headerBufP;
			zval *headerData;
			php_unserialize_data_t varHash;
			short statusCode = 0, statusLineLength = 0;

			read(fd, &headerLength, sizeof(size_t));

			headerBuf = emalloc(headerLength + 1);
			headerBuf[headerLength] = '\0';

			while(offset < headerLength) {
				size_t readLength = read(fd, &headerBuf[offset], headerLength - offset);
				if(readLength == -1) {
					efree(headerBuf);
					close(fd);

					PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("PHP SAPI unexpectedly closed network connection",
						sizeof("PHP SAPI unexpectedly closed network connection") - 1, 500);
					return;
				}

				offset += readLength;
			}

			MAKE_STD_ZVAL(headerData);

			headerBufP = headerBuf;

			PHP_VAR_UNSERIALIZE_INIT(varHash);
			if (!php_var_unserialize(&headerData, (const unsigned char**) &headerBuf, headerBuf + headerLength, &varHash TSRMLS_CC)
			|| Z_TYPE_P(headerData) != IS_ARRAY) {
				/* Malformed value */
				PHP_VAR_UNSERIALIZE_DESTROY(varHash);
				zval_ptr_dtor(&headerData);
				close(fd);
				efree(headerBuf);

				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("PHP SAPI returned malformed value",
					sizeof("PHP SAPI returned malformed value") - 1, 500);

				return;
			}
			PHP_VAR_UNSERIALIZE_DESTROY(varHash);

			efree(headerBufP);

			PancakeQuickWriteProperty(HTTPRequest, headerData, "answerHeaders", sizeof("answerHeaders"), HASH_OF_answerHeaders TSRMLS_CC);
			Z_DELREF_P(headerData);

			read(fd, &statusCode, sizeof(short));
			PancakeQuickWritePropertyLong(HTTPRequest, "answerCode", sizeof("answerCode"), HASH_OF_answerCode, (long) statusCode);

			read(fd, &statusLineLength, sizeof(short));
			if(statusLineLength) {
				offset = 0;
				statusLine = emalloc(statusLineLength + 1);
				statusLine[statusLineLength] = '\0';

				while(offset < statusLineLength) {
					size_t readLength = read(fd, &statusLine[offset], statusLineLength - offset);
					if(readLength == -1) {
						efree(statusLine);
						close(fd);

						PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("PHP SAPI unexpectedly closed network connection",
							sizeof("PHP SAPI unexpectedly closed network connection") - 1, 500);
						return;
					}

					offset += readLength;
				}

				PancakeQuickWritePropertyString(HTTPRequest, "answerCodeString", sizeof("answerCodeString"), HASH_OF_answerCodeString, statusLine, statusLineLength, 0);
			}

			RETURN_TRUE;
		} break;
		case PANCAKE_SAPI_BODY_DATA: {
			unsigned int length = 0, offset = 0;
			char *buf;
			zval *answerBody;

			read(fd, &length, sizeof(unsigned int));
			buf = emalloc(length + 1);
			buf[length] = '\0';

			while(offset < length) {
				size_t readLength = read(fd, &buf[offset], length - offset);
				if(readLength == -1) {
					efree(buf);
					close(fd);

					PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("PHP SAPI unexpectedly closed network connection",
						sizeof("PHP SAPI unexpectedly closed network connection") - 1, 500);
					return;
				}

				offset += readLength;
			}

			FAST_READ_PROPERTY(answerBody, HTTPRequest, "answerBody", sizeof("answerBody") - 1, HASH_OF_answerBody);

			if(!Z_STRLEN_P(answerBody)) {
				PancakeQuickWritePropertyString(HTTPRequest, "answerBody", sizeof("answerBody"), HASH_OF_answerBody, buf, length, 0);
			} else {
				Z_STRVAL_P(answerBody) = erealloc(Z_STRVAL_P(answerBody), Z_STRLEN_P(answerBody) + length + 1);
				memcpy(&Z_STRVAL_P(answerBody)[Z_STRLEN_P(answerBody)], buf, length + 1);
				Z_STRLEN_P(answerBody) += length;
				efree(buf);
			}

			RETURN_TRUE;
		} break;
		case PANCAKE_SAPI_FINISH_REQUEST: {
			zval *freeSocket;

			FAST_READ_PROPERTY(freeSocket, this_ptr, "freeSocket", sizeof("freeSocket") - 1, HASH_OF_freeSocket);
			if(!Z_LVAL_P(freeSocket)) {
				Z_LVAL_P(freeSocket) = fd;
			} else {
				close(fd);
			}
			RETURN_FALSE;
		} break;
		default: {
			close(fd);
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("PHP SAPI returned malformed value",
								sizeof("PHP SAPI returned malformed value") - 1, 500);
			RETURN_FALSE;
		}
	}
}
