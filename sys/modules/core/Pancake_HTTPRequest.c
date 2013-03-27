
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake_HTTPRequest.c                                      	*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "Pancake.h"

/* Copy of php_autoglobal_merge() with small changes since it is not available as a PHP_API */
static void PancakeAutoglobalMerge(HashTable *dest, HashTable *src TSRMLS_DC)
{
	zval **src_entry, **dest_entry;
	char *string_key;
	uint string_key_len;
	ulong num_key, h;
	HashPosition pos;
	int key_type;

	zend_hash_internal_pointer_reset_ex(src, &pos);
	while (zend_hash_get_current_data_ex(src, (void **)&src_entry, &pos) == SUCCESS) {
		key_type = zend_hash_get_current_key_ex(src, &string_key, &string_key_len, &num_key, 0, &pos);

		if(key_type == HASH_KEY_IS_STRING) {
			h = zend_inline_hash_func(string_key, string_key_len);
		}

		if (Z_TYPE_PP(src_entry) != IS_ARRAY
			|| (key_type == HASH_KEY_IS_STRING && zend_hash_quick_find(dest, string_key, string_key_len, h, (void **) &dest_entry) != SUCCESS)
			|| (key_type == HASH_KEY_IS_LONG && zend_hash_index_find(dest, num_key, (void **)&dest_entry) != SUCCESS)
			|| Z_TYPE_PP(dest_entry) != IS_ARRAY
			) {
			Z_ADDREF_PP(src_entry);
			if (key_type == HASH_KEY_IS_STRING) {
				zend_hash_quick_update(dest, string_key, string_key_len, h, src_entry, sizeof(zval *), NULL);
			} else {
				zend_hash_index_update(dest, num_key, src_entry, sizeof(zval *), NULL);
			}
		} else {
			SEPARATE_ZVAL(dest_entry);
			PancakeAutoglobalMerge(Z_ARRVAL_PP(dest_entry), Z_ARRVAL_PP(src_entry) TSRMLS_CC);
		}
		zend_hash_move_forward_ex(src, &pos);
	}
}

PANCAKE_API void PancakeSetAnswerHeader(zval *answerHeaderArray, char *name, uint name_len, zval *value, uint replace, ulong h TSRMLS_DC) {
	zval **answerHeader;

	if(Z_TYPE_P(answerHeaderArray) == IS_OBJECT) {
		FAST_READ_PROPERTY(answerHeaderArray, answerHeaderArray, "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);
	}

	if(zend_hash_quick_find(Z_ARRVAL_P(answerHeaderArray), name, name_len, h, (void**) &answerHeader) == SUCCESS) {
		if(replace) {
			zend_hash_quick_update(Z_ARRVAL_P(answerHeaderArray), name, name_len, h, (void*) &value, sizeof(zval*), NULL);
			return;
		}

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
		int i;
		index_len--;
		index = estrndup(index, index_len);
		*index = toupper(*index);
		for(i = 1;i < index_len;i++) {
			if(index[i] == '-')
				index[i + 1] = toupper(index[i + 1]);
		}

		if(Z_TYPE_PP(data) == IS_ARRAY) {
			zval **single;

			for(zend_hash_internal_pointer_reset(Z_ARRVAL_PP(data));
				zend_hash_get_current_data(Z_ARRVAL_PP(data), (void**) &single) == SUCCESS;
				zend_hash_move_forward(Z_ARRVAL_PP(data))) {
				convert_to_string(*single);

				size_t elementLength = Z_STRLEN_PP(single) + index_len + 4;
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
				offset ++;
			}
		} else {
			convert_to_string(*data);

			size_t elementLength = Z_STRLEN_PP(data) + index_len + 4;
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

PHP_METHOD(HTTPRequest, __construct) {
	zval *remoteIP, *localIP, *remotePort, *localPort, *answerHeaderArray;

	if(UNEXPECTED(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "zzzz", &remoteIP, &remotePort, &localIP, &localPort) == FAILURE)) {
		RETURN_FALSE;
	}

	PancakeQuickWriteProperty(this_ptr, remoteIP, "remoteIP", sizeof("remoteIP"), HASH_OF_remoteIP TSRMLS_CC);
	PancakeQuickWriteProperty(this_ptr, remotePort, "remotePort", sizeof("remotePort"), HASH_OF_remotePort TSRMLS_CC);
	PancakeQuickWriteProperty(this_ptr, localIP, "localIP", sizeof("localIP"), HASH_OF_localIP TSRMLS_CC);
	PancakeQuickWriteProperty(this_ptr, localPort, "localPort", sizeof("localPort"), HASH_OF_localPort TSRMLS_CC);

	/* Set default virtual host */
	PancakeQuickWriteProperty(this_ptr, PANCAKE_GLOBALS(defaultVirtualHost), "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);

	/* Set answer header array to empty array */
	MAKE_STD_ZVAL(answerHeaderArray);
	array_init_size(answerHeaderArray, 6);
	PancakeQuickWriteProperty(this_ptr, answerHeaderArray, "answerHeaders", sizeof("answerHeaders"), HASH_OF_answerHeaders TSRMLS_CC);

	zval_ptr_dtor(&answerHeaderArray);
}

PHP_METHOD(HTTPRequest, init) {
	char *requestHeader, *ptr1, *ptr2, *ptr3, *requestHeader_dupe, *requestLine;
	char **firstLine = ecalloc(3, sizeof(char*));
	int requestHeader_len, i, requestLine_len;


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
		efree(requestHeader_dupe);
		efree(firstLine);
		return;
	}

	PancakeQuickWritePropertyString(this_ptr, "requestLine", sizeof("requestLine"), HASH_OF_requestLine, requestLine, requestLine_len, 1);

	for(i = 0;i < 3;i++) {
		firstLine[i] = strtok_r(i ? NULL : requestLine, " ", &ptr2);
		if(UNEXPECTED(firstLine[i] == NULL)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Bad request line", sizeof("Bad request line") - 1, 400, requestHeader, requestHeader_len);
			efree(requestHeader_dupe);
			efree(firstLine);
			efree(requestLine);
			return;
		}
	}

	if(EXPECTED(!strcmp(firstLine[2], "HTTP/1.1"))) {
		PancakeQuickWritePropertyString(this_ptr, "protocolVersion", sizeof("protocolVersion"), HASH_OF_protocolVersion, "1.1", 3, 1);
	} else if(UNEXPECTED(strcmp(firstLine[2], "HTTP/1.0"))) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Unsupported protocol", sizeof("Unsupported protocol") - 1, 400, requestHeader, requestHeader_len);
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
		efree(requestHeader_dupe);
		efree(firstLine);
		efree(requestLine);
		return;
	}

	zval *headerArray;
	char *headerName, *headerValue;
	int haveContentLength = 0;
	int contentLength = 0;
	int acceptGZIP = 0;
	char *host = NULL, *authorization = NULL, *if_unmodified_since = NULL;

	MAKE_STD_ZVAL(headerArray);
	array_init_size(headerArray, 8);

	char *header;

	for(header = strtok_r(NULL, "\r\n", &ptr1);
		header != NULL;
		header = strtok_r(NULL, "\r\n", &ptr1)) {

		headerValue = strchr(header, ':');

		if(UNEXPECTED(headerValue == NULL))
			continue;

		headerName = header;
		headerName[headerValue - headerName] = '\0';
		headerValue++;

		php_strtolower(headerName, strlen(headerName));
		LEFT_TRIM(headerValue);

		add_assoc_string_ex(headerArray, headerName, strlen(headerName) + 1, headerValue, 1);

		if(!strcmp(headerName, "content-length")) {
			haveContentLength = 1;
			contentLength = atol(headerValue);
		} else if(!strcmp(headerName, "host")) {
			host = estrdup(headerValue);
		} else if(!strcmp(headerName, "accept-encoding")) {
			char *ptr4;
			char *acceptedCompression;
			zval *acceptedCompressions;

			acceptedCompression = strtok_r(headerValue, ",", &ptr4);

			MAKE_STD_ZVAL(acceptedCompressions);
			array_init_size(acceptedCompressions, 2);

			while(acceptedCompression != NULL) {
				int acceptedCompression_len = strlen(acceptedCompression);
				php_strtolower(acceptedCompression, acceptedCompression_len);
				LEFT_TRIM(acceptedCompression);
				add_next_index_stringl(acceptedCompressions, acceptedCompression, acceptedCompression_len, 1);

				if(!strcmp(acceptedCompression, "gzip")) {
					acceptGZIP = 1;
				}

				acceptedCompression = strtok_r(NULL, ",", &ptr4);
			}

			PancakeQuickWriteProperty(this_ptr, acceptedCompressions, "acceptedCompressions", sizeof("acceptedCompressions"), HASH_OF_acceptedCompressions TSRMLS_CC);
			zval_ptr_dtor(&acceptedCompressions);
		} else if(!strcmp(headerName, "authorization")) {
			authorization = estrdup(headerValue);
		} else if(!strcmp(headerName, "if-unmodified-since")) {
			if_unmodified_since = estrdup(headerValue);
		} else if(!strcmp(headerName, "range")) {
			char *to = strchr(headerValue, '-');
			if(EXPECTED(to != NULL && !strncmp(headerValue, "bytes=", 6))) {
				zval *rangeFrom, *rangeTo;
				*to = '\0';
				to++;

				headerValue += 6;

				MAKE_STD_ZVAL(rangeFrom);
				Z_TYPE_P(rangeFrom) = IS_STRING;
				Z_STRLEN_P(rangeFrom) = strlen(headerValue);
				Z_STRVAL_P(rangeFrom) = estrndup(headerValue, Z_STRLEN_P(rangeFrom));
				convert_to_long_base(rangeFrom, 10);

				MAKE_STD_ZVAL(rangeTo);
				Z_TYPE_P(rangeTo) = IS_STRING;
				Z_STRLEN_P(rangeTo) = strlen(to);
				Z_STRVAL_P(rangeTo) = estrndup(to, Z_STRLEN_P(rangeTo));
				convert_to_long_base(rangeTo, 10);

				PancakeQuickWriteProperty(this_ptr, rangeFrom, "rangeFrom", sizeof("rangeFrom"), HASH_OF_rangeFrom TSRMLS_CC);
				PancakeQuickWriteProperty(this_ptr, rangeTo, "rangeTo", sizeof("rangeTo"), HASH_OF_rangeTo TSRMLS_CC);

				zval_ptr_dtor(&rangeFrom);
				zval_ptr_dtor(&rangeTo);
			}
		}
	}

	PancakeQuickWriteProperty(this_ptr, headerArray, "requestHeaders", sizeof("requestHeaders"), HASH_OF_requestHeaders TSRMLS_CC);
	zval_ptr_dtor(&headerArray);

	efree(requestHeader_dupe);

	zval **vHost;
	zval **newvHost;

	if(UNEXPECTED(host == NULL)) {
		if(UNEXPECTED(!strcmp(firstLine[2], "HTTP/1.1"))) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Missing required header: Host",
					sizeof("Missing required header: Host") - 1, 400, requestHeader, requestHeader_len);
			if(authorization != NULL) efree(authorization);
			if(if_unmodified_since != NULL) efree(if_unmodified_since);
			efree(firstLine);
			efree(requestLine);
			return;
		} else {
			zval *listen;
			zval **hostZval;

			FAST_READ_PROPERTY(listen, PANCAKE_GLOBALS(defaultVirtualHost), "listen", sizeof("listen") - 1, HASH_OF_listen);
			vHost = &PANCAKE_GLOBALS(defaultVirtualHost);
			zend_hash_index_find(Z_ARRVAL_P(listen), 0, (void**) &hostZval);
			host = estrndup(Z_STRVAL_PP(hostZval), Z_STRLEN_PP(hostZval));
		}
	} else if(zend_hash_find(Z_ARRVAL_P(PANCAKE_GLOBALS(virtualHostArray)), host, strlen(host) + 1, (void**) &newvHost) == SUCCESS && Z_OBJ_HANDLE_PP(newvHost) != Z_OBJ_HANDLE_P(PANCAKE_GLOBALS(defaultVirtualHost))) {
		vHost = newvHost;
		PancakeQuickWriteProperty(this_ptr, *vHost, "vHost", sizeof("vHost"), HASH_OF_vHost TSRMLS_CC);
	} else {
		vHost = &PANCAKE_GLOBALS(defaultVirtualHost);
	}

	if(!strcmp(firstLine[0], "POST")) {
		if(UNEXPECTED(!haveContentLength)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("Your request can't be processed without a given Content-Length",
					sizeof("Your request can't be processed without a given Content-Length") - 1, 411, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(host);
			efree(requestLine);
			if(authorization != NULL) efree(authorization);
			if(if_unmodified_since != NULL) efree(if_unmodified_since);
			return;
		}

		if(contentLength > SG(post_max_size)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("The uploaded content is too large.",
					sizeof("The uploaded content is too large.") - 1, 413, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(host);
			efree(requestLine);
			if(authorization != NULL) efree(authorization);
			if(if_unmodified_since != NULL) efree(if_unmodified_since);
			return;
		}
	}

	/* Enough information for TRACE gathered */
	if(UNEXPECTED(!strcmp(firstLine[0], "TRACE"))) {
		zval *contentTypeZval;
		MAKE_STD_ZVAL(contentTypeZval);
		Z_TYPE_P(contentTypeZval) = IS_STRING;
		Z_STRVAL_P(contentTypeZval) = estrndup("message/http", 12);
		Z_STRLEN_P(contentTypeZval) = 12;

		PancakeQuickWritePropertyString(this_ptr, "answerBody", sizeof("answerBody"), HASH_OF_answerBody, requestHeader, requestHeader_len, 1);
		PancakeSetAnswerHeader(this_ptr, "content-type", sizeof("content-type"), contentTypeZval, 1, 14553278787112811407U TSRMLS_CC);
		efree(firstLine);
		efree(host);
		efree(requestLine);
		if(authorization != NULL) efree(authorization);
		if(if_unmodified_since != NULL) efree(if_unmodified_since);
		return;
	}

	zval *documentRootz;
	FAST_READ_PROPERTY(documentRootz, *vHost, "documentRoot", sizeof("documentRoot") - 1, HASH_OF_documentRoot);
	char *documentRoot = Z_STRVAL_P(documentRootz);

	PancakeQuickWritePropertyString(this_ptr, "originalRequestURI", sizeof("originalRequestURI"), HASH_OF_originalRequestURI, firstLine[1], strlen(firstLine[1]), 1);

	/* Apply rewrite rules */
	zval *rewriteRules;
	FAST_READ_PROPERTY(rewriteRules, *vHost, "rewriteRules", sizeof("rewriteRules") - 1, HASH_OF_rewriteRules);

	int fL1isMalloced = 0;
	char *queryStringStart;

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
			zval **value;

			if(path != NULL) {
				efree(path);
			}
			spprintf(&path, (((queryStringStart = strchr(firstLine[1], '?')) != NULL)
									? strlen(documentRoot) + (queryStringStart - firstLine[1])
									: 0), "%s%s", documentRoot, firstLine[1]);

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "if", sizeof("if"), 193494708, (void**) &value) == SUCCESS) {
				pcre_cache_entry *pcre;
				zval *pcre_retval;

				if(UNEXPECTED((pcre = pcre_get_compiled_regex_cache(Z_STRVAL_PP(value), Z_STRLEN_PP(value) TSRMLS_CC)) == NULL)) {
					continue;
				}

				MAKE_STD_ZVAL(pcre_retval);

				php_pcre_match_impl(pcre, firstLine[1], strlen(firstLine[1]),  pcre_retval, NULL, 0, 0, 0, 0 TSRMLS_CC);

				if(Z_LVAL_P(pcre_retval) == 0) {
					zval_ptr_dtor(&pcre_retval);
					continue;
				}

				zval_ptr_dtor(&pcre_retval);
			}

			if(		(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "location", sizeof("location"), 249896952137776350U, (void**) &value) == SUCCESS
					&& strncmp(Z_STRVAL_PP(value), firstLine[1], Z_STRLEN_PP(value)) != 0)
			||		(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "precondition", sizeof("precondition"), 17926165567001274195U, (void**) &value) == SUCCESS
					&& (	Z_TYPE_PP(value) != IS_LONG
						||	(	Z_LVAL_PP(value) == 404
							&&	virtual_access(path, F_OK TSRMLS_CC) == 0)
						||	(	Z_LVAL_PP(value) == 403
							&&	(	virtual_access(path, F_OK TSRMLS_CC) == -1
								||	virtual_access(path, R_OK TSRMLS_CC) == 0))))) {
				continue;
			}

			zval **value2;

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "pattern", sizeof("pattern"), 7572787993791075U, (void**) &value) == SUCCESS
			&& zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "replace", sizeof("replace"), 7572878230359585U, (void**) &value2) == SUCCESS) {
				char *result = NULL;
				int result_len = 0, replace_count = 0;

				result = php_pcre_replace(Z_STRVAL_PP(value), Z_STRLEN_PP(value), firstLine[1], strlen(firstLine[1]), *value2, 0, &result_len, -1, &replace_count TSRMLS_CC);

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
					PancakeSetAnswerHeader(this_ptr, headerName, headerName_len, *headerValue, 1, zend_inline_hash_func(headerName, headerName_len) TSRMLS_CC);
				}
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "exception", sizeof("exception"), 8246287202855534580U, (void**) &value) == SUCCESS
			&& EXPECTED(Z_TYPE_PP(value) == IS_LONG)) {
				if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "exceptionmessage", sizeof("exceptionmessage"), 14507601710368331673U, (void**) &value2) == SUCCESS) {
					PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL(Z_STRVAL_PP(value2), Z_STRLEN_PP(value2), Z_LVAL_PP(value), requestHeader, requestHeader_len);
				} else {
					PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("The server was unable to process your request", sizeof("The server was unable to process your request") - 1, Z_LVAL_PP(value), requestHeader, requestHeader_len);
				}

				if(fL1isMalloced) efree(firstLine[1]);
				efree(firstLine);
				efree(host);
				efree(requestLine);
				efree(path);
				if(authorization != NULL) efree(authorization);
				if(if_unmodified_since != NULL) efree(if_unmodified_since);
				return;
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "destination", sizeof("destination"), 15010265353095908391U, (void**) &value) == SUCCESS) {
				Z_ADDREF_PP(value);
				PancakeSetAnswerHeader(this_ptr, "location", sizeof("location"), *value, 1, 249896952137776350U TSRMLS_CC);
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Redirecting...", sizeof("Redirecting...") - 1, 301);
				if(fL1isMalloced) efree(firstLine[1]);
				efree(firstLine);
				efree(host);
				efree(requestLine);
				efree(path);
				if(authorization != NULL) efree(authorization);
				if(if_unmodified_since != NULL) efree(if_unmodified_since);
				return;
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "pathinfo", sizeof("pathinfo"), 249902003330075646U, (void**) &value) == SUCCESS) {
				pcre_cache_entry *pcre;
				zval *pcre_retval, *matches, **match;

				if(UNEXPECTED((pcre = pcre_get_compiled_regex_cache(Z_STRVAL_PP(value), Z_STRLEN_PP(value) TSRMLS_CC)) == NULL)) {
					continue;
				}

				MAKE_STD_ZVAL(pcre_retval);
				MAKE_STD_ZVAL(matches);
				Z_TYPE_P(matches) = IS_NULL;

				php_pcre_match_impl(pcre, firstLine[1], strlen(firstLine[1]),  pcre_retval, matches, 0, 0, 0, 0 TSRMLS_CC);

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

	char *uriptr, *requestFilePath, *queryString;
	int requestFilePath_len;

	requestFilePath = strtok_r(firstLine[1], "?", &uriptr);
	queryString = strtok_r(NULL, "?", &uriptr);
	if(queryString != NULL)
		queryString = estrdup(queryString);
	else
		queryString = estrndup("", 0);

	if(UNEXPECTED(!strncasecmp("http://", requestFilePath, 7))) {
		requestFilePath = &requestFilePath[7];
		requestFilePath =  strchr(requestFilePath, '/');
	}

	requestFilePath_len = strlen(requestFilePath);
	requestFilePath = estrndup(requestFilePath, requestFilePath_len);

	if(fL1isMalloced) efree(firstLine[1]);

	if(UNEXPECTED(requestFilePath[0] != '/')) {
		char *requestFilePath_c = estrndup(requestFilePath, requestFilePath_len);
		requestFilePath = erealloc(requestFilePath, requestFilePath_len + 2);
		requestFilePath[0] = '/';
		memcpy(requestFilePath + 1, requestFilePath_c, requestFilePath_len + 1);
		requestFilePath_len++;
		efree(requestFilePath_c);
	}

	if(UNEXPECTED(strstr(requestFilePath, "../") != NULL)) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("You are not allowed to access the requested file",
				sizeof("You are not allowed to access the requested file") - 1, 403, requestHeader, requestHeader_len);
		efree(host);
		efree(firstLine);
		efree(requestLine);
		efree(requestFilePath);
		if(authorization != NULL) efree(authorization);
		if(if_unmodified_since != NULL) efree(if_unmodified_since);
		return;
	}

	zval *mimeType = NULL;
	char *filePath;
	int filePath_len;
	zval *AJP13;
	FAST_READ_PROPERTY(AJP13, *vHost, "AJP13", 5, HASH_OF_AJP13);

	if(EXPECTED(Z_TYPE_P(AJP13) != IS_OBJECT)) {
		struct stat st;

		filePath = emalloc(Z_STRLEN_P(documentRootz) + requestFilePath_len + 1);
		memcpy(filePath, documentRoot, Z_STRLEN_P(documentRootz));
		memcpy(filePath + Z_STRLEN_P(documentRootz), requestFilePath, requestFilePath_len + 1);

		if(!stat(filePath, &st) && S_ISDIR(st.st_mode)) {
			if(requestFilePath[requestFilePath_len - 1] !=  '/') {
				zval *redirectValue;

				MAKE_STD_ZVAL(redirectValue);
				Z_TYPE_P(redirectValue) = IS_STRING;
				Z_STRLEN_P(redirectValue) = spprintf(&Z_STRVAL_P(redirectValue), 0, "http://%s%s/?%s", host, requestFilePath, queryString ? queryString : "");

				PancakeSetAnswerHeader(this_ptr, "location", sizeof("location"), redirectValue, 1, 249896952137776350U TSRMLS_CC);
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Redirecting...", sizeof("Redirecting...") - 1, 301);
				efree(filePath);
				efree(host);
				efree(firstLine);
				efree(requestLine);
				efree(requestFilePath);
				efree(queryString);
				if(authorization != NULL) efree(authorization);
				if(if_unmodified_since != NULL) efree(if_unmodified_since);
				return;
			}

			zval *indexFiles;
			FAST_READ_PROPERTY(indexFiles, *vHost, "indexFiles", sizeof("indexFiles") - 1, HASH_OF_indexFiles);

			if(EXPECTED(Z_TYPE_P(indexFiles) == IS_ARRAY)) {
				zval **indexFile;

				for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(indexFiles));
					zend_hash_get_current_data(Z_ARRVAL_P(indexFiles), (void**) &indexFile) == SUCCESS;
					zend_hash_move_forward(Z_ARRVAL_P(indexFiles))) {
					if(UNEXPECTED(Z_TYPE_PP(indexFile) != IS_STRING))
						continue;

					efree(filePath);
					filePath = emalloc(Z_STRLEN_P(documentRootz) + requestFilePath_len + Z_STRLEN_PP(indexFile) + 1);
					memcpy(filePath, documentRoot, Z_STRLEN_P(documentRootz));
					memcpy(filePath + Z_STRLEN_P(documentRootz), requestFilePath, requestFilePath_len);
					memcpy(filePath + Z_STRLEN_P(documentRootz) + requestFilePath_len, Z_STRVAL_PP(indexFile), Z_STRLEN_PP(indexFile) + 1);

					if(!virtual_access(filePath, F_OK | R_OK TSRMLS_CC)) {
						requestFilePath = erealloc(requestFilePath, (requestFilePath_len + Z_STRLEN_PP(indexFile) + 1) * sizeof(char));
						memcpy(requestFilePath + requestFilePath_len, Z_STRVAL_PP(indexFile), Z_STRLEN_PP(indexFile) + 1);
						requestFilePath_len += Z_STRLEN_PP(indexFile);
						stat(filePath, &st);
						goto checkRead;
					}
				}
			}

			zval *allowDirectoryListings;
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
				if(authorization != NULL) efree(authorization);
				if(if_unmodified_since != NULL) efree(if_unmodified_since);
				return;
			}
		// end is_dir
		}

		if(acceptGZIP == 1) {
			zval *allowGZIPStatic;
			FAST_READ_PROPERTY(allowGZIPStatic, *vHost, "gzipStatic", sizeof("gzipStatic") - 1, HASH_OF_gzipStatic);

			if(Z_TYPE_P(allowGZIPStatic) <= IS_BOOL && Z_LVAL_P(allowGZIPStatic) > 0) {
				efree(filePath);
				filePath = emalloc(Z_STRLEN_P(documentRootz) + requestFilePath_len + 4);
				memcpy(filePath, documentRoot, Z_STRLEN_P(documentRootz));
				memcpy(filePath + Z_STRLEN_P(documentRootz), requestFilePath, requestFilePath_len);
				memcpy(filePath + Z_STRLEN_P(documentRootz) + requestFilePath_len, ".gz", 4);

				if(!virtual_access(filePath, F_OK | R_OK TSRMLS_CC)) {
					mimeType = PancakeMIMEType(requestFilePath, requestFilePath_len TSRMLS_CC);
					requestFilePath = erealloc(requestFilePath, requestFilePath_len + 4);
					memcpy(requestFilePath + requestFilePath_len, ".gz", 4);
					requestFilePath_len += 3;

					zval *gzipStr;
					MAKE_STD_ZVAL(gzipStr);
					Z_TYPE_P(gzipStr) = IS_STRING;
					Z_STRVAL_P(gzipStr) = estrndup("gzip", 4);
					Z_STRLEN_P(gzipStr) = 4;

					PancakeSetAnswerHeader(this_ptr, "content-encoding", sizeof("content-encoding"), gzipStr, 1, HASH_OF_content_encoding TSRMLS_CC);
					stat(filePath, &st);
				}
			}
		}

		checkRead:

		efree(host);
		efree(filePath);

		filePath = emalloc(Z_STRLEN_P(documentRootz) + requestFilePath_len + 1);
		memcpy(filePath, documentRoot, Z_STRLEN_P(documentRootz));
		memcpy(filePath + Z_STRLEN_P(documentRootz), requestFilePath, requestFilePath_len + 1);

		if(UNEXPECTED(virtual_access(filePath, F_OK TSRMLS_CC))) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("File does not exist",
					sizeof("File does not exist") - 1, 404, requestHeader, requestHeader_len);
			efree(filePath);
			efree(firstLine);
			efree(requestLine);
			efree(requestFilePath);
			efree(queryString);
			if(authorization != NULL) efree(authorization);
			if(if_unmodified_since != NULL) efree(if_unmodified_since);
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
			if(authorization != NULL) efree(authorization);
			if(if_unmodified_since != NULL) efree(if_unmodified_since);
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
			if(authorization != NULL) efree(authorization);
			if(if_unmodified_since != NULL) efree(if_unmodified_since);
			return;
		}

		if(if_unmodified_since != NULL) {
			if(st.st_mtime != php_parse_date(if_unmodified_since, NULL)) {
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("File was modified since requested time.",
						sizeof("File was modified since requested time.") - 1, 412, requestHeader, requestHeader_len);
				efree(firstLine);
				efree(requestLine);
				efree(requestFilePath);
				efree(queryString);
				if(authorization != NULL) efree(authorization);
				efree(if_unmodified_since);
				return;
			}

			efree(if_unmodified_since);
		}
	} else {
		efree(host);
		if(if_unmodified_since != NULL) {
			efree(if_unmodified_since);
		}
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
			if(authorization != NULL) efree(authorization);

			// Let's throw a 500 for safety
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("An internal server error occured while trying to handle your request",
					sizeof("An internal server error occured while trying to handle your request") - 1, 500, requestHeader, requestHeader_len);
			return;
		}

		zval_ptr_dtor(&callArray);
		zval_ptr_dtor(&arg);

		if(Z_TYPE(authData) == IS_ARRAY) {
			if(authorization != NULL) {
				char *ptr1;

				strtok_r(authorization, " ", &ptr1);
				char *authorizationBase64 = strtok_r(NULL, " ", &ptr1);

				if(EXPECTED(authorizationBase64 != NULL)) {
					char *ptr2;
					int ret_len;

					unsigned char *decoded = php_base64_decode_ex((unsigned char*) authorizationBase64, strlen(authorizationBase64), &ret_len, 0);

					char **userPassword = ecalloc(2, sizeof(char*));
					userPassword[0] = strtok_r((char*) decoded, ":", &ptr2);
					userPassword[1] = strtok_r(NULL, ":", &ptr2);

					if(EXPECTED(userPassword[0] != NULL && userPassword[1] != NULL)) {
						MAKE_STD_ZVAL(callArray);
						Z_TYPE_P(callArray) = IS_STRING;
						Z_STRLEN_P(callArray) = sizeof("isValidAuthentication") - 1;
						Z_STRVAL_P(callArray) = estrndup("isValidAuthentication", sizeof("isValidAuthentication") - 1);

						zval *arg2, *arg3;

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

						zval *args[3] = {arg, arg2, arg3};
						zval retval;

						efree(userPassword);
						efree(decoded);

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

			zval **realm;
			zend_hash_quick_find(Z_ARRVAL(authData), "realm", sizeof("realm"), 6953973961110U, (void**) &realm);

			zval *authenticate;
			MAKE_STD_ZVAL(authenticate);
			Z_TYPE_P(authenticate) = IS_STRING;
			Z_STRLEN_P(authenticate) = sizeof("Basic realm=\"\"") - 1 + Z_STRLEN_PP(realm);
			Z_STRVAL_P(authenticate) = estrndup("Basic realm=\"", Z_STRLEN_P(authenticate));
			strcat(Z_STRVAL_P(authenticate), Z_STRVAL_PP(realm));
			strcat(Z_STRVAL_P(authenticate), "\"");

			PancakeSetAnswerHeader(this_ptr, "www-authenticate", sizeof("www-authenticate"), authenticate, 1, 10801095474844103286U TSRMLS_CC);
			efree(firstLine);
			efree(requestLine);
			efree(requestFilePath);
			efree(queryString);
			zval_dtor(&authData);
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL("You need to authorize in order to access this file.",
					sizeof("You need to authorize in order to access this file.") - 1, 401, requestHeader, requestHeader_len);
			return;
		} else if(authorization != NULL) {
			efree(authorization);
		}

		zval_dtor(&authData);
	}

	end:;

	if(mimeType == NULL)
		mimeType = PancakeMIMEType(requestFilePath, requestFilePath_len TSRMLS_CC);

	if(queryString == NULL) {
		queryString = "";
	}

	PancakeQuickWritePropertyString(this_ptr, "queryString", sizeof("queryString"), HASH_OF_queryString, queryString, strlen(queryString), 1);
	PancakeQuickWritePropertyString(this_ptr, "requestFilePath", sizeof("requestFilePath"), HASH_OF_requestFilePath, requestFilePath, requestFilePath_len, 1);
	PancakeQuickWriteProperty(this_ptr, mimeType, "mimeType", sizeof("mimeType"), HASH_OF_mimeType TSRMLS_CC);
	PancakeQuickWritePropertyLong(this_ptr, "requestTime", sizeof("requestTime"), HASH_OF_requestTime, time(NULL));

	efree(firstLine);
	efree(requestLine);
	efree(requestFilePath);
	efree(queryString);

	struct timeval tp = {0};

	gettimeofday(&tp, NULL);

	PancakeQuickWritePropertyDouble(this_ptr, "requestMicrotime", sizeof("requestMicrotime"), HASH_OF_requestMicrotime, (double) (tp.tv_sec + tp.tv_usec / 1000000.00));
}

PHP_METHOD(HTTPRequest, buildAnswerHeaders) {
	zval *vHost, *answerHeaderArray, *answerCodez, *answerBodyz, *protocolVersion, **contentLength, *requestHeaderArray, *connectionAnswer, **connection, *requestType;
	long answerCode;
	int answerBody_len;

	FAST_READ_PROPERTY(answerHeaderArray, this_ptr, "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);
	FAST_READ_PROPERTY(answerCodez, this_ptr, "answerCode", sizeof("answerCode") - 1, HASH_OF_answerCode);
	FAST_READ_PROPERTY(answerBodyz, this_ptr, "answerBody", sizeof("answerBody") - 1, HASH_OF_answerBody);
	FAST_READ_PROPERTY(protocolVersion, this_ptr, "protocolVersion", sizeof("protocolVersion") - 1, HASH_OF_protocolVersion);
	FAST_READ_PROPERTY(requestType, this_ptr, "requestType", sizeof("requestType") - 1, HASH_OF_requestType);
	answerCode = Z_LVAL_P(answerCodez);
	answerBody_len = Z_STRLEN_P(answerBodyz);

	if(zend_hash_quick_find(Z_ARRVAL_P(answerHeaderArray), "content-length", sizeof("content-length"), 2767439838230162255U, (void**) &contentLength) == SUCCESS) {
		convert_to_long(*contentLength);

		if(!Z_LVAL_PP(contentLength) && answerBody_len) {
			Z_LVAL_PP(contentLength) = answerBody_len;
		}
	} else {
		zval *contentLengthM;
		MAKE_STD_ZVAL(contentLengthM);
		contentLength = &contentLengthM;

		Z_TYPE_PP(contentLength) = IS_LONG;
		Z_LVAL_PP(contentLength) = answerBody_len;

		PancakeSetAnswerHeader(answerHeaderArray, "content-length", sizeof("content-length"), *contentLength, 1, 2767439838230162255U TSRMLS_CC);
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

	MAKE_STD_ZVAL(connectionAnswer);
	Z_TYPE_P(connectionAnswer) = IS_STRING;

	if(answerCode >= 200
	&& answerCode < 400
	&& zend_hash_quick_find(Z_ARRVAL_P(requestHeaderArray), "connection", sizeof("connection"), 13869595640170944373U, (void**) &connection) == SUCCESS
	&& !strcasecmp(Z_STRVAL_PP(connection), "keep-alive")) {
		Z_STRLEN_P(connectionAnswer) = sizeof("keep-alive") - 1;
		Z_STRVAL_P(connectionAnswer) = estrndup("keep-alive", sizeof("keep-alive") - 1);
	} else {
		Z_STRLEN_P(connectionAnswer) = sizeof("close") - 1;
		Z_STRVAL_P(connectionAnswer) = estrndup("close", sizeof("close") - 1);
	}

	PancakeSetAnswerHeader(answerHeaderArray, "connection", sizeof("connection"), connectionAnswer, 1, 13869595640170944373U TSRMLS_CC);

	if(PANCAKE_GLOBALS(exposePancake)) {
		Z_ADDREF_P(PANCAKE_GLOBALS(pancakeVersionString));
		PancakeSetAnswerHeader(answerHeaderArray, "server", sizeof("server"), PANCAKE_GLOBALS(pancakeVersionString), 1, 229482452699676U TSRMLS_CC);
	}

	if(Z_LVAL_PP(contentLength)
	&& !zend_hash_quick_exists(Z_ARRVAL_P(answerHeaderArray), "content-type", sizeof("content-type"), 14553278787112811407U)) {
		Z_ADDREF_P(PANCAKE_GLOBALS(defaultContentType));
		PancakeSetAnswerHeader(answerHeaderArray, "content-type", sizeof("content-type"), PANCAKE_GLOBALS(defaultContentType), 1, 14553278787112811407U TSRMLS_CC);
	}

	if(EXPECTED(!zend_hash_quick_exists(Z_ARRVAL_P(answerHeaderArray), "date", sizeof("date"), 210709757379U))) {
		char *date = php_format_date("r", 1, time(NULL), 1 TSRMLS_CC);

		zval *dateZval;
		MAKE_STD_ZVAL(dateZval);
		Z_TYPE_P(dateZval) = IS_STRING;
		Z_STRVAL_P(dateZval) = date;
		Z_STRLEN_P(dateZval) = strlen(date);

		PancakeSetAnswerHeader(answerHeaderArray, "date", sizeof("date"), dateZval, 1, 210709757379U TSRMLS_CC);
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

	PANCAKE_ANSWER_CODE_STRING(answerCodeString, answerCode);
	answerCodeString_len = strlen(answerCodeString);

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
	efree(answerCodeAsString);

	// Another request served by Pancake.
	// Let's deliver the result to the client
	RETURN_STRINGL(returnValue, returnValue_len, 0);
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
	PancakeSetAnswerHeader(this_ptr, "content-type", sizeof("content-type"), mimeType, 1, 14553278787112811407U TSRMLS_CC);

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

zval *PancakeProcessQueryString(zval *destination, zval *queryString, const char *delimiter) {
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

zval *PancakeFetchGET(zval *this_ptr TSRMLS_DC) {
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

		PancakeQuickWriteProperty(this_ptr, GETParameters, "GETParameters", sizeof("GETParameters"), HASH_OF_GETParameters TSRMLS_CC);
		Z_DELREF_P(GETParameters);
	}

	return GETParameters;
}

zend_bool PancakeJITFetchGET(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchGET(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);

	Z_ADDREF_P(retval);
	zend_hash_quick_update(&EG(symbol_table), "_GET", sizeof("_GET"), HASH_OF__GET, &retval, sizeof(zval*), NULL);

	return 0;
}

PHP_METHOD(HTTPRequest, getGETParams) {
	zval *retval = PancakeFetchGET(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 1, 0);
}

zval *PancakeFetchPOST(zval *this_ptr TSRMLS_DC) {
	zval *POSTParameters;
	FAST_READ_PROPERTY(POSTParameters, this_ptr, "POSTParameters", sizeof("POSTParameters") - 1, HASH_OF_POSTParameters);

	if(Z_TYPE_P(POSTParameters) != IS_ARRAY) {
		zval *rawPOSTData, *files, *tempNames;

		FAST_READ_PROPERTY(rawPOSTData, this_ptr, "rawPOSTData", sizeof("rawPOSTData") - 1, HASH_OF_rawPOSTData);

		MAKE_STD_ZVAL(POSTParameters);
		array_init_size(POSTParameters, 2);

		MAKE_STD_ZVAL(files);
		array_init(files);

		MAKE_STD_ZVAL(tempNames);
		array_init(tempNames);

		if(Z_STRLEN_P(rawPOSTData)) {
			zval *requestHeaders, **contentType;

			FAST_READ_PROPERTY(requestHeaders, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, HASH_OF_requestHeaders);

			if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaders), "content-type", sizeof("content-type"), 14553278787112811407U, (void**) &contentType) == SUCCESS) {
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
								char *buffer = estrndup(rawDataOffset, rawDataLength);
								write(fd, buffer, rawDataLength);
								efree(buffer);
								close(fd);
								Z_LVAL_P(zError) = 0; // UPLOAD_ERR_OK
								add_next_index_string(tempNames, tempNam, 1);
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
		PancakeQuickWriteProperty(this_ptr, tempNames, "uploadedFileTempNames", sizeof("uploadedFileTempNames"), HASH_OF_uploadedFileTempNames TSRMLS_CC);
		Z_DELREF_P(POSTParameters);
		Z_DELREF_P(files);
		Z_DELREF_P(tempNames);
	}

	return POSTParameters;
}

zend_bool PancakeJITFetchPOST(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchPOST(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);

	Z_ADDREF_P(retval);
	zend_hash_quick_update(&EG(symbol_table), "_POST", sizeof("_POST"), HASH_OF__POST, &retval, sizeof(zval*), NULL);

	return 0;
}

zend_bool PancakeJITFetchFILES(const char *name, uint name_len TSRMLS_DC) {
	zval *files;

	PancakeFetchPOST(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);
	FAST_READ_PROPERTY(files, PANCAKE_GLOBALS(JITGlobalsHTTPRequest), "uploadedFiles", sizeof("uploadedFiles") - 1, HASH_OF_uploadedFiles);
	Z_ADDREF_P(files);
	zend_hash_quick_update(&EG(symbol_table), "_FILES", sizeof("_FILES"), HASH_OF__FILES, &files, sizeof(zval*), NULL);

	return 0;
}

PHP_METHOD(HTTPRequest, getPOSTParams) {
	zval *retval = PancakeFetchPOST(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 1 , 0);
}

zval *PancakeFetchCookies(zval *this_ptr TSRMLS_DC) {
	zval *cookies;
	FAST_READ_PROPERTY(cookies, this_ptr, "cookies", sizeof("cookies") - 1, HASH_OF_cookies);

	if(Z_TYPE_P(cookies) != IS_ARRAY) {
		zval *requestHeaders, **cookie;
		FAST_READ_PROPERTY(requestHeaders, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, HASH_OF_requestHeaders);

		MAKE_STD_ZVAL(cookies);
		array_init_size(cookies, 2);

		if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaders), "cookie", sizeof("cookie"), 229462176616959U, (void**) &cookie) == SUCCESS) {
			cookies = PancakeProcessQueryString(cookies, *cookie, ";");
		}

		PancakeQuickWriteProperty(this_ptr, cookies, "cookies", sizeof("cookies"), HASH_OF_cookies TSRMLS_CC);
		Z_DELREF_P(cookies);
	}

	return cookies;
}

zend_bool PancakeJITFetchCookies(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchCookies(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);

	Z_ADDREF_P(retval);
	zend_hash_quick_update(&EG(symbol_table), "_COOKIE", sizeof("_COOKIE"), HASH_OF__COOKIE, &retval, sizeof(zval*), NULL);

	return 0;
}

PHP_METHOD(HTTPRequest, getCookies) {
	zval *retval = PancakeFetchCookies(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 1 , 0);
}

zval *PancakeFetchSERVER(zval *this_ptr TSRMLS_DC) {
	zval *server, *requestTime, *requestMicrotime, *requestMethod, *protocolVersion, *requestFilePath,
		*originalRequestURI, *requestURI, *vHost, *documentRoot, *remoteIP, *remotePort, *queryString,
		*localIP, *localPort, *requestHeaderArray, **data, *pathInfo, *TLS;

	MAKE_STD_ZVAL(server);
	array_init_size(server, 20); // 17 basic elements + 3 overhead for headers (faster init; low overhead when not needed)

	FAST_READ_PROPERTY(requestTime, this_ptr, "requestTime", sizeof("requestTime") - 1, HASH_OF_requestTime);
	Z_ADDREF_P(requestTime);
	add_assoc_zval_ex(server, "REQUEST_TIME", sizeof("REQUEST_TIME"), requestTime);

	FAST_READ_PROPERTY(requestMicrotime, this_ptr, "requestMicrotime", sizeof("requestMicrotime") - 1, HASH_OF_requestMicrotime);
	Z_ADDREF_P(requestMicrotime);
	add_assoc_zval_ex(server, "REQUEST_TIME_FLOAT", sizeof("REQUEST_TIME_FLOAT"), requestMicrotime);

	// USER

	FAST_READ_PROPERTY(requestMethod, this_ptr, "requestType", sizeof("requestType") -1 , HASH_OF_requestType);
	Z_ADDREF_P(requestMethod);
	add_assoc_zval_ex(server, "REQUEST_METHOD", sizeof("REQUEST_METHOD"), requestMethod);

	FAST_READ_PROPERTY(protocolVersion, this_ptr, "protocolVersion", sizeof("protocolVersion") - 1, HASH_OF_protocolVersion);
	if(EXPECTED(!strcmp(Z_STRVAL_P(protocolVersion), "1.1"))) {
		add_assoc_stringl_ex(server, "SERVER_PROTOCOL", sizeof("SERVER_PROTOCOL"), "HTTP/1.1", 8, 1);
	} else {
		add_assoc_stringl_ex(server, "SERVER_PROTOCOL", sizeof("SERVER_PROTOCOL"), "HTTP/1.0", 8, 1);
	}

	Z_ADDREF_P(PANCAKE_GLOBALS(pancakeVersionString));
	add_assoc_zval_ex(server, "SERVER_SOFTWARE", sizeof("SERVER_SOFTWARE"), PANCAKE_GLOBALS(pancakeVersionString));

	FAST_READ_PROPERTY(requestFilePath, this_ptr, "requestFilePath", sizeof("requestFilePath") - 1, HASH_OF_requestFilePath);
	Z_SET_REFCOUNT_P(requestFilePath, Z_REFCOUNT_P(requestFilePath) + 2);
	add_assoc_zval_ex(server, "PHP_SELF", sizeof("PHP_SELF"), requestFilePath);
	add_assoc_zval_ex(server, "SCRIPT_NAME", sizeof("SCRIPT_NAME"), requestFilePath);

	FAST_READ_PROPERTY(originalRequestURI, this_ptr, "originalRequestURI", sizeof("originalRequestURI") - 1, HASH_OF_originalRequestURI);
	Z_ADDREF_P(originalRequestURI);
	add_assoc_zval_ex(server, "REQUEST_URI", sizeof("REQUEST_URI"), originalRequestURI);

	FAST_READ_PROPERTY(requestURI, this_ptr, "requestURI", sizeof("requestURI") - 1, HASH_OF_requestURI);
	Z_ADDREF_P(requestURI);
	add_assoc_zval_ex(server, "DOCUMENT_URI", sizeof("DOCUMENT_URI"), requestURI);

	FAST_READ_PROPERTY(vHost, this_ptr, "vHost", 5, HASH_OF_vHost);
	FAST_READ_PROPERTY(documentRoot, vHost, "documentRoot", sizeof("documentRoot") - 1, HASH_OF_documentRoot);

	char *fullPath = emalloc(Z_STRLEN_P(documentRoot) + Z_STRLEN_P(requestFilePath) + 1);
	memcpy(fullPath, Z_STRVAL_P(documentRoot), Z_STRLEN_P(documentRoot));
	memcpy(fullPath + Z_STRLEN_P(documentRoot), Z_STRVAL_P(requestFilePath), Z_STRLEN_P(requestFilePath) + 1);

	char *resolvedPath = realpath(fullPath, NULL);
	efree(fullPath);
	add_assoc_string_ex(server, "SCRIPT_FILENAME", sizeof("SCRIPT_FILENAME"), resolvedPath, 1);
	free(resolvedPath); // resolvedPath is OS malloc()ated

	FAST_READ_PROPERTY(remoteIP, this_ptr, "remoteIP", sizeof("remoteIP") - 1, HASH_OF_remoteIP);
	Z_ADDREF_P(remoteIP);
	add_assoc_zval_ex(server, "REMOTE_ADDR", sizeof("REMOTE_ADDR"), remoteIP);

	FAST_READ_PROPERTY(remotePort, this_ptr, "remotePort", sizeof("remotePort") - 1, HASH_OF_remotePort);
	Z_ADDREF_P(remotePort);
	add_assoc_zval_ex(server, "REMOTE_PORT", sizeof("REMOTE_PORT"), remotePort);

	FAST_READ_PROPERTY(queryString, this_ptr, "queryString", sizeof("queryString") - 1, HASH_OF_queryString);
	Z_ADDREF_P(queryString);
	add_assoc_zval_ex(server, "QUERY_STRING", sizeof("QUERY_STRING"), queryString);

	Z_ADDREF_P(documentRoot);
	add_assoc_zval_ex(server, "DOCUMENT_ROOT", sizeof("DOCUMENT_ROOT"), documentRoot);

	FAST_READ_PROPERTY(localIP, this_ptr, "localIP", sizeof("localIP") - 1, HASH_OF_localIP);
	Z_ADDREF_P(localIP);
	add_assoc_zval_ex(server, "SERVER_ADDR", sizeof("SERVER_ADDR"), localIP);

	FAST_READ_PROPERTY(localPort, this_ptr, "localPort", sizeof("localPort") - 1, HASH_OF_localPort);
	Z_ADDREF_P(localPort);
	add_assoc_zval_ex(server, "SERVER_PORT", sizeof("SERVER_PORT"), localPort);

	FAST_READ_PROPERTY(TLS, this_ptr, "TLS", sizeof("TLS") - 1, HASH_OF_TLS);
	if(Z_LVAL_P(TLS)) {
		add_assoc_long_ex(server, "HTTPS", sizeof("HTTPS"), 1);
	}

	FAST_READ_PROPERTY(pathInfo, this_ptr, "pathInfo", sizeof("pathInfo") - 1, HASH_OF_pathInfo);
	if(Z_TYPE_P(pathInfo) == IS_STRING && Z_STRLEN_P(pathInfo)) {
		Z_ADDREF_P(pathInfo);
		add_assoc_zval_ex(server, "PATH_INFO", sizeof("PATH_INFO"), pathInfo);

		char *pathTranslated = emalloc(Z_STRLEN_P(documentRoot) + Z_STRLEN_P(pathInfo) + 1);
		memcpy(pathTranslated, Z_STRVAL_P(documentRoot), Z_STRLEN_P(documentRoot));
		memcpy(pathTranslated + Z_STRLEN_P(documentRoot), Z_STRVAL_P(pathInfo), Z_STRLEN_P(pathInfo) + 1);

		add_assoc_stringl_ex(server, "PATH_TRANSLATED", sizeof("PATH_TRANSLATED"), pathTranslated, Z_STRLEN_P(documentRoot) + Z_STRLEN_P(pathInfo), 0);
	}

	FAST_READ_PROPERTY(requestHeaderArray, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, HASH_OF_requestHeaders);
	char *index;
	int index_len, haveServerName = 0;

	for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(requestHeaderArray));
		zend_hash_get_current_data(Z_ARRVAL_P(requestHeaderArray), (void**) &data) == SUCCESS,
		zend_hash_get_current_key_ex(Z_ARRVAL_P(requestHeaderArray), &index, &index_len, NULL, 0, NULL) == HASH_KEY_IS_STRING;
		zend_hash_move_forward(Z_ARRVAL_P(requestHeaderArray))) {
		index_len--;
		char *index_dupe = estrndup(index, index_len);
		unsigned char *c, *e;

		c = (unsigned char*) index_dupe;
		e = (unsigned char*) c + index_len;

		while (c < e) {
			*c = (*c == '-' ? '_' : toupper(*c));
			c++;
		}

		char *CGIIndex = estrndup("HTTP_", index_len + 5);
		strcat(CGIIndex, index_dupe);
		efree(index_dupe);
		if(!strcmp(CGIIndex, "HTTP_HOST")) {
			Z_ADDREF_PP(data);
			add_assoc_zval_ex(server, "SERVER_NAME", sizeof("SERVER_NAME"), *data);
			haveServerName = 1;
		}
		Z_ADDREF_PP(data);
		add_assoc_zval_ex(server, CGIIndex, strlen(CGIIndex) + 1, *data);
		efree(CGIIndex);
	}

	if(UNEXPECTED(!haveServerName)) {
		zval *listen;

		FAST_READ_PROPERTY(listen, vHost, "listen", sizeof("listen") - 1, HASH_OF_listen);
		zend_hash_index_find(Z_ARRVAL_P(listen), 0, (void**) &data);
		Z_ADDREF_PP(data);
		add_assoc_zval_ex(server, "SERVER_NAME", sizeof("SERVER_NAME"), *data);
	}

	return server;
}

zend_bool PancakeJITFetchSERVER(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchSERVER(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);

	zend_hash_quick_update(&EG(symbol_table), "_SERVER", sizeof("_SERVER"), HASH_OF__SERVER, &retval, sizeof(zval*), NULL);

	return 0;
}

PHP_METHOD(HTTPRequest, createSERVER) {
	zval *retval = PancakeFetchSERVER(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 0 , 1);
}

zend_bool PancakeJITFetchREQUEST(const char *name, uint name_len TSRMLS_DC) {
	zval *REQUEST;
	unsigned char _gpc_flags[3] = {0, 0, 0};
	char *p;

	MAKE_STD_ZVAL(REQUEST);
	array_init_size(REQUEST, 3);

	if (PG(request_order) != NULL) {
		p = PG(request_order);
	} else {
		p = PG(variables_order);
	}

	for (; p && *p; p++) {
		switch (*p) {
			case 'g':
			case 'G':
				if (EXPECTED(!_gpc_flags[0])) {
					zval *GET = PancakeFetchGET(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);
					PancakeAutoglobalMerge(Z_ARRVAL_P(REQUEST), Z_ARRVAL_P(GET) TSRMLS_CC);
					_gpc_flags[0] = 1;
				}
				break;
			case 'p':
			case 'P':
				if (EXPECTED(!_gpc_flags[1])) {
					zval *POST = PancakeFetchPOST(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);
					PancakeAutoglobalMerge(Z_ARRVAL_P(REQUEST), Z_ARRVAL_P(POST) TSRMLS_CC);
					_gpc_flags[1] = 1;
				}
				break;
			case 'c':
			case 'C':
				if (EXPECTED(!_gpc_flags[2])) {
					zval *COOKIE = PancakeFetchCookies(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);
					PancakeAutoglobalMerge(Z_ARRVAL_P(REQUEST), Z_ARRVAL_P(COOKIE) TSRMLS_CC);
					_gpc_flags[2] = 1;
				}
				break;
		}
	}

	zend_hash_quick_update(&EG(symbol_table), "_REQUEST", sizeof("_REQUEST"), HASH_OF__REQUEST, &REQUEST, sizeof(zval*), NULL);

	return 0;
}

zend_bool PancakeJITFetchENV(const char *name, uint name_len TSRMLS_DC) {
	zval *ENV;
	MAKE_STD_ZVAL(ENV);
	array_init(ENV);

	zend_hash_quick_update(&EG(symbol_table), "_ENV", sizeof("_ENV"), HASH_OF__ENV, &ENV, sizeof(zval*), NULL);

	return 0;
}

PHP_METHOD(HTTPRequest, registerJITGlobals) {
	PANCAKE_GLOBALS(JITGlobalsHTTPRequest) = this_ptr;

	zend_activate_auto_globals(TSRMLS_C);
}

PHP_METHOD(HTTPRequest, setCookie) {
	char *name, *value, *path, *domain;
	int name_len, value_len, path_len, domain_len;
	long expire = 0, secure = 0, httpOnly = 0, raw = 0;

	// ($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httpOnly = false, $raw = false)
	if(UNEXPECTED(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s|slsslll", &name, &name_len, &value, &value_len, &expire, &path, &path_len, &domain, &domain_len, &secure, &httpOnly, &raw) == FAILURE)) {
		RETURN_FALSE;
	}

	if(!raw) {
		value = php_url_encode(value, value_len, NULL);
	}

	char *expireString;

	if(expire) {
		expireString = php_format_date("r", 1, expire, 1 TSRMLS_CC);;
	}

	zval *cookie;
	MAKE_STD_ZVAL(cookie);
	Z_TYPE_P(cookie) = IS_STRING;
	Z_STRLEN_P(cookie) = spprintf(&Z_STRVAL_P(cookie), 0, "%s=%s%s%s%s%s%s%s%s%s", name, value,
													expire ? "; Expires=" : "",
													  expire ? expireString : "",
														path_len ? "; Path=" : "",
														  path_len ? path : "",
														    domain_len ? "; Domain=" : "",
														      domain_len ? domain : "",
														    	secure ? "; Secure" : "",
														    	  httpOnly ? "; HttpOnly" : "");

	if(!raw) {
		efree(value);
	}

	if(expire) {
		efree(expireString);
	}

	PancakeSetAnswerHeader(this_ptr, "set-cookie", sizeof("set-cookie"), cookie, 0, 13893642455224896184U TSRMLS_CC);

	RETURN_TRUE;
}
