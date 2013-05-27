
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake_HTTPRequest.c                                      	*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "Pancake.h"

PANCAKE_API void PancakeSetAnswerHeader(zval *answerHeaderArray, char *name, uint name_len, zval *value, uint replace, ulong h TSRMLS_DC) {
	zval **answerHeader;

	if(Z_TYPE_P(answerHeaderArray) == IS_OBJECT) {
		FAST_READ_PROPERTY(answerHeaderArray, answerHeaderArray, "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);
	}

	if(replace) {
		zend_hash_quick_update(Z_ARRVAL_P(answerHeaderArray), name, name_len, h, (void*) &value, sizeof(zval*), NULL);
		return;
	}

	if(zend_hash_quick_find(Z_ARRVAL_P(answerHeaderArray), name, name_len, h, (void**) &answerHeader) == SUCCESS) {
		if(Z_TYPE_PP(answerHeader) == IS_STRING) {
			zval *array;

			MAKE_STD_ZVAL(array);
			array_init_size(array, 2);

			Z_ADDREF_PP(answerHeader);

			zend_hash_next_index_insert(Z_ARRVAL_P(array), (void*) answerHeader, sizeof(zval*), NULL);
			zend_hash_next_index_insert(Z_ARRVAL_P(array), (void*) &value, sizeof(zval*), NULL);

			zend_hash_quick_update(Z_ARRVAL_P(answerHeaderArray), name, name_len, h, (void*) &array, sizeof(zval*), NULL);
		} else { // IS_ARRAY
			zend_hash_next_index_insert(Z_ARRVAL_PP(answerHeader), (void*) &value, sizeof(zval*), NULL);
		}
	} else {
		zend_hash_quick_add(Z_ARRVAL_P(answerHeaderArray), name, name_len, h, (void*) &value, sizeof(zval*), NULL);
	}
}

PHP_METHOD(HTTPRequest, setHeader) {
	char *name;
	int name_len;
	long replace = 1;
	zval *value, *nvalue;

	if(UNEXPECTED(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sz|l", &name, &name_len, &value, &replace) == FAILURE)) {
		RETURN_FALSE;
	}

	ALLOC_ZVAL(nvalue);
	INIT_PZVAL_COPY(nvalue, value);
	zval_copy_ctor(nvalue);
	convert_to_string(nvalue);

	php_strtolower(name, name_len);
	name_len++;

	PancakeSetAnswerHeader(this_ptr, name, name_len, nvalue, replace, zend_inline_hash_func(name, name_len) TSRMLS_CC);
}

char *PancakeBuildAnswerHeaders(zval *answerHeaderArray, uint *answerHeader_len) {
	zval **data;
	char *index;
	int index_len;
	char *retval = emalloc(sizeof(char));
	size_t offset = 0;
	size_t retvalSize = 1;

	for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(answerHeaderArray));
		zend_hash_get_current_data(Z_ARRVAL_P(answerHeaderArray), (void**) &data) == SUCCESS,
		zend_hash_get_current_key_ex(Z_ARRVAL_P(answerHeaderArray), &index, &index_len, NULL, 0, NULL) == HASH_KEY_IS_STRING;
		zend_hash_move_forward(Z_ARRVAL_P(answerHeaderArray))) {
		// Format index (x-powered-by => X-Powered-By)
		char *i;
		index_len--;
		index = estrndup(index, index_len);
		*index = toupper(*index);
		for(i = index;*i != '\0';i++) {
			if(*i == '-')
				i[1] = toupper(i[1]);
		}

		if(Z_TYPE_PP(data) == IS_ARRAY) {
			zval **single;

			for(zend_hash_internal_pointer_reset(Z_ARRVAL_PP(data));
				zend_hash_get_current_data(Z_ARRVAL_PP(data), (void**) &single) == SUCCESS;
				zend_hash_move_forward(Z_ARRVAL_PP(data))) {
				size_t elementLength;

				convert_to_string(*single);
				elementLength = Z_STRLEN_PP(single) + index_len + 4;
				retvalSize += elementLength * sizeof(char);
				retval = erealloc(retval, retvalSize);

				//sprintf((char*) (retval + offset), "%s: %s\r\n", index, Z_STRVAL_PP(single));
				memcpy(retval + offset, index, index_len);
				offset += index_len;
				retval[offset] = ':';
				offset++;
				retval[offset] = ' ';
				offset++;
				memcpy(retval + offset, Z_STRVAL_PP(single), Z_STRLEN_PP(single));
				offset += Z_STRLEN_PP(single);
				retval[offset] = '\r';
				offset++;
				retval[offset] = '\n';
				offset++;
			}
		} else {
			size_t elementLength;

			convert_to_string(*data);
			elementLength = Z_STRLEN_PP(data) + index_len + 4;
			retvalSize += elementLength * sizeof(char);
			retval = erealloc(retval, retvalSize);

			//sprintf((char*) (retval + offset), "%s: %s\r\n", index, Z_STRVAL_PP(data));
			memcpy(retval + offset, index, index_len);
			offset += index_len;
			retval[offset] = ':';
			offset++;
			retval[offset] = ' ';
			offset++;
			memcpy(retval + offset, Z_STRVAL_PP(data), Z_STRLEN_PP(data));
			offset += Z_STRLEN_PP(data);
			retval[offset] = '\r';
			offset++;
			retval[offset] = '\n';
			offset++;
		}

		efree(index);
	}

	retval[retvalSize - 1] = '\0';

	*answerHeader_len = retvalSize;
	return retval;
}

PHP_METHOD(HTTPRequest, init) {
	char *requestHeader, *ptr1, *ptr2, *ptr3, *requestHeader_dupe, *requestLine,
		 **firstLine = ecalloc(3, sizeof(char*)), *headerName, *headerValue,
		 *host = NULL, *authorization = NULL, *if_unmodified_since = NULL,
		 *header, *documentRoot, *queryStringStart, *uriptr, *requestFilePath, *queryString,
		 *filePath, *filePath_tmp;
	int requestHeader_len, i, requestLine_len, haveContentLength = 0, contentLength = 0,
		acceptGZIP = 0, host_len, fL1isMalloced = 0, requestFilePath_len, filePath_len,
		authorization_len;
	zval *headerArray, **vHost, **newvHost, *documentRootz, *rewriteRules, *mimeType = NULL, *AJP13,
		*answerHeaderArray;
	struct timeval tp = {0};

	/* Set answer header array to empty array */
	MAKE_STD_ZVAL(answerHeaderArray);
	array_init_size(answerHeaderArray, 8);
	PancakeQuickWriteProperty(this_ptr, answerHeaderArray, "answerHeaders", sizeof("answerHeaders"), HASH_OF_answerHeaders TSRMLS_CC);

	Z_DELREF_P(answerHeaderArray);

	if(UNEXPECTED(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &requestHeader, &requestHeader_len) == FAILURE)) {
		RETURN_FALSE;
	}

	requestHeader_dupe = estrndup(requestHeader, requestHeader_len);
	requestLine = strtok_r(requestHeader_dupe, "\r\n", &ptr1);

	if(EXPECTED(requestLine != NULL)) {
		requestLine_len = strlen(requestLine);
		requestLine = estrndup(requestLine, requestLine_len);
	} else {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Bad request line", sizeof("Bad request line") - 1, 400, requestHeader, requestHeader_len);
		PancakeQuickWriteProperty(this_ptr, PANCAKE_GLOBALS(defaultVirtualHost), "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);
		efree(requestHeader_dupe);
		efree(firstLine);
		return;
	}

	PancakeQuickWritePropertyString(this_ptr, "requestLine", sizeof("requestLine"), HASH_OF_requestLine, requestLine, requestLine_len, 1);

	for(i = 0;i < 3;i++) {
		firstLine[i] = strtok_r(i ? NULL : requestLine, " ", &ptr2);
		if(UNEXPECTED(firstLine[i] == NULL)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Bad request line", sizeof("Bad request line") - 1, 400, requestHeader, requestHeader_len);
			PancakeQuickWriteProperty(this_ptr, PANCAKE_GLOBALS(defaultVirtualHost), "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);
			efree(requestHeader_dupe);
			efree(firstLine);
			efree(requestLine);
			return;
		}
	}

	if(EXPECTED(!strcmp(firstLine[2], "HTTP/1.1"))) {
		PancakeQuickWriteProperty(this_ptr, ZVAL_CACHE(HTTP_1_1), "protocolVersion", sizeof("protocolVersion"), HASH_OF_protocolVersion TSRMLS_CC);
	} else if(UNEXPECTED(strcmp(firstLine[2], "HTTP/1.0"))) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Unsupported protocol", sizeof("Unsupported protocol") - 1, 400, requestHeader, requestHeader_len);
		PancakeQuickWriteProperty(this_ptr, PANCAKE_GLOBALS(defaultVirtualHost), "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);
		efree(requestHeader_dupe);
		efree(firstLine);
		efree(requestLine);
		return;
	}

	if(UNEXPECTED(strcmp(firstLine[0], "GET")
	&& strcmp(firstLine[0], "POST")
	&& strcmp(firstLine[0], "HEAD")
	&& strcmp(firstLine[0], "TRACE")
	&& strcmp(firstLine[0], "OPTIONS"))) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Unknown request method", sizeof("Unknown request method") - 1, 501, requestHeader, requestHeader_len);
		PancakeQuickWriteProperty(this_ptr, PANCAKE_GLOBALS(defaultVirtualHost), "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);
		efree(requestHeader_dupe);
		efree(firstLine);
		efree(requestLine);
		return;
	}

	if(strcmp(firstLine[0], "GET")) {
		// GET is the default value
		PancakeQuickWritePropertyString(this_ptr, "requestType", sizeof("requestType"), HASH_OF_requestType, firstLine[0], strlen(firstLine[0]), 1);
	}

	if(UNEXPECTED((!strcmp(firstLine[0], "HEAD") && PANCAKE_GLOBALS(allowHEAD) == 0)
	|| (!strcmp(firstLine[0], "TRACE") && PANCAKE_GLOBALS(allowTRACE) == 0)
	|| (!strcmp(firstLine[0], "OPTIONS") && PANCAKE_GLOBALS(allowOPTIONS) == 0))) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Disallowed request method", sizeof("Disallowed request method") - 1, 405, requestHeader, requestHeader_len);
		PancakeQuickWriteProperty(this_ptr, PANCAKE_GLOBALS(defaultVirtualHost), "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);
		efree(requestHeader_dupe);
		efree(firstLine);
		efree(requestLine);
		return;
	}

	MAKE_STD_ZVAL(headerArray);
	array_init_size(headerArray, 8);

	while((header = strtok_r(NULL, "\r\n", &ptr1)) != NULL) {
		int headerName_len;
		zval *zHeaderValue;

		headerValue = strchr(header, ':');

		if(UNEXPECTED(headerValue == NULL))
			continue;

		headerName = header;
		headerName_len = headerValue - headerName;
		headerName[headerName_len] = '\0';
		headerValue++;

		php_strtolower(headerName, headerName_len);
		LEFT_TRIM(headerValue);

		if(!strcmp(headerName, "host")) {
			MAKE_STD_ZVAL(zHeaderValue);
			Z_TYPE_P(zHeaderValue) = IS_STRING;

			Z_STRLEN_P(zHeaderValue) = host_len = strlen(headerValue);
			Z_STRVAL_P(zHeaderValue) = estrndup(headerValue, Z_STRLEN_P(zHeaderValue));
			host = estrndup(headerValue, host_len);

			zend_hash_quick_update(Z_ARRVAL_P(headerArray), "host", sizeof("host"), HASH_OF_host, (void*) &zHeaderValue, sizeof(zval*), NULL);
		} else if(!strcmp(headerName, "connection")) {
			if(!strcasecmp(headerValue, "keep-alive")) {
				Z_ADDREF_P(ZVAL_CACHE(KEEP_ALIVE));
				zHeaderValue = ZVAL_CACHE(KEEP_ALIVE);
			} else if(!strcasecmp(headerName, "close")) {
				Z_ADDREF_P(ZVAL_CACHE(CLOSE));
				zHeaderValue = ZVAL_CACHE(CLOSE);
			} else {
				MAKE_STD_ZVAL(zHeaderValue);
				Z_TYPE_P(zHeaderValue) = IS_STRING;

				Z_STRLEN_P(zHeaderValue) = strlen(headerValue);
				Z_STRVAL_P(zHeaderValue) = estrndup(headerValue, Z_STRLEN_P(zHeaderValue));
			}

			zend_hash_quick_update(Z_ARRVAL_P(headerArray), "connection", sizeof("connection"), HASH_OF_connection, (void*) &zHeaderValue, sizeof(zval*), NULL);
		} else if(!strcmp(headerName, "accept-encoding")) {
			char *ptr4;
			char *acceptedCompression;
			zval *acceptedCompressions;

			MAKE_STD_ZVAL(zHeaderValue);
			Z_TYPE_P(zHeaderValue) = IS_STRING;

			Z_STRLEN_P(zHeaderValue) = strlen(headerValue);
			Z_STRVAL_P(zHeaderValue) = estrndup(headerValue, Z_STRLEN_P(zHeaderValue));

			zend_hash_quick_update(Z_ARRVAL_P(headerArray), "accept-encoding", sizeof("accept-encoding"), HASH_OF_accept_encoding, (void*) &zHeaderValue, sizeof(zval*), NULL);

			headerValue = estrndup(headerValue, Z_STRLEN_P(zHeaderValue));
			acceptedCompression = strtok_r(headerValue, ",", &ptr4);

			MAKE_STD_ZVAL(acceptedCompressions);
			array_init_size(acceptedCompressions, 3);

			while(acceptedCompression != NULL) {
				int acceptedCompression_len;
				LEFT_TRIM(acceptedCompression);

				acceptedCompression_len = strlen(acceptedCompression);
				php_strtolower(acceptedCompression, acceptedCompression_len);

				if(!strcmp(acceptedCompression, "gzip")) {
					acceptGZIP = 1;
					Z_ADDREF_P(ZVAL_CACHE(GZIP));
					zend_hash_quick_add(Z_ARRVAL_P(acceptedCompressions), "gzip", 5, HASH_OF_gzip, (void*) &ZVAL_CACHE(GZIP), sizeof(zval*), NULL);
				} else {
					add_assoc_stringl_ex(acceptedCompressions, acceptedCompression, acceptedCompression_len + 1, acceptedCompression, acceptedCompression_len, 1);
				}

				acceptedCompression = strtok_r(NULL, ",", &ptr4);
			}

			PancakeQuickWriteProperty(this_ptr, acceptedCompressions, "acceptedCompressions", sizeof("acceptedCompressions"), HASH_OF_acceptedCompressions TSRMLS_CC);
			Z_DELREF_P(acceptedCompressions);
			efree(headerValue);
		} else if(!strcmp(headerName, "authorization")) {
			MAKE_STD_ZVAL(zHeaderValue);
			Z_TYPE_P(zHeaderValue) = IS_STRING;

			Z_STRLEN_P(zHeaderValue) = authorization_len = strlen(headerValue);
			Z_STRVAL_P(zHeaderValue) = authorization = estrndup(headerValue, authorization_len);

			zend_hash_quick_update(Z_ARRVAL_P(headerArray), "authorization", sizeof("authorization"), HASH_OF_authorization, (void*) &zHeaderValue, sizeof(zval*), NULL);
		} else if(!strcmp(headerName, "if-unmodified-since")) {
			MAKE_STD_ZVAL(zHeaderValue);
			Z_TYPE_P(zHeaderValue) = IS_STRING;

			Z_STRLEN_P(zHeaderValue) = strlen(headerValue);
			Z_STRVAL_P(zHeaderValue) = if_unmodified_since = estrndup(headerValue, Z_STRLEN_P(zHeaderValue));

			zend_hash_quick_update(Z_ARRVAL_P(headerArray), "if-unmodified-since", sizeof("if-unmodified-since"), HASH_OF_if_unmodified_since, (void*) &zHeaderValue, sizeof(zval*), NULL);
		} else if(!strcmp(headerName, "range")) {
			char *to = strchr(headerValue, '-');

			MAKE_STD_ZVAL(zHeaderValue);
			Z_TYPE_P(zHeaderValue) = IS_STRING;

			Z_STRLEN_P(zHeaderValue) = strlen(headerValue);
			Z_STRVAL_P(zHeaderValue) = estrndup(headerValue, Z_STRLEN_P(zHeaderValue));

			zend_hash_quick_update(Z_ARRVAL_P(headerArray), "range", sizeof("range"), HASH_OF_range, (void*) &zHeaderValue, sizeof(zval*), NULL);

			if(EXPECTED(to != NULL && !strncmp(headerValue, "bytes=", 6))) {
				zval *rangeFrom, *rangeTo;
				*to = '\0';
				to++;

				headerValue = estrndup(headerValue + 6, Z_STRLEN_P(zHeaderValue) - 6);

				MAKE_STD_ZVAL(rangeFrom);
				Z_TYPE_P(rangeFrom) = IS_LONG;
				Z_LVAL_P(rangeFrom) = atol(headerValue);

				MAKE_STD_ZVAL(rangeTo);
				Z_TYPE_P(rangeTo) = IS_LONG;
				Z_LVAL_P(rangeTo) = atol(to);

				PancakeQuickWriteProperty(this_ptr, rangeFrom, "rangeFrom", sizeof("rangeFrom"), HASH_OF_rangeFrom TSRMLS_CC);
				PancakeQuickWriteProperty(this_ptr, rangeTo, "rangeTo", sizeof("rangeTo"), HASH_OF_rangeTo TSRMLS_CC);

				Z_DELREF_P(rangeFrom);
				Z_DELREF_P(rangeTo);

				efree(headerValue);
			}
		} else if(!strcmp(headerName, "content-length")) {
			haveContentLength = 1;

			MAKE_STD_ZVAL(zHeaderValue);
			Z_TYPE_P(zHeaderValue) = IS_LONG;
			Z_LVAL_P(zHeaderValue) = contentLength = atol(headerValue);

			zend_hash_quick_update(Z_ARRVAL_P(headerArray), "content-length", sizeof("content-length"), HASH_OF_content_length, (void*) &zHeaderValue, sizeof(zval*), NULL);
		} else {
			MAKE_STD_ZVAL(zHeaderValue);
			Z_TYPE_P(zHeaderValue) = IS_STRING;
			Z_STRLEN_P(zHeaderValue) = strlen(headerValue);
			Z_STRVAL_P(zHeaderValue) = estrndup(headerValue, Z_STRLEN_P(zHeaderValue));

			zend_hash_update(Z_ARRVAL_P(headerArray), headerName, headerName_len + 1, (void*) &zHeaderValue, sizeof(zval*), NULL);
		}
	}

	PancakeQuickWriteProperty(this_ptr, headerArray, "requestHeaders", sizeof("requestHeaders"), HASH_OF_requestHeaders TSRMLS_CC);
	Z_DELREF_P(headerArray);

	efree(requestHeader_dupe);

	if(UNEXPECTED(host == NULL)) {
		PancakeQuickWriteProperty(this_ptr, PANCAKE_GLOBALS(defaultVirtualHost), "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);

		if(UNEXPECTED(!strcmp(firstLine[2], "HTTP/1.1"))) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Missing required header: Host",
					sizeof("Missing required header: Host") - 1, 400, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(requestLine);
			return;
		} else {
			zval *listen, **hostZval;

			FAST_READ_PROPERTY(listen, PANCAKE_GLOBALS(defaultVirtualHost), "listen", sizeof("listen") - 1, HASH_OF_listen);

			vHost = &PANCAKE_GLOBALS(defaultVirtualHost);
			zend_hash_index_find(Z_ARRVAL_P(listen), 0, (void**) &hostZval);
			host = estrndup(Z_STRVAL_PP(hostZval), Z_STRLEN_PP(hostZval));
			host_len = Z_STRLEN_PP(hostZval);
		}
	} else if(zend_hash_find(Z_ARRVAL_P(PANCAKE_GLOBALS(virtualHostArray)), host, host_len + 1, (void**) &newvHost) == SUCCESS) {
		vHost = newvHost;
		PancakeQuickWriteProperty(this_ptr, *vHost, "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);
	} else {
		PancakeQuickWriteProperty(this_ptr, PANCAKE_GLOBALS(defaultVirtualHost), "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);
		vHost = &PANCAKE_GLOBALS(defaultVirtualHost);
	}

	if(!strcmp(firstLine[0], "POST")) {
		if(UNEXPECTED(!haveContentLength)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Your request can't be processed without a given Content-Length",
					sizeof("Your request can't be processed without a given Content-Length") - 1, 411, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(host);
			efree(requestLine);
			return;
		}

		if(PANCAKE_GLOBALS(post_max_size) > 0 && contentLength > PANCAKE_GLOBALS(post_max_size)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("The uploaded content is too large.",
					sizeof("The uploaded content is too large.") - 1, 413, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(host);
			efree(requestLine);
			return;
		}
	}

	/* Enough information for TRACE gathered */
	if(UNEXPECTED(!strcmp(firstLine[0], "TRACE"))) {
		zval *contentTypeZval;

		MAKE_STD_ZVAL(contentTypeZval);
		Z_TYPE_P(contentTypeZval) = IS_STRING;
		Z_STRVAL_P(contentTypeZval) = estrndup("message/http", sizeof("message/http") - 1);
		Z_STRLEN_P(contentTypeZval) = sizeof("message/http") - 1;

		PancakeQuickWritePropertyString(this_ptr, "answerBody", sizeof("answerBody"), HASH_OF_answerBody, requestHeader, requestHeader_len, 1);
		PancakeSetAnswerHeader(answerHeaderArray, "content-type", sizeof("content-type"), contentTypeZval, 1, HASH_OF_content_type TSRMLS_CC);
		efree(firstLine);
		efree(host);
		efree(requestLine);
		return;
	}

	FAST_READ_PROPERTY(documentRootz, *vHost, "documentRoot", sizeof("documentRoot") - 1, HASH_OF_documentRoot);
	documentRoot = Z_STRVAL_P(documentRootz);

	PancakeQuickWritePropertyString(this_ptr, "originalRequestURI", sizeof("originalRequestURI"), HASH_OF_originalRequestURI, firstLine[1], strlen(firstLine[1]), 1);

	/* Apply rewrite rules */
	FAST_READ_PROPERTY(rewriteRules, *vHost, "rewriteRules", sizeof("rewriteRules") - 1, HASH_OF_rewriteRules);

	if(queryStringStart = strchr(firstLine[1], '?')) {
		php_url_decode(firstLine[1], queryStringStart - firstLine[1]);
		firstLine[1][queryStringStart - firstLine[1]] = '?';
	} else {
		php_url_decode(firstLine[1], strlen(firstLine[1]));
	}

	if(Z_TYPE_P(rewriteRules) == IS_ARRAY) {
		zval **rewriteRule;
		char *path = NULL;

		for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(rewriteRules));
			zend_hash_get_current_data(Z_ARRVAL_P(rewriteRules), (void**) &rewriteRule) == SUCCESS;
			zend_hash_move_forward(Z_ARRVAL_P(rewriteRules))) {
			zval **value, **value2;
			char *matchPath;
			int matchPath_len;

			if(path != NULL) {
				efree(path);
			}

			if(zend_hash_quick_exists(Z_ARRVAL_PP(rewriteRule), "useclientpath", sizeof("useclientpath"), HASH_OF_useclientpath)) {
				zval *clientPath;
				FAST_READ_PROPERTY(clientPath, this_ptr, "originalRequestURI", sizeof("originalRequestURI") - 1, HASH_OF_originalRequestURI);

				matchPath = Z_STRVAL_P(clientPath);
				matchPath_len = Z_STRLEN_P(clientPath);
			} else {
				matchPath = firstLine[1];
				matchPath_len = strlen(firstLine[1]);
			}

			spprintf(&path, (((queryStringStart = strchr(matchPath, '?')) != NULL)
									? strlen(documentRoot) + (queryStringStart - matchPath)
									: 0), "%s%s", documentRoot, matchPath);

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "if", sizeof("if"), HASH_OF_if, (void**) &value) == SUCCESS) {
				pcre_cache_entry *pcre;
				zval *pcre_retval;

				if(UNEXPECTED((pcre = pcre_get_compiled_regex_cache(Z_STRVAL_PP(value), Z_STRLEN_PP(value) TSRMLS_CC)) == NULL)) {
					continue;
				}

				MAKE_STD_ZVAL(pcre_retval);

				php_pcre_match_impl(pcre, matchPath, matchPath_len,  pcre_retval, NULL, 0, 0, 0, 0 TSRMLS_CC);

				if(Z_LVAL_P(pcre_retval) == 0) {
					zval_ptr_dtor(&pcre_retval);
					continue;
				}

				zval_ptr_dtor(&pcre_retval);
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "ifnot", sizeof("ifnot"), HASH_OF_ifnot, (void**) &value) == SUCCESS) {
				pcre_cache_entry *pcre;
				zval *pcre_retval;

				if(UNEXPECTED((pcre = pcre_get_compiled_regex_cache(Z_STRVAL_PP(value), Z_STRLEN_PP(value) TSRMLS_CC)) == NULL)) {
					continue;
				}

				MAKE_STD_ZVAL(pcre_retval);

				php_pcre_match_impl(pcre, matchPath, matchPath_len,  pcre_retval, NULL, 0, 0, 0, 0 TSRMLS_CC);

				if(Z_LVAL_P(pcre_retval)) {
					zval_ptr_dtor(&pcre_retval);
					continue;
				}

				zval_ptr_dtor(&pcre_retval);
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "tls", sizeof("tls"), HASH_OF_tls, (void**) &value) == SUCCESS) {
				zval *TLS;
				FAST_READ_PROPERTY(TLS, this_ptr, "TLS", sizeof("TLS") - 1, HASH_OF_TLS);

				if((zend_is_true(*value) && !Z_LVAL_P(TLS))
				|| (!zend_is_true(*value) && Z_LVAL_P(TLS))) {
					continue;
				}
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "httpmethod", sizeof("httpmethod"), HASH_OF_httpmethod, (void**) &value) == SUCCESS) {
				if(Z_TYPE_PP(value) == IS_STRING && strcmp(Z_STRVAL_PP(value), firstLine[0])) {
					continue;
				} else if(Z_TYPE_PP(value) == IS_ARRAY) {
					zval **method;

					PANCAKE_FOREACH(Z_ARRVAL_PP(value), method) {
						if(!strcmp(Z_STRVAL_PP(method), firstLine[0])) {
							goto MethodAccepted;
						}
					}

					continue;
				}
			}

			MethodAccepted:;

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "mimetype", sizeof("mimetype"), HASH_OF_mimetype, (void**) &value) == SUCCESS) {
				zval *MIME = PancakeMIMEType(path, strlen(path));

				if(Z_TYPE_PP(value) == IS_STRING && strcmp(Z_STRVAL_PP(value), Z_STRVAL_P(MIME))) {
					continue;
				} else if(Z_TYPE_PP(value) == IS_ARRAY) {
					zval **type;

					PANCAKE_FOREACH(Z_ARRVAL_PP(value), type) {
						if(!strcmp(Z_STRVAL_PP(type), Z_STRVAL_P(MIME))) {
							goto MIMETypeAccepted;
						}
					}

					continue;
				}
			}

			MIMETypeAccepted:;

			if(		(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "location", sizeof("location"), HASH_OF_location, (void**) &value) == SUCCESS
					&& strncmp(Z_STRVAL_PP(value), matchPath, Z_STRLEN_PP(value)) != 0)
			||		(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "precondition", sizeof("precondition"), HASH_OF_precondition, (void**) &value) == SUCCESS
					&& (	Z_TYPE_PP(value) != IS_LONG
						||	(	Z_LVAL_PP(value) == 404
							&&	virtual_access(path, F_OK TSRMLS_CC) == 0)
						||	(	Z_LVAL_PP(value) == 403
							&&	(	virtual_access(path, F_OK TSRMLS_CC) == -1
								||	virtual_access(path, R_OK TSRMLS_CC) == 0))))) {
				continue;
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "pattern", sizeof("pattern"), HASH_OF_pattern, (void**) &value) == SUCCESS
			&& zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "replace", sizeof("replace"), HASH_OF_replace, (void**) &value2) == SUCCESS) {
				char *result = NULL;
				int result_len = 0, replace_count = 0;

				result = php_pcre_replace(Z_STRVAL_PP(value), Z_STRLEN_PP(value), matchPath, matchPath_len, *value2, 0, &result_len, -1, &replace_count TSRMLS_CC);

				if(result_len > 0 && replace_count > 0) {
					if(fL1isMalloced) efree(firstLine[1]);
					fL1isMalloced = 1;
					firstLine[1] = result;
				} else {
					efree(result);
				}
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "headers", sizeof("headers"), HASH_OF_headers, (void**) &value) == SUCCESS
			&& EXPECTED(Z_TYPE_PP(value) == IS_ARRAY)) {
				zval **headerValue;
				char *headerName;
				int headerName_len;

				for(zend_hash_internal_pointer_reset(Z_ARRVAL_PP(value));
							zend_hash_get_current_data(Z_ARRVAL_PP(value), (void**) &headerValue) == SUCCESS,
							zend_hash_get_current_key_ex(Z_ARRVAL_PP(value), &headerName, &headerName_len, NULL, 0, NULL) == HASH_KEY_IS_STRING;
							zend_hash_move_forward(Z_ARRVAL_PP(value))) {
					Z_ADDREF_PP(headerValue);
					PancakeSetAnswerHeader(answerHeaderArray, headerName, headerName_len, *headerValue, 1, Z_ARRVAL_PP(value)->pInternalPointer->h TSRMLS_CC);
				}
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "exception", sizeof("exception"), HASH_OF_exception, (void**) &value) == SUCCESS
			&& EXPECTED(Z_TYPE_PP(value) == IS_LONG)) {
				if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "exceptionmessage", sizeof("exceptionmessage"), HASH_OF_exceptionmessage, (void**) &value2) == SUCCESS) {
					PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL(Z_STRVAL_PP(value2), Z_STRLEN_PP(value2), Z_LVAL_PP(value), requestHeader, requestHeader_len);
				} else {
					PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("The server was unable to process your request", sizeof("The server was unable to process your request") - 1, Z_LVAL_PP(value), requestHeader, requestHeader_len);
				}

				if(fL1isMalloced) efree(firstLine[1]);
				efree(firstLine);
				efree(host);
				efree(requestLine);
				efree(path);
				return;
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "destination", sizeof("destination"), HASH_OF_destination, (void**) &value) == SUCCESS) {
				Z_ADDREF_PP(value);
				PancakeSetAnswerHeader(answerHeaderArray, "location", sizeof("location"), *value, 1, HASH_OF_location TSRMLS_CC);
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Redirecting...", sizeof("Redirecting...") - 1, 301);
				if(fL1isMalloced) efree(firstLine[1]);
				efree(firstLine);
				efree(host);
				efree(requestLine);
				efree(path);
				return;
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "pathinfo", sizeof("pathinfo"), HASH_OF_pathinfo, (void**) &value) == SUCCESS) {
				pcre_cache_entry *pcre;
				zval *pcre_retval, *matches, **match;

				if(UNEXPECTED((pcre = pcre_get_compiled_regex_cache(Z_STRVAL_PP(value), Z_STRLEN_PP(value) TSRMLS_CC)) == NULL)) {
					continue;
				}

				MAKE_STD_ZVAL(pcre_retval);
				MAKE_STD_ZVAL(matches);
				Z_TYPE_P(matches) = IS_NULL;

				php_pcre_match_impl(pcre, matchPath, matchPath_len,  pcre_retval, matches, 0, 0, 0, 0 TSRMLS_CC);

				if(EXPECTED(zend_hash_index_find(Z_ARRVAL_P(matches), 2, (void**) &match) == SUCCESS)) {
					PancakeQuickWriteProperty(this_ptr, *match, "pathInfo", sizeof("pathInfo"), HASH_OF_pathInfo TSRMLS_CC);
				}

				if(EXPECTED(zend_hash_index_find(Z_ARRVAL_P(matches), 1, (void**) &match) == SUCCESS)) {
					if(fL1isMalloced) { efree(firstLine[1]); }
					else { fL1isMalloced = 1; }

					firstLine[1] = estrndup(Z_STRVAL_PP(match), Z_STRLEN_PP(match));
				}

				zval_ptr_dtor(&matches);
				zval_ptr_dtor(&pcre_retval);
			}
		}

		if(path != NULL) {
			efree(path);
		}
	}

	PancakeQuickWritePropertyString(this_ptr, "requestURI", sizeof("requestURI"), HASH_OF_requestURI, firstLine[1], strlen(firstLine[1]), 1);

	requestFilePath = strtok_r(firstLine[1], "?", &uriptr);
	queryString = strtok_r(NULL, "?", &uriptr);
	if(queryString != NULL) {
		queryString = estrdup(queryString);
	} else {
		queryString = emalloc(1);
		*queryString = '\0';
	}

	if(UNEXPECTED(!strncasecmp("http://", requestFilePath, 7))) {
		requestFilePath = &requestFilePath[7];
		requestFilePath =  strchr(requestFilePath, '/');
	}

	requestFilePath_len = strlen(requestFilePath);
	requestFilePath = estrndup(requestFilePath, requestFilePath_len);

	if(fL1isMalloced) efree(firstLine[1]);

	if(UNEXPECTED(requestFilePath[0] != '/')) {
		requestFilePath_len++;
		requestFilePath = erealloc(requestFilePath, requestFilePath_len + 1);
		memmove(requestFilePath + 1, requestFilePath, requestFilePath_len);
		requestFilePath[0] = '/';
	}

	if(UNEXPECTED(strstr(requestFilePath, "../") != NULL)) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("You are not allowed to access the requested file",
				sizeof("You are not allowed to access the requested file") - 1, 403, requestHeader, requestHeader_len);
		efree(host);
		efree(firstLine);
		efree(requestLine);
		efree(requestFilePath);
		return;
	}

	FAST_READ_PROPERTY(AJP13, *vHost, "AJP13", 5, HASH_OF_AJP13);

	if(EXPECTED(Z_TYPE_P(AJP13) != IS_OBJECT)) {
		struct stat st;

		filePath = emalloc(Z_STRLEN_P(documentRootz) + requestFilePath_len + 1);
		memcpy(filePath, documentRoot, Z_STRLEN_P(documentRootz));
		memcpy(filePath + Z_STRLEN_P(documentRootz), requestFilePath, requestFilePath_len + 1);

		if(!stat(filePath, &st) && S_ISDIR(st.st_mode)) {
			zval *indexFiles, *allowDirectoryListings;

			if(requestFilePath[requestFilePath_len - 1] !=  '/') {
				zval *redirectValue;

				MAKE_STD_ZVAL(redirectValue);
				Z_TYPE_P(redirectValue) = IS_STRING;
				Z_STRLEN_P(redirectValue) = spprintf(&Z_STRVAL_P(redirectValue), 0, "http://%s%s/?%s", host, requestFilePath, queryString ? queryString : "");

				PancakeSetAnswerHeader(answerHeaderArray, "location", sizeof("location"), redirectValue, 1, HASH_OF_location TSRMLS_CC);
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Redirecting...", sizeof("Redirecting...") - 1, 301);
				efree(filePath);
				efree(host);
				efree(firstLine);
				efree(requestLine);
				efree(requestFilePath);
				efree(queryString);
				return;
			}

			FAST_READ_PROPERTY(indexFiles, *vHost, "indexFiles", sizeof("indexFiles") - 1, HASH_OF_indexFiles);

			if(EXPECTED(Z_TYPE_P(indexFiles) == IS_ARRAY)) {
				zval **indexFile;

				for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(indexFiles));
					zend_hash_get_current_data(Z_ARRVAL_P(indexFiles), (void**) &indexFile) == SUCCESS;
					zend_hash_move_forward(Z_ARRVAL_P(indexFiles))) {
					if(UNEXPECTED(Z_TYPE_PP(indexFile) != IS_STRING))
						continue;

					filePath_tmp = emalloc(Z_STRLEN_P(documentRootz) + requestFilePath_len + Z_STRLEN_PP(indexFile) + 1);
					memcpy(filePath_tmp, documentRoot, Z_STRLEN_P(documentRootz));
					memcpy(filePath_tmp + Z_STRLEN_P(documentRootz), requestFilePath, requestFilePath_len);
					memcpy(filePath_tmp + Z_STRLEN_P(documentRootz) + requestFilePath_len, Z_STRVAL_PP(indexFile), Z_STRLEN_PP(indexFile) + 1);

					if(!virtual_access(filePath_tmp, F_OK | R_OK TSRMLS_CC)) {
						requestFilePath = erealloc(requestFilePath, (requestFilePath_len + Z_STRLEN_PP(indexFile) + 1) * sizeof(char));
						memcpy(requestFilePath + requestFilePath_len, Z_STRVAL_PP(indexFile), Z_STRLEN_PP(indexFile) + 1);
						requestFilePath_len += Z_STRLEN_PP(indexFile);

						efree(filePath);
						filePath = filePath_tmp;

						stat(filePath, &st);
						goto checkRead;
					}

					efree(filePath_tmp);
				}
			}

			FAST_READ_PROPERTY(allowDirectoryListings, *vHost, "allowDirectoryListings", sizeof("allowDirectoryListings") - 1, HASH_OF_allowDirectoryListings);

			if(Z_TYPE_P(allowDirectoryListings) > IS_BOOL || Z_LVAL_P(allowDirectoryListings) == 0) {
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("You're not allowed to view the listing of the requested directory",
						sizeof("You're not allowed to view the listing of the requested directory") - 1, 403, requestHeader, requestHeader_len);
				efree(filePath);
				efree(firstLine);
				efree(requestLine);
				efree(requestFilePath);
				efree(queryString);
				efree(host);
				return;
			}
		// end is_dir
		}

		if(acceptGZIP == 1) {
			zval *allowGZIPStatic;
			FAST_READ_PROPERTY(allowGZIPStatic, *vHost, "gzipStatic", sizeof("gzipStatic") - 1, HASH_OF_gzipStatic);

			if(Z_TYPE_P(allowGZIPStatic) <= IS_BOOL && Z_LVAL_P(allowGZIPStatic) > 0) {
				filePath_tmp = emalloc(Z_STRLEN_P(documentRootz) + requestFilePath_len + 4);
				memcpy(filePath_tmp, documentRoot, Z_STRLEN_P(documentRootz));
				memcpy(filePath_tmp + Z_STRLEN_P(documentRootz), requestFilePath, requestFilePath_len);
				memcpy(filePath_tmp + Z_STRLEN_P(documentRootz) + requestFilePath_len, ".gz", 4);

				if(!virtual_access(filePath_tmp, F_OK | R_OK TSRMLS_CC)) {
					zval *gzipStr;

					mimeType = PancakeMIMEType(requestFilePath, requestFilePath_len TSRMLS_CC);
					requestFilePath = erealloc(requestFilePath, requestFilePath_len + 4);
					memcpy(requestFilePath + requestFilePath_len, ".gz", 4);
					requestFilePath_len += 3;

					MAKE_STD_ZVAL(gzipStr);
					Z_TYPE_P(gzipStr) = IS_STRING;
					Z_STRVAL_P(gzipStr) = estrndup("gzip", 4);
					Z_STRLEN_P(gzipStr) = 4;

					PancakeSetAnswerHeader(answerHeaderArray, "content-encoding", sizeof("content-encoding"), gzipStr, 1, HASH_OF_content_encoding TSRMLS_CC);

					efree(filePath);
					filePath = filePath_tmp;
					stat(filePath, &st);
				} else {
					efree(filePath_tmp);
				}
			}
		}

		checkRead:

		efree(host);

		if(UNEXPECTED(virtual_access(filePath, F_OK TSRMLS_CC))) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("File does not exist",
					sizeof("File does not exist") - 1, 404, requestHeader, requestHeader_len);
			efree(filePath);
			efree(firstLine);
			efree(requestLine);
			efree(requestFilePath);
			efree(queryString);
			return;
		}

		if(UNEXPECTED(virtual_access(filePath, R_OK TSRMLS_CC))) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("You're not allowed to access the requested file",
					sizeof("You're not allowed to access the requested file") - 1, 403, requestHeader, requestHeader_len);
			efree(filePath);
			efree(firstLine);
			efree(requestLine);
			efree(requestFilePath);
			efree(queryString);
			return;
		}

		efree(filePath);

		if(!S_ISREG(st.st_mode) && !S_ISDIR(st.st_mode) && !S_ISLNK(st.st_mode)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("File is not a regular file or a directory.",
									sizeof("File is not a regular file or a directory.") - 1, 404, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(requestLine);
			efree(requestFilePath);
			efree(queryString);
			return;
		}

		if(if_unmodified_since != NULL && st.st_mtime != php_parse_date(if_unmodified_since, NULL)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("File was modified since requested time.",
					sizeof("File was modified since requested time.") - 1, 412, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(requestLine);
			efree(requestFilePath);
			efree(queryString);
			return;
		}
	} else {
		efree(host);
	}

	if(PANCAKE_GLOBALS(enableAuthentication)) {
		zval *callArray, authData, *arg;

		MAKE_STD_ZVAL(callArray);
		array_init_size(callArray, 2);
		Z_ADDREF_PP(vHost);
		add_next_index_zval(callArray, *vHost);
		add_next_index_stringl(callArray, "requiresAuthentication", sizeof("requiresAuthentication") - 1, 1);

		MAKE_STD_ZVAL(arg);
		Z_TYPE_P(arg) = IS_STRING;
		Z_STRLEN_P(arg) = requestFilePath_len;
		Z_STRVAL_P(arg) = estrndup(requestFilePath, requestFilePath_len);

		if(UNEXPECTED(call_user_function(CG(function_table), NULL, callArray, &authData, 1, &arg TSRMLS_CC) == FAILURE)) {
			zval_ptr_dtor(&callArray);
			zval_ptr_dtor(&arg);
			efree(firstLine);
			efree(requestLine);
			efree(requestFilePath);
			efree(queryString);

			// Let's throw a 500 for safety
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("An internal server error occured while trying to handle your request",
					sizeof("An internal server error occured while trying to handle your request") - 1, 500, requestHeader, requestHeader_len);
			return;
		}

		zval_ptr_dtor(&callArray);
		zval_ptr_dtor(&arg);

		if(Z_TYPE(authData) == IS_ARRAY) {
			zval **realm, *authenticate;

			if(authorization != NULL) {
				char *ptr1, *authorizationBase64;

				authorization = estrndup(authorization, authorization_len);
				strtok_r(authorization, " ", &ptr1);
				authorizationBase64 = strtok_r(NULL, " ", &ptr1);

				if(EXPECTED(authorizationBase64 != NULL)) {
					char *ptr2;
					int ret_len;
					unsigned char *decoded = php_base64_decode_ex((unsigned char*) authorizationBase64, strlen(authorizationBase64), &ret_len, 0);
					char **userPassword = ecalloc(2, sizeof(char*));

					userPassword[0] = strtok_r((char*) decoded, ":", &ptr2);
					userPassword[1] = strtok_r(NULL, ":", &ptr2);

					if(EXPECTED(userPassword[0] != NULL && userPassword[1] != NULL)) {
						zval *arg2, *arg3, *args[3], retval;

						MAKE_STD_ZVAL(callArray);
						Z_TYPE_P(callArray) = IS_STRING;
						Z_STRLEN_P(callArray) = sizeof("isValidAuthentication") - 1;
						Z_STRVAL_P(callArray) = estrndup("isValidAuthentication", sizeof("isValidAuthentication") - 1);

						MAKE_STD_ZVAL(arg);
						Z_TYPE_P(arg) = IS_STRING;
						Z_STRLEN_P(arg) = requestFilePath_len;
						Z_STRVAL_P(arg)= estrndup(requestFilePath, requestFilePath_len);

						MAKE_STD_ZVAL(arg2);
						Z_TYPE_P(arg2) = IS_STRING;
						Z_STRLEN_P(arg2) = strlen(userPassword[0]);
						Z_STRVAL_P(arg2) = estrndup(userPassword[0], Z_STRLEN_P(arg2));

						MAKE_STD_ZVAL(arg3);
						Z_TYPE_P(arg3)= IS_STRING;
						Z_STRLEN_P(arg3) = strlen(userPassword[1]);
						Z_STRVAL_P(arg3) = estrndup(userPassword[1], Z_STRLEN_P(arg3));

						efree(userPassword);
						efree(decoded);

						args[0] = arg;
						args[1] = arg2;
						args[2] = arg3;

						if(UNEXPECTED(call_user_function(CG(function_table), vHost, callArray, &retval, 3, args TSRMLS_CC) == FAILURE)) {
							zval_ptr_dtor(&callArray);
							zval_ptr_dtor(&arg);
							zval_ptr_dtor(&arg2);
							zval_ptr_dtor(&arg3);
							zval_dtor(&authData);
							efree(firstLine);
							efree(requestLine);
							efree(requestFilePath);
							efree(queryString);
							efree(authorization);

							// Let's throw a 500 for safety
							PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("An internal server error occured while trying to handle your request",
									sizeof("An internal server error occured while trying to handle your request") - 1, 500, requestHeader, requestHeader_len);
							return;
						}

						zval_ptr_dtor(&arg);
						zval_ptr_dtor(&arg2);
						zval_ptr_dtor(&arg3);
						zval_ptr_dtor(&callArray);

						if(Z_LVAL(retval) == 1) { // Authentication suceeded
							efree(authorization);
							zval_dtor(&authData);
							zval_dtor(&retval);
							goto end;
						}

						zval_dtor(&retval);
					} else {
						efree(userPassword);
						efree(decoded);
					}
				}

				efree(authorization);
			}

			zend_hash_quick_find(Z_ARRVAL(authData), "realm", sizeof("realm"), 6953973961110U, (void**) &realm);

			MAKE_STD_ZVAL(authenticate);
			Z_TYPE_P(authenticate) = IS_STRING;
			Z_STRLEN_P(authenticate) = sizeof("Basic realm=\"\"") - 1 + Z_STRLEN_PP(realm);
			Z_STRVAL_P(authenticate) = estrndup("Basic realm=\"", Z_STRLEN_P(authenticate));
			strcat(Z_STRVAL_P(authenticate), Z_STRVAL_PP(realm));
			strcat(Z_STRVAL_P(authenticate), "\"");

			PancakeSetAnswerHeader(answerHeaderArray, "www-authenticate", sizeof("www-authenticate"), authenticate, 1, 10801095474844103286U TSRMLS_CC);
			efree(firstLine);
			efree(requestLine);
			efree(requestFilePath);
			efree(queryString);
			zval_dtor(&authData);
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("You need to authorize in order to access this file.",
					sizeof("You need to authorize in order to access this file.") - 1, 401, requestHeader, requestHeader_len);
			return;
		}

		zval_dtor(&authData);
	}

	end:;

	if(mimeType == NULL) {
		mimeType = PancakeMIMEType(requestFilePath, requestFilePath_len TSRMLS_CC);
	}

	PancakeQuickWritePropertyString(this_ptr, "queryString", sizeof("queryString"), HASH_OF_queryString, queryString, strlen(queryString), 1);
	PancakeQuickWritePropertyString(this_ptr, "requestFilePath", sizeof("requestFilePath"), HASH_OF_requestFilePath, requestFilePath, requestFilePath_len, 1);
	PancakeQuickWriteProperty(this_ptr, mimeType, "mimeType", sizeof("mimeType"), HASH_OF_mimeType TSRMLS_CC);
	PancakeQuickWritePropertyLong(this_ptr, "requestTime", sizeof("requestTime"), HASH_OF_requestTime, time(NULL));

	efree(firstLine);
	efree(requestLine);
	efree(requestFilePath);
	efree(queryString);

	gettimeofday(&tp, NULL);

	PancakeQuickWritePropertyDouble(this_ptr, "requestMicrotime", sizeof("requestMicrotime"), HASH_OF_requestMicrotime, (double) (tp.tv_sec + tp.tv_usec / 1000000.00));
}

PHP_METHOD(HTTPRequest, buildAnswerHeaders) {
	zval *vHost, *answerHeaderArray, *answerCodez, *answerBodyz, *protocolVersion, **contentLength, *requestHeaderArray, *connectionAnswer, **connection, *requestType,
		*contentLengthM, *writeBuffer, *answerCodeStringz, *remoteIP, *requestLine, **host = NULL, **referer = NULL, **userAgent = NULL, *vHostName, *TLS, *fd;
	long answerCode;
	int answerBody_len, logLine_len = 0, TLSCipherName_len;
	char *logLine, *logLine_p, *TLSCipherName = NULL;

	FAST_READ_PROPERTY(answerHeaderArray, this_ptr, "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);
	FAST_READ_PROPERTY(answerCodez, this_ptr, "answerCode", sizeof("answerCode") - 1, HASH_OF_answerCode);
	FAST_READ_PROPERTY(answerBodyz, this_ptr, "answerBody", sizeof("answerBody") - 1, HASH_OF_answerBody);
	FAST_READ_PROPERTY(protocolVersion, this_ptr, "protocolVersion", sizeof("protocolVersion") - 1, HASH_OF_protocolVersion);
	FAST_READ_PROPERTY(requestType, this_ptr, "requestType", sizeof("requestType") - 1, HASH_OF_requestType);
	FAST_READ_PROPERTY(fd, this_ptr, "socket", sizeof("socket") - 1, HASH_OF_socket);
	answerCode = Z_LVAL_P(answerCodez);
	answerBody_len = Z_STRLEN_P(answerBodyz);

	if(zend_hash_quick_find(Z_ARRVAL_P(answerHeaderArray), "content-length", sizeof("content-length"), HASH_OF_content_length, (void**) &contentLength) == SUCCESS) {
		convert_to_long(*contentLength);

		if(!Z_LVAL_PP(contentLength) && answerBody_len) {
			Z_LVAL_PP(contentLength) = answerBody_len;
		}
	} else {
		MAKE_STD_ZVAL(contentLengthM);
		contentLength = &contentLengthM;

		Z_TYPE_PP(contentLength) = IS_LONG;
		Z_LVAL_PP(contentLength) = answerBody_len;

		PancakeSetAnswerHeader(answerHeaderArray, "content-length", sizeof("content-length"), *contentLength, 1, HASH_OF_content_length TSRMLS_CC);
	}

	if(answerCode < 100 || answerCode > 599) {
		if(Z_LVAL_PP(contentLength) == 0) {
			zval *vHost, *onEmptyPage204;

			FAST_READ_PROPERTY(vHost, this_ptr, "vHost", 5, HASH_OF_vHost);
			FAST_READ_PROPERTY(onEmptyPage204, vHost, "onEmptyPage204", sizeof("onEmptyPage204") - 1, HASH_OF_onEmptyPage204);

			if(Z_LVAL_P(onEmptyPage204)) {
				answerCode = 204;
			} else {
				answerCode = 200;
			}
		} else {
			answerCode = 200;
		}
	}

	FAST_READ_PROPERTY(requestHeaderArray, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, HASH_OF_requestHeaders);

	if(answerCode >= 200
	&& answerCode < 400
	&& zend_hash_quick_find(Z_ARRVAL_P(requestHeaderArray), "connection", sizeof("connection"), HASH_OF_connection, (void**) &connection) == SUCCESS
	&& *connection == ZVAL_CACHE(KEEP_ALIVE)) {
		long set = 1;

		connectionAnswer = ZVAL_CACHE(KEEP_ALIVE);
		setsockopt(Z_LVAL_P(fd), SOL_SOCKET, SO_KEEPALIVE, &set, sizeof(long));
	} else {
		connectionAnswer = ZVAL_CACHE(CLOSE);
	}

	Z_ADDREF_P(connectionAnswer);
	PancakeSetAnswerHeader(answerHeaderArray, "connection", sizeof("connection"), connectionAnswer, 1, HASH_OF_connection TSRMLS_CC);

	if(PANCAKE_GLOBALS(exposePancake)) {
		Z_ADDREF_P(PANCAKE_GLOBALS(pancakeVersionString));
		PancakeSetAnswerHeader(answerHeaderArray, "server", sizeof("server"), PANCAKE_GLOBALS(pancakeVersionString), 1, HASH_OF_server TSRMLS_CC);
	}

	if(Z_LVAL_PP(contentLength)
	&& !zend_hash_quick_exists(Z_ARRVAL_P(answerHeaderArray), "content-type", sizeof("content-type"), HASH_OF_content_type)) {
		Z_ADDREF_P(PANCAKE_GLOBALS(defaultContentType));
		PancakeSetAnswerHeader(answerHeaderArray, "content-type", sizeof("content-type"), PANCAKE_GLOBALS(defaultContentType), 1, HASH_OF_content_type TSRMLS_CC);
	}

	if(EXPECTED(!zend_hash_quick_exists(Z_ARRVAL_P(answerHeaderArray), "date", sizeof("date"), HASH_OF_date))) {
		char *date = php_format_date("r", 1, time(NULL), 1 TSRMLS_CC);
		zval *dateZval;

		MAKE_STD_ZVAL(dateZval);
		Z_TYPE_P(dateZval) = IS_STRING;
		Z_STRVAL_P(dateZval) = date;
		Z_STRLEN_P(dateZval) = strlen(date);

		PancakeSetAnswerHeader(answerHeaderArray, "date", sizeof("date"), dateZval, 1, HASH_OF_date TSRMLS_CC);
	}

	PancakeQuickWritePropertyLong(this_ptr, "answerCode", sizeof("answerCode"), HASH_OF_answerCode, answerCode);

	char *returnValue;
	char *answerCodeString;
	uint returnValue_len, answerHeader_len;
	char *answerHeaders = PancakeBuildAnswerHeaders(answerHeaderArray, &answerHeader_len);
	char *answerCodeAsString;
	int answerCode_len = spprintf(&answerCodeAsString, 0, "%ld", answerCode);
	int offset = 0;
	int answerCodeString_len;

	FAST_READ_PROPERTY(answerCodeStringz, this_ptr, "answerCodeString", sizeof("answerCodeString") - 1, HASH_OF_answerCodeString);

	if(Z_STRLEN_P(answerCodeStringz)) {
		answerCodeString = Z_STRVAL_P(answerCodeStringz);
		answerCodeString_len = Z_STRLEN_P(answerCodeStringz);
	} else {
		PANCAKE_ANSWER_CODE_STRING(answerCodeString, answerCode);
		answerCodeString_len = strlen(answerCodeString);
	}

	// This is ugly. But it is fast.
	returnValue_len = sizeof("HTTP/1.1  \r\n\r\n") + answerCode_len + answerCodeString_len + answerHeader_len - 2; // 2 = null byte from answerHeaders + null byte from sizeof()
	if(strcmp(Z_STRVAL_P(requestType), "HEAD")) {
		returnValue_len += answerBody_len;
	}

	returnValue = emalloc(returnValue_len + 1);
	memcpy(returnValue, "HTTP/", sizeof("HTTP/") - 1);
	offset += sizeof("HTTP/") - 1;
	memcpy(returnValue + offset, Z_STRVAL_P(protocolVersion), Z_STRLEN_P(protocolVersion));
	offset += Z_STRLEN_P(protocolVersion);
	returnValue[offset] = ' ';
	offset++;
	memcpy(returnValue + offset, answerCodeAsString, answerCode_len);
	offset += answerCode_len;
	returnValue[offset] = ' ';
	offset++;
	memcpy(returnValue + offset, answerCodeString, answerCodeString_len);
	offset += answerCodeString_len;
	returnValue[offset] = '\r';
	offset++;
	returnValue[offset] = '\n';
	offset++;
	memcpy(returnValue + offset, answerHeaders, answerHeader_len - 1);
	offset += answerHeader_len - 1;
	returnValue[offset] = '\r';
	offset++;
	returnValue[offset] = '\n';
	if(strcmp(Z_STRVAL_P(requestType), "HEAD") && answerBody_len) {
		memmove(returnValue + offset + 1, Z_STRVAL_P(answerBodyz), Z_STRLEN_P(answerBodyz));
		efree(Z_STRVAL_P(answerBodyz));
		Z_TYPE_P(answerBodyz) = IS_NULL;
	}
	returnValue[returnValue_len] = '\0';

	// old implementation
	//returnValue_len = spprintf(&returnValue, 0, "HTTP/%s %lu %s\r\n%s\r\n", Z_STRVAL_P(protocolVersion), answerCode, answerCodeString, answerHeaders);
	efree(answerHeaders);

	// Build log line
	offset = answerCode_len;
	FAST_READ_PROPERTY(remoteIP, this_ptr, "remoteIP", sizeof("remoteIP") - 1, HASH_OF_remoteIP);
	FAST_READ_PROPERTY(requestLine, this_ptr, "requestLine", sizeof("requestLine") - 1, HASH_OF_requestLine);
	FAST_READ_PROPERTY(vHost, this_ptr, "vHost", sizeof("vHost") - 1, HASH_OF_vHost);
	FAST_READ_PROPERTY(vHostName, vHost, "name", sizeof("name") - 1, HASH_OF_name);
	FAST_READ_PROPERTY(TLS, this_ptr, "TLS", sizeof("TLS") - 1, HASH_OF_TLS);

	logLine_len = answerCode_len + Z_STRLEN_P(remoteIP) + Z_STRLEN_P(requestLine) + Z_STRLEN_P(vHostName) + sizeof("   on vHost  ()") - 1;

	if(Z_LVAL_P(TLS)) {
		TLSCipherName = PANCAKE_GLOBALS(TLSCipherName)(Z_LVAL_P(fd) TSRMLS_CC);
		logLine_len += (TLSCipherName_len = strlen(TLSCipherName)) + sizeof(" - ") - 1;
	}

	if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaderArray), "host", sizeof("host"), HASH_OF_host, (void**) &host) == SUCCESS) {
		logLine_len += Z_STRLEN_PP(host) + sizeof("via ") - 1;
	}
	if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaderArray), "referer", sizeof("referer"), HASH_OF_referer, (void**) &referer) == SUCCESS) {
		logLine_len += Z_STRLEN_PP(referer) + sizeof(" from ") - 1;
	}
	if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaderArray), "user-agent", sizeof("user-agent"), HASH_OF_user_agent, (void**) &userAgent) == SUCCESS) {
		logLine_len += Z_STRLEN_PP(userAgent) + sizeof(" - ") - 1;
	}

	logLine = emalloc(logLine_len + 1);
	memcpy(logLine, answerCodeAsString, answerCode_len);
	logLine[offset] = ' ';
	offset++;
	memcpy(logLine + offset, Z_STRVAL_P(remoteIP), Z_STRLEN_P(remoteIP));
	offset += Z_STRLEN_P(remoteIP);
	logLine[offset] = ' ';
	offset++;
	memcpy(logLine + offset, Z_STRVAL_P(requestLine), Z_STRLEN_P(requestLine));
	offset += Z_STRLEN_P(requestLine);
	memcpy(logLine + offset, " on vHost ", sizeof(" on vHost ") - 1);
	offset += sizeof(" on vHost ") - 1;
	memcpy(logLine + offset, Z_STRVAL_P(vHostName), Z_STRLEN_P(vHostName));
	offset += Z_STRLEN_P(vHostName);
	logLine[offset] = ' ';
	logLine[offset + 1] = '(';
	offset += 2;
	if(host) {
		memcpy(logLine + offset, "via ", sizeof("via ") - 1);
		offset += sizeof("via ") - 1;
		memcpy(logLine + offset, Z_STRVAL_PP(host), Z_STRLEN_PP(host));
		offset += Z_STRLEN_PP(host);
	}
	if(referer) {
		memcpy(logLine + offset, " from ", sizeof(" from ") - 1);
		offset += sizeof(" from ") - 1;
		memcpy(logLine + offset, Z_STRVAL_PP(referer), Z_STRLEN_PP(referer));
		offset += Z_STRLEN_PP(referer);
	}

	logLine[offset] = ')';
	offset++;

	if(userAgent) {
		memcpy(logLine + offset, " - ", sizeof(" - ") - 1);
		offset += sizeof(" - ") - 1;
		memcpy(logLine + offset, Z_STRVAL_PP(userAgent), Z_STRLEN_PP(userAgent));
		offset += Z_STRLEN_PP(userAgent);
	}

	if(TLSCipherName) {
		memcpy(logLine + offset, " - ", sizeof(" - ") - 1);
		offset += sizeof(" - ") - 1;
		memcpy(logLine + offset, TLSCipherName, TLSCipherName_len);
		offset += TLSCipherName_len;
	}

	logLine[offset] = '\0';
	logLine_p = logLine;
	PancakeOutput(&logLine, logLine_len, OUTPUT_LOG | OUTPUT_REQUEST TSRMLS_CC);

	efree(answerCodeAsString);
	efree(logLine);
	efree(logLine_p);

	// Another request served by Pancake.
	// Let's deliver the result to the client
	PancakeQuickWritePropertyString(this_ptr, "writeBuffer", sizeof("writeBuffer"), HASH_OF_writeBuffer, returnValue, returnValue_len, 0);
}

PHP_METHOD(HTTPRequest, invalidRequest) {
	zval *exception, *answerCode, *vHost, *exceptionPageHandler, *mimeType, *output;
	FILE *handle;
	char *contents;
	long len;
	int useDefaultHandler = 0;

	if(UNEXPECTED(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "O", &exception, invalidHTTPRequestException_ce) == FAILURE)) {
		RETURN_FALSE;
	}

	FAST_READ_PROPERTY(answerCode, exception, "code", 4, HASH_OF_code);
	FAST_READ_PROPERTY(vHost, this_ptr, "vHost", 5, HASH_OF_vHost);
	FAST_READ_PROPERTY(exceptionPageHandler, vHost, "exceptionPageHandler", sizeof("exceptionPageHandler") - 1, HASH_OF_exceptionPageHandler);

	PancakeQuickWriteProperty(this_ptr, answerCode, "answerCode", sizeof("answerCode"), HASH_OF_answerCode TSRMLS_CC);

	mimeType = PancakeMIMEType(Z_STRVAL_P(exceptionPageHandler), Z_STRLEN_P(exceptionPageHandler) TSRMLS_CC);
	Z_ADDREF_P(mimeType);
	PancakeSetAnswerHeader(this_ptr, "content-type", sizeof("content-type"), mimeType, 1, HASH_OF_content_type TSRMLS_CC);

	MAKE_STD_ZVAL(output);

	if(EXPECTED(!virtual_access(Z_STRVAL_P(exceptionPageHandler), F_OK | R_OK TSRMLS_CC))) {
		handle = fopen(Z_STRVAL_P(exceptionPageHandler), "r");
	} else {
		defaultHandler:
		useDefaultHandler = 1;
		handle = fopen("php/exceptionPageHandler.php", "r");
	}

	fseek(handle, 0, SEEK_END);
	len = ftell(handle);
	contents = (char*) emalloc(len + 1);
	fseek(handle, 0, SEEK_SET);
	fread(contents, len, 1, handle);
	contents[len] = '\0';
	fclose(handle);

	if(!useDefaultHandler && strcmp(Z_STRVAL_P(mimeType), "text/x-php")) {
		// Not a PHP script.
		Z_TYPE_P(output) = IS_STRING;
		Z_STRVAL_P(output) = contents;
		Z_STRLEN_P(output) = len;
	} else {
		if(UNEXPECTED(php_output_start_user(NULL, 0, PHP_OUTPUT_HANDLER_STDFLAGS TSRMLS_CC) == FAILURE)) {
			RETURN_FALSE;
		}

		char *eval = "?>";
		int freeEval = 0;

		if(!strncasecmp(contents, "<?php", 5)) {
			eval = &contents[5];
		} else if(!strncmp(contents, "<?", 2)) {
			eval = &contents[2];
		} else {
			freeEval = 1;
			eval = emalloc(3 + len);
			memcpy(eval, "?>", 2);
			memcpy(eval + 2, contents, len + 1);
		}

		char *description = zend_make_compiled_string_description(useDefaultHandler ? "Pancake Exception Page Handler" : Z_STRVAL_P(exceptionPageHandler) TSRMLS_CC);

		zend_rebuild_symbol_table(TSRMLS_C);

		if(!zend_hash_quick_exists(EG(active_symbol_table), "exception", sizeof("exception"), HASH_OF_exception)) {
			zval *runException;
			ALLOC_ZVAL(runException);
			INIT_PZVAL_COPY(runException, exception);
			zval_copy_ctor(runException);

			zend_hash_quick_update(EG(active_symbol_table), "exception", sizeof("exception"), HASH_OF_exception, &runException, sizeof(zval*), NULL);
		}

		if(UNEXPECTED(zend_eval_stringl(eval, strlen(eval), NULL, description TSRMLS_CC) == FAILURE)) {
			if(UNEXPECTED(useDefaultHandler)) {
				zend_error(E_WARNING, "Pancake Default Exception Page Handler execution failed");
			} else {
				if(freeEval) efree(eval);
				efree(description);
				efree(contents);
				goto defaultHandler;
			}
		}

		if(freeEval) efree(eval);
		efree(description);

		php_output_get_contents(output TSRMLS_CC);
		php_output_discard(TSRMLS_C);
	}

	Z_DELREF_P(output);
	PancakeQuickWriteProperty(this_ptr, output, "answerBody", sizeof("answerBody"), HASH_OF_answerBody TSRMLS_CC);

	efree(contents);
}

PHP_METHOD(HTTPRequest, getAnswerCodeString) {
	long answerCode;
	char *answerCodeString;

	if(UNEXPECTED(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &answerCode) == FAILURE)) {
		RETURN_FALSE;
	}

	PANCAKE_ANSWER_CODE_STRING(answerCodeString, answerCode);

	RETURN_STRING(answerCodeString, 1);
}

static zval *PancakeRecursiveResolveParameterRun(char *part, zval *value, zval *destination) {
	char *begin, *end;
	int len;

	begin = strchr(part, '[');
	end = strchr(part, ']');

	if(!begin || UNEXPECTED(!end)) {
		len = end ? end - part : strlen(part);

		if(len) {
			part[len] = '\0';
			add_assoc_zval_ex(destination, part, len + 1, value);
		} else {
			add_next_index_zval(destination, value);
		}
	} else {
		zval *newDestination, **data;

		len = end > begin ? begin - part : end - part;
		part[len] = '\0';

		if(zend_symtable_find(Z_ARRVAL_P(destination), part, len + 1, (void**) &data) == SUCCESS && Z_TYPE_PP(data) == IS_ARRAY) {
			Z_ADDREF_PP(data);
			newDestination = *data;
		} else {
			MAKE_STD_ZVAL(newDestination);
			array_init_size(newDestination, 1);
		}

		newDestination = PancakeRecursiveResolveParameterRun(begin + 1, value, newDestination);

		if(len) {
			add_assoc_zval_ex(destination, part, len + 1, newDestination);
		} else {
			add_next_index_zval(destination, newDestination);
		}
	}

	return destination;
}

static inline zval *PancakeRecursiveResolveParameter(char *part, zval *value, zval *destination) {
	if(!strlen(part)) {
		add_assoc_zval_ex(destination, "", 1, value);
	} else {
		destination = PancakeRecursiveResolveParameterRun(part, value, destination);
	}

	return destination;
}

static inline zval *PancakeResolveFILES(char **opart, zval *zName, zval *zType, zval *zTmpName, zval *zError, zval *zSize, zval *destination) {
	char *part = *opart;
	int part_len = strlen(part);
	char *begin, *end, *dupe;

	begin = strchr(part, '[');

	if(begin) {
		int key_len = part_len - (begin - part) + 1;
		int beginOffset = begin - part;

		part = erealloc(part, part_len + 7);

		memmove(part + beginOffset + 6, part + beginOffset, key_len);
		memcpy(part + beginOffset, "[name]", sizeof("[name]") - 1);
		dupe = estrndup(part, part_len + 6);
		destination = PancakeRecursiveResolveParameter(dupe, zName, destination);
		efree(dupe);

		memcpy(part + beginOffset, "[type]", sizeof("[type]") - 1);
		dupe = estrndup(part, part_len + 6);
		destination = PancakeRecursiveResolveParameter(dupe, zType, destination);
		efree(dupe);

		memcpy(part + beginOffset, "[size]", sizeof("[size]") - 1);
		dupe = estrndup(part, part_len + 6);
		destination = PancakeRecursiveResolveParameter(dupe, zSize, destination);
		efree(dupe);

		part = erealloc(part, part_len + 8);
		memmove(part + beginOffset + 7, part + beginOffset + 6, key_len);
		memcpy(part + beginOffset, "[error]", sizeof("[error]") - 1);
		dupe = estrndup(part, part_len + 7);
		destination = PancakeRecursiveResolveParameter(dupe, zError, destination);
		efree(dupe);

		part = erealloc(part, part_len + sizeof("[tmp_name]"));
		memmove(part + beginOffset + sizeof("[tmp_name]") - 1, part + beginOffset + 7, key_len);
		memcpy(part + beginOffset, "[tmp_name]", sizeof("[tmp_name]") - 1);
		dupe = estrndup(part, part_len + sizeof("[tmp_name]") - 1);
		destination = PancakeRecursiveResolveParameter(dupe, zTmpName, destination);
		efree(dupe);
	} else {
		part = erealloc(part, part_len + 7);

		memcpy(part + part_len, "[name]", sizeof("[name]"));
		destination = PancakeRecursiveResolveParameter(part, zName, destination);
		memcpy(part + part_len, "[type]", sizeof("[type]"));
		destination = PancakeRecursiveResolveParameter(part, zType, destination);
		memcpy(part + part_len, "[size]", sizeof("[size]"));
		destination = PancakeRecursiveResolveParameter(part, zSize, destination);
		part = erealloc(part, part_len + 8);
		memcpy(part + part_len, "[error]", sizeof("[error]"));
		destination = PancakeRecursiveResolveParameter(part, zError, destination);
		part = erealloc(part, part_len + sizeof("[tmp_name]"));
		memcpy(part + part_len, "[tmp_name]", sizeof("[tmp_name]"));
		destination = PancakeRecursiveResolveParameter(part, zTmpName, destination);
	}

	*opart = part;

	return destination;
}

static zval *PancakeProcessQueryString(zval *destination, zval *queryString, const char *delimiter) {
	char *queryString_c = estrndup(Z_STRVAL_P(queryString), Z_STRLEN_P(queryString));
	char *part, *ptr1;

	part = strtok_r(queryString_c, delimiter, &ptr1);

	if(UNEXPECTED(part == NULL)) {
		efree(queryString_c);
		return destination;
	}

	do {
		char *value;
		zval *zvalue;
		LEFT_TRIM(part);
		value = strchr(part, '=');

		MAKE_STD_ZVAL(zvalue);

		if(value != NULL) {
			*value = '\0';
			value++;
			php_url_decode(value, strlen(value));
			Z_TYPE_P(zvalue) = IS_STRING;
			Z_STRLEN_P(zvalue) = strlen(value);
			Z_STRVAL_P(zvalue) = estrndup(value, Z_STRLEN_P(zvalue));
		} else {
			Z_TYPE_P(zvalue) = IS_STRING;
			Z_STRLEN_P(zvalue) = 0;
			Z_STRVAL_P(zvalue) = estrndup("", 0);
		}

		if(!strlen(part) && !Z_STRLEN_P(zvalue)) {
			zval_ptr_dtor(&zvalue);
			continue;
		}

		php_url_decode(part, strlen(part));

		destination = PancakeRecursiveResolveParameter(part, zvalue, destination);

		char *pos = strchr(part, '[');
		if(pos != NULL) {
			*pos = '\0';
		}
	} while((part = strtok_r(NULL, delimiter, &ptr1)) != NULL);

	efree(queryString_c);

	return destination;
}

PANCAKE_API zval *PancakeFetchGET(zval *this_ptr TSRMLS_DC) {
	zval *GETParameters;
	FAST_READ_PROPERTY(GETParameters, this_ptr, "GETParameters", sizeof("GETParameters") - 1, HASH_OF_GETParameters);

	if(Z_TYPE_P(GETParameters) != IS_ARRAY) {
		zval *queryString;
		FAST_READ_PROPERTY(queryString, this_ptr, "queryString", sizeof("queryString") - 1, HASH_OF_queryString);

		MAKE_STD_ZVAL(GETParameters);
		array_init_size(GETParameters, 2);

		if(Z_STRLEN_P(queryString)) {
			GETParameters = PancakeProcessQueryString(GETParameters, queryString, "&");
		}

		if(PG(http_globals)[TRACK_VARS_GET]) {
			zval_ptr_dtor(&PG(http_globals)[TRACK_VARS_GET]);
		}

		PG(http_globals)[TRACK_VARS_GET] = GETParameters;

		PancakeQuickWriteProperty(this_ptr, GETParameters, "GETParameters", sizeof("GETParameters"), HASH_OF_GETParameters TSRMLS_CC);
	}

	return GETParameters;
}

PHP_METHOD(HTTPRequest, getGETParams) {
	zval *retval = PancakeFetchGET(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 1, 0);
}

PANCAKE_API zval *PancakeFetchPOST(zval *this_ptr TSRMLS_DC) {
	zval *POSTParameters;
	FAST_READ_PROPERTY(POSTParameters, this_ptr, "POSTParameters", sizeof("POSTParameters") - 1, HASH_OF_POSTParameters);

	if(Z_TYPE_P(POSTParameters) != IS_ARRAY) {
		zval *rawPOSTData, *files;

		FAST_READ_PROPERTY(rawPOSTData, this_ptr, "rawPOSTData", sizeof("rawPOSTData") - 1, HASH_OF_rawPOSTData);

		MAKE_STD_ZVAL(POSTParameters);
		array_init_size(POSTParameters, 2);

		MAKE_STD_ZVAL(files);
		array_init(files);

		if(PSG(rfc1867_uploaded_files, HashTable*)) {
			zend_hash_destroy(PSG(rfc1867_uploaded_files, HashTable*));
			FREE_HASHTABLE(PSG(rfc1867_uploaded_files, HashTable*));
		}

		ALLOC_HASHTABLE(PSG(rfc1867_uploaded_files, HashTable*));
		zend_hash_init(PSG(rfc1867_uploaded_files, HashTable*), 5, NULL, (dtor_func_t) free_estring, 0);

		if(Z_STRLEN_P(rawPOSTData)) {
			zval *requestHeaders, **contentType;

			FAST_READ_PROPERTY(requestHeaders, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, HASH_OF_requestHeaders);

			if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaders), "content-type", sizeof("content-type"), HASH_OF_content_type, (void**) &contentType) == SUCCESS) {
				if(!strncmp("application/x-www-form-urlencoded", Z_STRVAL_PP(contentType), sizeof("application/x-www-form-urlencoded") - 1)) {
					POSTParameters = PancakeProcessQueryString(POSTParameters, rawPOSTData, "&");
				} else if(!strncmp("multipart/form-data", Z_STRVAL_PP(contentType), sizeof("multipart/form-data") - 1)) {
					// Fetch boundary string
					char *boundary = strstr(Z_STRVAL_PP(contentType), "boundary=");
					if(UNEXPECTED(boundary == NULL)) {
						goto save;
					} else {
						boundary += sizeof("boundary=") - 1;
					}

					char *delimiter = strchr(boundary, ';');
					if(delimiter != NULL) {
						*delimiter = '\0';
					}

					if(UNEXPECTED(!strlen(boundary))) {
						goto save;
					}

					char *realBoundary = estrndup("--", strlen(boundary) + 4);
					strcat(realBoundary, boundary);
					strcat(realBoundary, "\r\n");
					int realBoundary_len = strlen(realBoundary);

					char *data = Z_STRVAL_P(rawPOSTData);
					char *newData, *end, *rawDataOffset;
					int data_len = Z_STRLEN_P(rawPOSTData) - realBoundary_len, rawDataLength;

					data += realBoundary_len;

					do {
						if(data_len < realBoundary_len + 1) {
							goto free;
						}

						newData = end = zend_memnstr(data, realBoundary, realBoundary_len, data + data_len);

						rawDataOffset = strstr(data, "\r\n\r\n");

						if(rawDataOffset != NULL) {
							rawDataOffset += 4;
							if(newData == NULL) {
								rawDataLength = data_len - (rawDataOffset - data) - realBoundary_len - 4;
							} else {
								rawDataLength = newData - rawDataOffset - 2;
							}
						} else {
							rawDataLength = 0;
						}

						char *lineEnd;
						char *line = data;
						char *name = NULL;
						char *fileName = NULL;
						char *type = NULL;

						while((lineEnd = strstr(line, "\r\n")) && (rawDataOffset > lineEnd)) {
							*lineEnd = '\0';

							if(!strncasecmp("content-disposition", line, sizeof("content-disposition") - 1)) {
								name = strstr(line, "name=\"");
								if(name == NULL) {
									goto free;
								}
								name += sizeof("name=\"") - 1;
								char *nameEnd = strstr(name, "\"");
								if(nameEnd == NULL) {
									goto free;
								}
								name = estrndup(name, nameEnd - name);

								fileName = strstr(line, "filename=\"");
								if(fileName != NULL) {
									fileName += sizeof("filename=\"") - 1;
									char *fileNameEnd = strstr(fileName, "\"");

									if(fileNameEnd == NULL) {
										goto free;
									}

									fileName = estrndup(fileName, fileNameEnd - fileName);
								}
							} else if(!strncasecmp("content-type", line, sizeof("content-type") - 1)) {
								// multipart/mixed?

								type = estrdup(line + sizeof("content-type") + 1);
							}

							line = lineEnd + 2;
						}

						if(UNEXPECTED(name == NULL)) {
							if(fileName != NULL) efree(fileName);
							if(type != NULL) efree(type);
							goto free;
						}

						if(fileName != NULL) {
							if(UNEXPECTED(type == NULL)) {
								efree(name);
								efree(fileName);
								goto free;
							}

							zval *zName, *zType, *zTmpName, *zError, *zSize;
							MAKE_STD_ZVAL(zName);
							MAKE_STD_ZVAL(zType);
							MAKE_STD_ZVAL(zTmpName);
							MAKE_STD_ZVAL(zError);
							MAKE_STD_ZVAL(zSize);

							Z_TYPE_P(zError) = IS_LONG;

							// Get temporary file name
							char *tempNam = tempnam(PANCAKE_GLOBALS(tmpDir), "UPL");

							if(UNEXPECTED(tempNam == NULL)) {
								Z_LVAL_P(zError) = 6; // UPLOAD_ERR_NO_TMP_DIR
							} else {
								// Write contents to file
								int fd = creat(tempNam, S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP | S_IROTH | S_IWOTH);
								int tempNam_len = strlen(tempNam);
								char *buffer = estrndup(rawDataOffset, rawDataLength);
								char *tempNam_dupe = estrndup(tempNam, tempNam_len);

								write(fd, buffer, rawDataLength);
								efree(buffer);
								close(fd);
								Z_LVAL_P(zError) = 0; // UPLOAD_ERR_OK
								zend_hash_add(PSG(rfc1867_uploaded_files, HashTable*), tempNam, tempNam_len + 1, &tempNam_dupe, sizeof(char*), NULL);
							}

							Z_TYPE_P(zName) = IS_STRING;
							Z_STRVAL_P(zName) = fileName;
							Z_STRLEN_P(zName) = strlen(fileName);

							Z_TYPE_P(zType) = IS_STRING;
							Z_STRVAL_P(zType) = type;
							Z_STRLEN_P(zType) = strlen(type);

							Z_TYPE_P(zTmpName) = IS_STRING;
							if(EXPECTED(tempNam != NULL)) {
								Z_STRLEN_P(zTmpName) = strlen(tempNam);
								Z_STRVAL_P(zTmpName) = estrndup(tempNam, Z_STRLEN_P(zTmpName));
								free(tempNam);
							} else {
								Z_STRLEN_P(zTmpName) = 0;
								Z_STRVAL_P(zTmpName) = estrndup("", 0);
							}

							Z_TYPE_P(zSize) = IS_LONG;
							Z_LVAL_P(zSize) = rawDataLength;

							files = PancakeResolveFILES(&name, zName, zType, zTmpName, zError, zSize, files);
						} else {
							zval *zvalue;

							MAKE_STD_ZVAL(zvalue);
							Z_TYPE_P(zvalue) = IS_STRING;
							Z_STRLEN_P(zvalue) = rawDataLength;
							Z_STRVAL_P(zvalue) = estrndup(rawDataOffset, rawDataLength);

							POSTParameters = PancakeRecursiveResolveParameter(name, zvalue, POSTParameters);

							char *pos = strchr(name, '[');
							if(pos != NULL) {
								*pos = '\0';
							}
						}

						efree(name);
					} while(newData != NULL && (data_len = data_len - (end - data) - realBoundary_len) && (data = newData + realBoundary_len));

					free:

					efree(realBoundary);
				}
			}
		}

		save:

		PancakeQuickWriteProperty(this_ptr, files, "uploadedFiles", sizeof("uploadedFiles"), HASH_OF_uploadedFiles TSRMLS_CC);
		PancakeQuickWriteProperty(this_ptr, POSTParameters, "POSTParameters", sizeof("POSTParameters"), HASH_OF_POSTParameters TSRMLS_CC);

		if(PG(http_globals)[TRACK_VARS_POST]) {
			zval_ptr_dtor(&PG(http_globals)[TRACK_VARS_POST]);
		}

		if(PG(http_globals)[TRACK_VARS_FILES]) {
			zval_ptr_dtor(&PG(http_globals)[TRACK_VARS_FILES]);
		}

		PG(http_globals)[TRACK_VARS_POST] = POSTParameters;
		PG(http_globals)[TRACK_VARS_FILES] = files;
	}

	return POSTParameters;
}

PHP_METHOD(HTTPRequest, getPOSTParams) {
	zval *retval = PancakeFetchPOST(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 1 , 0);
}

PANCAKE_API zval *PancakeFetchCookies(zval *this_ptr TSRMLS_DC) {
	zval *cookies;
	FAST_READ_PROPERTY(cookies, this_ptr, "cookies", sizeof("cookies") - 1, HASH_OF_cookies);

	if(Z_TYPE_P(cookies) != IS_ARRAY) {
		zval *requestHeaders, **cookie;
		FAST_READ_PROPERTY(requestHeaders, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, HASH_OF_requestHeaders);

		MAKE_STD_ZVAL(cookies);
		array_init_size(cookies, 2);

		if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaders), "cookie", sizeof("cookie"), HASH_OF_cookie, (void**) &cookie) == SUCCESS) {
			cookies = PancakeProcessQueryString(cookies, *cookie, ";");
		}

		if(PG(http_globals)[TRACK_VARS_COOKIE]) {
			zval_ptr_dtor(&PG(http_globals)[TRACK_VARS_COOKIE]);
		}

		PG(http_globals)[TRACK_VARS_COOKIE] = cookies;

		PancakeQuickWriteProperty(this_ptr, cookies, "cookies", sizeof("cookies"), HASH_OF_cookies TSRMLS_CC);
	}

	return cookies;
}

PHP_METHOD(HTTPRequest, getCookies) {
	zval *retval = PancakeFetchCookies(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 1 , 0);
}
