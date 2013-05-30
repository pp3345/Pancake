
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

static void PancakeSAPIClientWriteHeaderSet(int sock, HashTable *headers TSRMLS_DC) {
	char *key, *buf;
	int key_len,
		numElements = zend_hash_num_elements(headers),
		offset,
		bufSize;
	zval **value;

	if(!numElements) {
		write(sock, "\0\0\0\0", 4);
		return;
	}

	bufSize = 1024;
	offset = sizeof(int);
	buf = emalloc(1024);
	memcpy(buf, &numElements, sizeof(int));

	PANCAKE_FOREACH_KEY(headers, key, key_len, value) {
		if(bufSize < offset + key_len) {
			bufSize += key_len + 100;
			buf = erealloc(buf, bufSize);
		}

		memcpy(buf + offset, &key_len, sizeof(int));
		offset += sizeof(int);
		memcpy(buf + offset, key, key_len);
		offset += key_len;

		/* Content-Length is stored as long, however we should not convert the value itself to a string */
		if(Z_TYPE_PP(value) == IS_LONG) {
			char *strValue;
			int length = spprintf(&strValue, 0, "%ld", Z_LVAL_PP(value));

			if(bufSize < offset + length) {
				bufSize += length + 100;
				buf = erealloc(buf, bufSize);
			}

			memcpy(buf + offset, &length, sizeof(int));
			offset += sizeof(int);
			memcpy(buf + offset, strValue, length);
			offset += length;

			efree(strValue);
		} else {
			if(bufSize < offset + Z_STRLEN_PP(value)) {
				bufSize += Z_STRLEN_PP(value) + 100;
				buf = erealloc(buf, bufSize);
			}

			memcpy(buf + offset, &Z_STRLEN_PP(value), sizeof(int));
			offset += sizeof(int);
			memcpy(buf + offset, Z_STRVAL_PP(value), Z_STRLEN_PP(value));
			offset += Z_STRLEN_PP(value);
		}
	}

	write(sock, &offset, sizeof(int));
	write(sock, buf, offset);

	efree(buf);
}

static int PancakeSAPIClientReconnect(zval *this_ptr TSRMLS_DC) {
	zval *address;
	struct sockaddr_un s_un = {0};
	int sock;

	FAST_READ_PROPERTY(address, this_ptr, "address", sizeof("address") - 1, HASH_OF_address);

	s_un.sun_family = AF_UNIX;
	memcpy(&s_un.sun_path, Z_STRVAL_P(address), Z_STRLEN_P(address));

	sock = socket(AF_UNIX, SOCK_STREAM, 0);

	if(connect(sock, (struct sockaddr*) &s_un, (socklen_t)(XtOffsetOf(struct sockaddr_un, sun_path) + Z_STRLEN_P(address)))) {
		close(sock);
		zend_error(E_WARNING, "%s", strerror(errno));
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Failed to establish connection to PHP SAPI",
			sizeof("Failed to establish connection to PHP SAPI") - 1, 500);
		return -1;
	}

	return sock;
}

PHP_METHOD(SAPIClient, makeRequest) {
	zval **HTTPRequest, *freeSocket, *headers;
	int sock, i, propertiesSize;
	char *properties;
	ssize_t offset = 0, wOffset = 0;
	zend_object *zobj;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "Z", &HTTPRequest) == FAILURE) {
		RETURN_FALSE;
	}

	FAST_READ_PROPERTY(freeSocket, this_ptr, "freeSocket", sizeof("freeSocket") - 1, HASH_OF_freeSocket);

	/* Get socket for transmission */
	if(Z_LVAL_P(freeSocket)) {
		sock = Z_LVAL_P(freeSocket);
		Z_LVAL_P(freeSocket) = 0;
	} else {
		sock = PancakeSAPIClientReconnect(this_ptr TSRMLS_CC);
	}

	zobj = Z_OBJ_P(*HTTPRequest);
	propertiesSize = (PANCAKE_HTTP_REQUEST_LAST_RECV_SCALAR_OFFSET + 1) * (sizeof(zval) - sizeof(zend_uchar)) + 140;
	properties = emalloc(propertiesSize);

	/* Write scalar properties */
	for(i = 0;i <= PANCAKE_HTTP_REQUEST_LAST_RECV_SCALAR_OFFSET;i++) {
		zval *property = zobj->properties_table[i];

		/* We only need the value + its type */
		/* However, since the order is <value> <refcount> <type> we still need to transmit the refcount */
		memcpy(properties + offset, property, sizeof(zval) - sizeof(zend_uchar));
		offset += sizeof(zval) - sizeof(zend_uchar);

		if(i >= PANCAKE_HTTP_REQUEST_FIRST_STRING_OFFSET) {
			/* Transmit string value */
			if(propertiesSize < (offset + Z_STRLEN_P(property) + (sizeof(zval) - sizeof(zend_uchar)))) {
				/* Optimized reallocation behavior */
				propertiesSize += Z_STRLEN_P(property) + 140;
				properties = erealloc(properties, propertiesSize);
			}

			memcpy(properties + offset, Z_STRVAL_P(property), Z_STRLEN_P(property));
			offset += Z_STRLEN_P(property);
		}
	}

	if(write(sock, &offset, sizeof(ssize_t)) == -1) {
		if((sock = PancakeSAPIClientReconnect(this_ptr TSRMLS_CC)) == -1) {
			return;
		}

		write(sock, &offset, sizeof(ssize_t));
	}

	while(wOffset < offset) {
		ssize_t result = write(sock, &properties[wOffset], offset - wOffset);
		if(result == -1) {
			close(sock);
			efree(properties);
			zend_error(E_WARNING, "%s", strerror(errno));

			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Failed to send request to PHP SAPI",
				sizeof("Failed to send request to PHP SAPI") - 1, 500);
			return;
		}

		wOffset += result;
	}

	efree(properties);

	/* Write headers */
	headers = zobj->properties_table[PANCAKE_HTTP_REQUEST_REQUEST_HEADERS_OFFSET];
	PancakeSAPIClientWriteHeaderSet(sock, Z_ARRVAL_P(headers) TSRMLS_CC);
	headers = zobj->properties_table[PANCAKE_HTTP_REQUEST_ANSWER_HEADERS_OFFSET];
	PancakeSAPIClientWriteHeaderSet(sock, Z_ARRVAL_P(headers) TSRMLS_CC);

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
			zval *answerHeaders;
			size_t bufSize, offset = 3 * sizeof(short), readData = 0;
			char *buf;
			unsigned short numHeaders, responseCode, statusLineLength, headersRead = 0;

			read(fd, &bufSize, sizeof(size_t));
			buf = emalloc(bufSize);

			while(readData < bufSize) {
				size_t result = read(fd, buf, bufSize);
				if(result == -1) {
					efree(buf);
					close(fd);
					zend_error(E_WARNING, "%s", strerror(errno));

					PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("PHP SAPI unexpectedly closed network connection",
							sizeof("PHP SAPI unexpectedly closed network connection") - 1, 500);
					return;
				}

				readData += result;
			}

			memcpy(&numHeaders, buf, sizeof(short));
			memcpy(&responseCode, buf + sizeof(short), sizeof(short));
			memcpy(&statusLineLength, buf + 2 * sizeof(short), sizeof(short));

			PancakeQuickWritePropertyString(HTTPRequest, "answerCodeString", sizeof("answerCodeString"), HASH_OF_answerCodeString, buf + 3 * sizeof(short), statusLineLength, 1);
			PancakeQuickWritePropertyLong(HTTPRequest, "answerCode", sizeof("answerCode"), HASH_OF_answerCode, (long) responseCode);

			offset += statusLineLength;

			FAST_READ_PROPERTY(answerHeaders, HTTPRequest, "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);
			zend_hash_clean(Z_ARRVAL_P(answerHeaders));

			while(headersRead < numHeaders) {
				zval *headerValue;
				char *headerName;
				int headerName_len;

				memcpy(&headerName_len, buf + offset, sizeof(int));
				offset += sizeof(int);
				headerName = emalloc(headerName_len);
				memcpy(headerName, buf + offset, headerName_len);
				offset += headerName_len;

				MAKE_STD_ZVAL(headerValue);
				Z_TYPE_P(headerValue) = IS_STRING;

				memcpy(&Z_STRLEN_P(headerValue), buf + offset, sizeof(int));
				offset += sizeof(int);
				Z_STRVAL_P(headerValue) = emalloc(Z_STRLEN_P(headerValue) + 1);
				memcpy(Z_STRVAL_P(headerValue), buf + offset, Z_STRLEN_P(headerValue));
				Z_STRVAL_P(headerValue)[Z_STRLEN_P(headerValue)] = '\0';
				offset += Z_STRLEN_P(headerValue);

				PancakeSetAnswerHeader(answerHeaders, headerName, headerName_len, headerValue, 0, zend_inline_hash_func(headerName, headerName_len) TSRMLS_CC);

				efree(headerName);

				headersRead++;
			}

			efree(buf);

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
