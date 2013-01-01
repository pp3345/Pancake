
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake_HTTPRequest.c                                      	*/
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "php_PancakeBase.h"

/* Copy of php_autoglobal_merge() with small changes since it is not available as a PHP_API */
static void PancakeAutoglobalMerge(HashTable *dest, HashTable *src TSRMLS_DC)
{
	zval **src_entry, **dest_entry;
	char *string_key;
	uint string_key_len;
	ulong num_key;
	HashPosition pos;
	int key_type;

	zend_hash_internal_pointer_reset_ex(src, &pos);
	while (zend_hash_get_current_data_ex(src, (void **)&src_entry, &pos) == SUCCESS) {
		key_type = zend_hash_get_current_key_ex(src, &string_key, &string_key_len, &num_key, 0, &pos);
		if (Z_TYPE_PP(src_entry) != IS_ARRAY
			|| (key_type == HASH_KEY_IS_STRING && zend_hash_find(dest, string_key, string_key_len, (void **) &dest_entry) != SUCCESS)
			|| (key_type == HASH_KEY_IS_LONG && zend_hash_index_find(dest, num_key, (void **)&dest_entry) != SUCCESS)
			|| Z_TYPE_PP(dest_entry) != IS_ARRAY
			) {
			Z_ADDREF_PP(src_entry);
			if (key_type == HASH_KEY_IS_STRING) {
				zend_hash_update(dest, string_key, string_key_len, src_entry, sizeof(zval *), NULL);
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
		answerHeaderArray = zend_read_property(HTTPRequest_ce, answerHeaderArray, "answerHeaders", sizeof("answerHeaders") - 1, 0 TSRMLS_CC);
	}

	if(zend_hash_quick_find(Z_ARRVAL_P(answerHeaderArray), name, name_len, h, (void**) &answerHeader) == SUCCESS) {
		if(replace) {
			zend_hash_quick_update(Z_ARRVAL_P(answerHeaderArray), name, name_len, h, (void*) &value, sizeof(zval*), NULL);
			return;
		}

		if(Z_TYPE_PP(answerHeader) == IS_STRING) {
			zval *array;

			MAKE_STD_ZVAL(array);
			array_init(array);

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
	zval *value;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sz|l", &name, &name_len, &value, &replace) == FAILURE) {
		RETURN_FALSE;
	}

	Z_ADDREF_P(value);
	php_strtolower(name, name_len);
	name_len++;

	PancakeSetAnswerHeader(this_ptr, name, name_len, value, replace, zend_inline_hash_func(name, name_len) TSRMLS_CC);
}

char *PancakeBuildAnswerHeaders(zval *answerHeaderArray) {
	zval **data;
	char *index;
	int index_len;
	char *retval = emalloc(sizeof(char));
	size_t retvalSize = 1;
	size_t offset = 0;

	for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(answerHeaderArray));
		zend_hash_get_current_data(Z_ARRVAL_P(answerHeaderArray), (void**) &data) == SUCCESS,
		zend_hash_get_current_key_ex(Z_ARRVAL_P(answerHeaderArray), &index, &index_len, NULL, 0, NULL) == HASH_KEY_IS_STRING;
		zend_hash_move_forward(Z_ARRVAL_P(answerHeaderArray))) {
		// Format index (x-powered-by => X-Powered-By)
		int i;
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

				size_t elementLength = Z_STRLEN_PP(single) + index_len + 3;
				retvalSize += elementLength * sizeof(char);
				retval = erealloc(retval, retvalSize);

				sprintf((char*) (retval + offset), "%s: %s\r\n", index, Z_STRVAL_PP(single));
				offset += elementLength;
			}
		} else {
			convert_to_string(*data);

			size_t elementLength = Z_STRLEN_PP(data) + index_len + 3;
			retvalSize += elementLength * sizeof(char);
			retval = erealloc(retval, retvalSize);

			sprintf((char*) (retval + offset), "%s: %s\r\n", index, Z_STRVAL_PP(data));
			offset += elementLength;
		}
	}

	return retval;
}

PHP_METHOD(HTTPRequest, __construct) {
	zval *remoteIP, *localIP, *remotePort, *localPort;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "zzzz", &remoteIP, &remotePort, &localIP, &localPort) == FAILURE) {
		RETURN_FALSE;
	}

	zend_update_property(HTTPRequest_ce, this_ptr, "remoteIP", sizeof("remoteIP") - 1, remoteIP TSRMLS_CC);
	zend_update_property(HTTPRequest_ce, this_ptr, "remotePort", sizeof("remotePort") - 1, remotePort TSRMLS_CC);
	zend_update_property(HTTPRequest_ce, this_ptr, "localIP", sizeof("localIP") - 1, localIP TSRMLS_CC);
	zend_update_property(HTTPRequest_ce, this_ptr, "localPort", sizeof("localPort") - 1, localPort TSRMLS_CC);

	/* Set default virtual host */
	Z_ADDREF_P(PANCAKE_GLOBALS(defaultVirtualHost));
	zend_update_property(HTTPRequest_ce, this_ptr, "vHost", sizeof("vHost") - 1, PANCAKE_GLOBALS(defaultVirtualHost) TSRMLS_CC);

	/* Set answer header array to empty array */
	zval *answerHeaderArray;

	MAKE_STD_ZVAL(answerHeaderArray);
	array_init(answerHeaderArray);
	zend_update_property(HTTPRequest_ce, this_ptr, "answerHeaders", sizeof("answerHeaders") - 1, answerHeaderArray TSRMLS_CC);
}

PHP_METHOD(HTTPRequest, init) {
	char *requestHeader;
	int requestHeader_len;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &requestHeader, &requestHeader_len) == FAILURE) {
		RETURN_FALSE;
	}

	char *requestLine, *ptr1, *ptr2, *ptr3;
	int i;
	char **firstLine = ecalloc(3, sizeof(char*));
	char *requestHeader_dupe = estrndup(requestHeader, requestHeader_len);

	requestLine = estrdup(strtok_r(requestHeader_dupe, "\r\n", &ptr1));

	zend_update_property_string(HTTPRequest_ce, this_ptr, "requestLine", sizeof("requestLine") - 1, requestLine TSRMLS_CC);

	for(i = 0;i < 3;i++) {
		firstLine[i] = strtok_r(i ? NULL : requestLine, " ", &ptr2);
		if(firstLine[i] == NULL) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("Bad request line", 400, requestHeader, requestHeader_len);
			efree(requestHeader_dupe);
			efree(firstLine);
			efree(requestLine);
			return;
		}
	}

	if(!strcmp(firstLine[2], "HTTP/1.1"))
		zend_update_property_stringl(HTTPRequest_ce, this_ptr, "protocolVersion", sizeof("protocolVersion") - 1, "1.1", 3 TSRMLS_CC);
	else if(strcmp(firstLine[2], "HTTP/1.0")) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("Unsupported protocol", 400, requestHeader, requestHeader_len);
		efree(requestHeader_dupe);
		efree(firstLine);
		efree(requestLine);
		return;
	}

	if(strcmp(firstLine[0], "GET")
	&& strcmp(firstLine[0], "POST")
	&& strcmp(firstLine[0], "HEAD")
	&& strcmp(firstLine[0], "TRACE")
	&& strcmp(firstLine[0], "OPTIONS")) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("Unknown request method", 501, requestHeader, requestHeader_len);
		efree(requestHeader_dupe);
		efree(firstLine);
		efree(requestLine);
		return;
	}

	if(strcmp(firstLine[0], "GET")) {
		// GET is the default value
		zend_update_property_string(HTTPRequest_ce, this_ptr, "requestType", sizeof("requestType") - 1, firstLine[0] TSRMLS_CC);
	}

	if((!strcmp(firstLine[0], "HEAD") && PANCAKE_GLOBALS(allowHEAD) == 0)
	|| (!strcmp(firstLine[0], "TRACE") && PANCAKE_GLOBALS(allowTRACE) == 0)
	|| (!strcmp(firstLine[0], "OPTIONS") && PANCAKE_GLOBALS(allowOPTIONS) == 0)) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("Disallowed request method", 405, requestHeader, requestHeader_len);
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
	array_init(headerArray);

	char *header;

	for(header = strtok_r(NULL, "\r\n", &ptr1);
		header != NULL;
		header = strtok_r(NULL, "\r\n", &ptr1)) {

		headerValue = strchr(header, ':');

		if(headerValue == NULL)
			continue;

		headerName = header;
		headerName[headerValue - headerName] = '\0';
		headerValue++;

		php_strtolower(headerName, strlen(headerName));
		LEFT_TRIM(headerValue);

		if(!strcmp(headerName, "content-length")) {
			haveContentLength = 1;
			contentLength = atol(headerValue);
		}

		if(!strcmp(headerName, "host")) {
			host = estrdup(headerValue);
		}

		if(!strcmp(headerName, "accept-encoding")) {
			char *ptr4;
			char *acceptedCompression;
			zval *acceptedCompressions;

			acceptedCompression = strtok_r(headerValue, ",", &ptr4);

			MAKE_STD_ZVAL(acceptedCompressions);
			array_init(acceptedCompressions);

			while(acceptedCompression != NULL) {
				php_strtolower(acceptedCompression, strlen(acceptedCompression));
				LEFT_TRIM(acceptedCompression);
				add_next_index_string(acceptedCompressions, acceptedCompression, 1);

				if(acceptedCompression == "gzip")
					acceptGZIP = 1;

				acceptedCompression = strtok_r(NULL, ",", &ptr4);
			}

			zend_update_property(HTTPRequest_ce, this_ptr, "acceptedCompressions", sizeof("acceptedCompressions") - 1, acceptedCompressions TSRMLS_CC);
		}

		if(!strcmp(headerName, "authorization")) {
			authorization = headerValue;
		}

		if(!strcmp(headerName, "if-unmodified-since")) {
			if_unmodified_since = headerValue;
		}

		add_assoc_string_ex(headerArray, headerName, strlen(headerName) + 1, headerValue, 1);
	}

	zend_update_property(HTTPRequest_ce, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, headerArray TSRMLS_CC);

	efree(requestHeader_dupe);

	zval **vHost = &PANCAKE_GLOBALS(defaultVirtualHost);

	if(host == NULL) {
		if(!strcmp(firstLine[2], "HTTP/1.1")) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("Missing required header: Host", 400, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(requestLine);
			return;
		} else {
			zval *listen = zend_read_property(HTTPRequest_ce, PANCAKE_GLOBALS(defaultVirtualHost), "listen", sizeof("listen") - 1, 1 TSRMLS_CC);
			zval **hostZval;
			zend_hash_index_find(Z_ARRVAL_P(listen), 0, (void**) &hostZval);
			host = estrndup(Z_STRVAL_PP(hostZval), Z_STRLEN_PP(hostZval));
		}
	} else if(zend_hash_find(Z_ARRVAL_P(PANCAKE_GLOBALS(virtualHostArray)), host, strlen(host) + 1, (void**) &vHost) == SUCCESS && *vHost != PANCAKE_GLOBALS(defaultVirtualHost)) {
		Z_ADDREF_PP(vHost);
		zend_update_property(HTTPRequest_ce, this_ptr, "vHost", sizeof("vHost") - 1, *vHost TSRMLS_CC);
	}

	if(!strcmp(firstLine[0], "POST")) {
		if(!haveContentLength) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("Your request can't be processed without a given Content-Length", 411, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(host);
			efree(requestLine);
			return;
		}

		if(contentLength > PANCAKE_GLOBALS(postMaxSize)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("The uploaded content is too large.", 413, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(host);
			efree(requestLine);
			return;
		}
	}

	/* Enough information for TRACE gathered */
	if(!strcmp(firstLine[0], "TRACE")) {
		zval *contentTypeZval;
		MAKE_STD_ZVAL(contentTypeZval);
		Z_TYPE_P(contentTypeZval) = IS_STRING;
		Z_STRVAL_P(contentTypeZval) = estrndup("message/http", 12);
		Z_STRLEN_P(contentTypeZval) = 12;

		zend_update_property_stringl(HTTPRequest_ce, this_ptr, "answerBody", sizeof("answerBody") - 1, requestHeader, requestHeader_len TSRMLS_CC);
		PancakeSetAnswerHeader(this_ptr, "content-type", sizeof("content-type"), contentTypeZval, 1, 14553278787112811407 TSRMLS_CC);
		efree(firstLine);
		efree(host);
		efree(requestLine);
		return;
	}

	char *documentRoot = Z_STRVAL_P(zend_read_property(HTTPRequest_ce, *vHost, "documentRoot", sizeof("documentRoot") - 1, 0 TSRMLS_CC));
	char *filePath;
	int filePath_len;
	spprintf(&filePath, 0, "%s%s", documentRoot, firstLine[1]);

	zend_update_property_string(HTTPRequest_ce, this_ptr, "originalRequestURI", sizeof("originalRequestURI") - 1, firstLine[1] TSRMLS_CC);

	/* Apply rewrite rules */
	zval *rewriteRules = zend_read_property(HTTPRequest_ce, *vHost, "rewriteRules", sizeof("rewriteRules") - 1, 0 TSRMLS_CC);

	if(Z_TYPE_P(rewriteRules) == IS_ARRAY) {
		zval **rewriteRule;

		for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(rewriteRules));
			zend_hash_get_current_data(Z_ARRVAL_P(rewriteRules), (void**) &rewriteRule) == SUCCESS;
			zend_hash_move_forward(Z_ARRVAL_P(rewriteRules))) {
			zval *value;

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "if", sizeof("if"), 193494708, (void**) &value) == SUCCESS) {
				pcre_cache_entry *pcre;
				zval *pcre_retval;

				if((pcre = pcre_get_compiled_regex_cache(Z_STRVAL_P(value), Z_STRLEN_P(value) TSRMLS_CC)) == NULL) {
					continue;
				}

				php_pcre_match_impl(pcre, firstLine[1], sizeof(firstLine[1]) - 1,  pcre_retval, NULL, 0, 0, 0, 0 TSRMLS_CC);

				if(Z_TYPE_P(pcre_retval) != IS_LONG
				|| Z_LVAL_P(pcre_retval) == 0) {
					continue;
				}
			}

			if(		(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "location", sizeof("location"), 249896952137776350, (void**) &value) == SUCCESS
					&& strncmp(Z_STRVAL_P(value), firstLine[1], Z_STRLEN_P(value)) != 0)
			||		(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "precondition", sizeof("precondition"), 17926165567001274195, (void**) &value) == SUCCESS
					&& (	Z_TYPE_P(value) != IS_LONG
						||	(	Z_LVAL_P(value) == 404
							&&	virtual_access(filePath, F_OK TSRMLS_CC) == 0)
						||	(	Z_LVAL_P(value) == 403
							&&	(	virtual_access(filePath, F_OK TSRMLS_CC) == -1
								||	virtual_access(filePath, R_OK TSRMLS_CC) == 0))))) {
				continue;
			}

			zval *value2;

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "pattern", sizeof("pattern"), 7572787993791075, (void**) &value) == SUCCESS
			&& zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "replace", sizeof("replace"), 7572878230359585, (void**) &value2) == SUCCESS) {
				char *result;

				result = php_pcre_replace(Z_STRVAL_P(value), Z_STRLEN_P(value), firstLine[1], sizeof(firstLine[1]) - 1, value2, 0, NULL, -1, NULL);

				if(result != NULL) {
					*firstLine[1] = *result;
				}
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "exception", sizeof("exception"), 8246287202855534580, (void**) &value) == SUCCESS
			&& Z_TYPE_P(value) == IS_LONG) {
				if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "exceptionmessage", sizeof("exceptionmessage") - 1, 14507601710368331673, (void**) &value2) == SUCCESS) {
					PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL(Z_STRVAL_P(value2), Z_STRLEN_P(value2), Z_LVAL_P(value), requestHeader, requestHeader_len);
				} else {
					PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("The server was unable to process your request", Z_LVAL_P(value), requestHeader, requestHeader_len);
				}

				efree(filePath);
				efree(firstLine);
				efree(host);
				efree(requestLine);
				return;
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "destination", sizeof("destination"), 15010265353095908391, (void**) &value) == SUCCESS) {
				PancakeSetAnswerHeader(this_ptr, "location", sizeof("location"), value, 1, 249896952137776350);
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Redirecting...", 301);
				efree(filePath);
				efree(firstLine);
				efree(host);
				efree(requestLine);
				return;
			}

			if(zend_hash_quick_find(Z_ARRVAL_PP(rewriteRule), "pathinfo", sizeof("pathinfo"), 249902003330075646, (void**) &value) == SUCCESS) {
				pcre_cache_entry *pcre;
				zval *pcre_retval, *matches;

				if((pcre = pcre_get_compiled_regex_cache(Z_STRVAL_P(value), Z_STRLEN_P(value) TSRMLS_CC)) == NULL) {
					continue;
				}

				MAKE_STD_ZVAL(matches);

				php_pcre_match_impl(pcre, firstLine[1], sizeof(firstLine[1]) - 1,  pcre_retval, matches, 0, 0, 0, 0 TSRMLS_CC);

				if(Z_TYPE_P(matches) == IS_ARRAY) {
					zval **match;

					if(zend_hash_index_find(Z_ARRVAL_P(matches), 2, (void**) &match) == SUCCESS) {
						zend_update_property(HTTPRequest_ce, this_ptr, "pathInfo", sizeof("pathInfo") - 1, *match TSRMLS_CC);
					}

					zend_hash_index_find(Z_ARRVAL_P(matches), 1, (void**) &match);

					firstLine[1] = Z_STRVAL_PP(match);
				}
			}
		}
	}

	efree(filePath);

	zend_update_property_string(HTTPRequest_ce, this_ptr, "requestURI", sizeof("requestURI") - 1, firstLine[1] TSRMLS_CC);

	char *uriptr, *requestFilePath, *queryString;

	requestFilePath = strtok_r(firstLine[1], "?", &uriptr);
	queryString = strtok_r(NULL, "?", &uriptr);

	if(!strncasecmp("http://", requestFilePath, 7)) {
		requestFilePath = &requestFilePath[7];
		requestFilePath =  strchr(requestFilePath, '/');
	}

	if(requestFilePath[0] != '/') {
		char *requestFilePath_c = estrdup(requestFilePath);
		sprintf(requestFilePath, "/%s", requestFilePath_c);
		efree(requestFilePath_c);
	}

	if(strstr(requestFilePath, "../") != NULL) {
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("You are not allowed to access the requested file", 403, requestHeader, requestHeader_len);
		efree(host);
		efree(firstLine);
		efree(requestLine);
		return;
	}

	zval *mimeType = NULL;

	if(Z_TYPE_P(zend_read_property(HTTPRequest_ce, *vHost, "AJP13", sizeof("AJP13") - 1, 1 TSRMLS_CC)) != IS_OBJECT) {
		struct stat st;

		spprintf(&filePath, 0, "%s%s", documentRoot, requestFilePath);

		if(!stat(filePath, &st) && S_ISDIR(st.st_mode)) {
			if(requestFilePath[strlen(requestFilePath) - 1] !=  '/') {
				zval *redirectValue;

				MAKE_STD_ZVAL(redirectValue);
				redirectValue->type = IS_STRING;
				spprintf(&redirectValue->value.str.val, 0, "http://%s%s/?%s", host, requestFilePath, queryString ? queryString : "");
				redirectValue->value.str.len = strlen(redirectValue->value.str.val);

				PancakeSetAnswerHeader(this_ptr, "location", sizeof("location"), redirectValue, 1, 249896952137776350);
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER("Redirecting...", 301);
				efree(filePath);
				efree(host);
				efree(firstLine);
				efree(requestLine);
				return;
			}

			efree(host);

			zval *indexFiles = zend_read_property(HTTPRequest_ce, *vHost, "indexFiles", sizeof("indexFiles") - 1, 1 TSRMLS_CC);

			if(Z_TYPE_P(indexFiles) == IS_ARRAY) {
				zval **indexFile;

				for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(indexFiles));
					zend_hash_get_current_data(Z_ARRVAL_P(indexFiles), (void**) &indexFile) == SUCCESS;
					zend_hash_move_forward(Z_ARRVAL_P(indexFiles))) {
					if(Z_TYPE_PP(indexFile) != IS_STRING)
						continue;

					efree(filePath);
					spprintf(&filePath, 0, "%s%s%s", documentRoot, requestFilePath, Z_STRVAL_PP(indexFile));
					if(!virtual_access(filePath, F_OK | R_OK)) {
						strcat(requestFilePath, Z_STRVAL_PP(indexFile));
						goto checkRead;
					}
				}
			}

			zval *allowDirectoryListings = zend_read_property(HTTPRequest_ce, *vHost, "allowDirectoryListings", sizeof("allowDirectoryListings") - 1, 1 TSRMLS_CC);

			if(Z_TYPE_P(allowDirectoryListings) > IS_BOOL || Z_LVAL_P(allowDirectoryListings) == 0) {
				PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("You're not allowed to view the listing of the requested directory", 403, requestHeader, requestHeader_len);
				efree(filePath);
				efree(firstLine);
				efree(requestLine);
				return;
			}
		// end is_dir
		} else if(acceptGZIP == 1) {
			efree(host);

			zval *allowGZIPStatic = zend_read_property(HTTPRequest_ce, *vHost, "gzipStatic", sizeof("gzipStatic") - 1, 1 TSRMLS_CC);

			if(Z_TYPE_P(allowGZIPStatic) <= IS_BOOL && Z_LVAL_P(allowGZIPStatic) > 0) {
				efree(filePath);
				filePath_len = spprintf(&filePath, 0, "%s%s.gz", documentRoot, requestFilePath);
				if(!virtual_access(filePath, F_OK | R_OK)) {
					mimeType = PancakeMIMEType(filePath, filePath_len TSRMLS_CC);
					requestFilePath = filePath;

					zval *gzipStr;
					MAKE_STD_ZVAL(gzipStr);
					gzipStr->type = IS_STRING;
					gzipStr->value.str.val = "gzip";
					gzipStr->value.str.len = 4;

					PancakeSetAnswerHeader(this_ptr, "content-encoding", sizeof("content-encoding"), gzipStr, 1, zend_inline_hash_func(filePath, strlen(filePath)));
				}
			}
		} else {
			efree(host);
		}

		checkRead:

		efree(filePath);

		spprintf(&filePath, 0, "%s%s", documentRoot, requestFilePath);

		if(virtual_access(filePath, F_OK TSRMLS_CC)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("File does not exist", 404, requestHeader, requestHeader_len);
			efree(filePath);
			efree(firstLine);
			efree(requestLine);
			return;
		}

		if(virtual_access(filePath, R_OK TSRMLS_CC)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("You're not allowed to access the requested file", 403, requestHeader, requestHeader_len);
			efree(filePath);
			efree(firstLine);
			efree(requestLine);
			return;
		}

		efree(filePath);

		if(if_unmodified_since && st.st_mtime != php_parse_date(if_unmodified_since, NULL)) {
			PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("File was modified since requested time.", 412, requestHeader, requestHeader_len);
			efree(firstLine);
			efree(requestLine);
			return;
		}
	}

	zval *callArray, authData, *arg;

	MAKE_STD_ZVAL(callArray);
	array_init(callArray);
	add_next_index_zval(callArray, *vHost);
	add_next_index_string(callArray, "requiresAuthentication", 1);

	MAKE_STD_ZVAL(arg);
	arg->type = IS_STRING;
	arg->value.str.val = estrdup(requestFilePath);
	arg->value.str.len = strlen(requestFilePath);

	if(call_user_function(CG(function_table), NULL, callArray, &authData, 1, &arg TSRMLS_CC) == FAILURE) {
		zval_dtor(callArray);
		zval_dtor(arg);
		efree(callArray);
		efree(arg);
		efree(firstLine);
		efree(requestLine);

		// Let's throw a 500 for safety
		PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION("An internal server error occured while trying to handle your request", 500, requestHeader, requestHeader_len);
		return;
	}

	zval_dtor(callArray);
	zval_dtor(arg);
	efree(callArray);
	efree(arg);

	if(Z_TYPE(authData) == IS_ARRAY) {
		if(authorization != NULL) {
			strtok(authorization, " ");
			authorization = strtok(NULL, " ");

			if(authorization != NULL) {
				char **userPassword = ecalloc(2, sizeof(char*));

				char decoded = php_base64_decode_ex(authorization, strlen(authorization), NULL, 0);

				userPassword[0] = strtok(&decoded, ":");
				userPassword[1] = strtok(NULL, ":");

				/*if(userPassword[0] != NULL && userPassword[1] != NULL) {
					MAKE_STD_ZVAL(callArray);
					array_init(callArray);
					add_next_index_zval(callArray, *vHost);
					add_next_index_string(callArray, "requiresAuthentication", 1);

					zval *arg2, *arg3;

					MAKE_STD_ZVAL(arg);
					arg->type = IS_STRING;
					arg->value.str.val = requestFilePath;
					arg->value.str.len = strlen(requestFilePath);

					MAKE_STD_ZVAL(arg2);
					arg2->type = IS_STRING;
					arg2->value.str.val = userPassword[0];
					arg2->value.str.len = strlen(userPassword[0]);

					MAKE_STD_ZVAL(arg3);
					arg3->type = IS_STRING;
					arg3->value.str.val = userPassword[1];
					arg3->value.str.len = strlen(userPassword[1]);

					zval **args[3] = {arg, arg2, arg3};
					zval retval;

					if(call_user_function(CG(function_table), NULL, callArray, &retval, 3, &args TSRMLS_CC) == FAILURE) {
						// Continue here
					}
				}*/

				efree(userPassword);
			}
		}

		//PancakeSetAnswerHeader();
	}

	end:;

	if(mimeType == NULL)
		mimeType = PancakeMIMEType(requestFilePath, strlen(requestFilePath) TSRMLS_CC);

	if(queryString == NULL) {
		queryString = "";
	}

	zend_update_property_string(HTTPRequest_ce, this_ptr, "queryString", sizeof("queryString") - 1, queryString TSRMLS_CC);
	zend_update_property_string(HTTPRequest_ce, this_ptr, "requestFilePath", sizeof("requestFilePath") - 1, requestFilePath TSRMLS_CC);
	zend_update_property(HTTPRequest_ce, this_ptr, "mimeType", sizeof("mimeType") - 1, mimeType TSRMLS_CC);
	zend_update_property_long(HTTPRequest_ce, this_ptr, "requestTime", sizeof("requestTime") - 1, time(NULL) TSRMLS_CC);

	efree(firstLine);
	efree(requestLine);

	struct timeval tp = {0};

	gettimeofday(&tp, NULL);

	zend_update_property_double(HTTPRequest_ce, this_ptr, "requestMicrotime", sizeof("requestMicrotime") - 1, (double) (tp.tv_sec + tp.tv_usec / 1000000.00) TSRMLS_CC);
}

PHP_METHOD(HTTPRequest, buildAnswerHeaders) {
	zval *vHost;
	zval *answerHeaderArray = zend_read_property(HTTPRequest_ce, this_ptr, "answerHeaders", sizeof("answerHeaders") - 1, 0 TSRMLS_CC);
	long answerCode = Z_LVAL_P(zend_read_property(HTTPRequest_ce, this_ptr, "answerCode", sizeof("answerCode") - 1, 1 TSRMLS_CC));
	zval **contentLength;
	int answerBody_len = Z_STRLEN_P(zend_read_property(HTTPRequest_ce, this_ptr, "answerBody", sizeof("answerBody") - 1, 1 TSRMLS_CC));
	int quickFindResult;
	zval *protocolVersion = zend_read_property(HTTPRequest_ce, this_ptr, "protocolVersion", sizeof("protocolVersion") - 1, 0 TSRMLS_CC);

	if((quickFindResult = zend_hash_quick_find(Z_ARRVAL_P(answerHeaderArray), "content-length", sizeof("content-length"), 2767439838230162255, (void**) &contentLength)) == FAILURE
	|| !Z_LVAL_PP(contentLength)) {
		if(quickFindResult == FAILURE) {
			zval *contentLengthM;
			MAKE_STD_ZVAL(contentLengthM);
			contentLength = &contentLengthM;
		}
		Z_TYPE_PP(contentLength) = IS_LONG;
		Z_LVAL_PP(contentLength) = answerBody_len;

		PancakeSetAnswerHeader(answerHeaderArray, "content-length", sizeof("content-length"), *contentLength, 1, 2767439838230162255);
	}

	if(answerCode < 100 || answerCode > 599) {
		if(Z_LVAL_PP(contentLength) == 0) {
			zval *vHost = zend_read_property(HTTPRequest_ce, this_ptr, "vHost", sizeof("vHost") - 1, 1 TSRMLS_CC);
			zval *onEmptyPage204 = zend_read_property(HTTPRequest_ce, vHost, "onEmptyPage204", sizeof("onEmptyPage204") - 1, 1 TSRMLS_CC);
			if(Z_LVAL_P(onEmptyPage204)) {
				answerCode = 204;
			} else {
				answerCode = 200;
			}
		} else {
			answerCode = 200;
		}
	}

	if(answerCode >= 200 && answerCode < 400) {
		zval *requestHeaderArray = zend_read_property(HTTPRequest_ce, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, 1 TSRMLS_CC);
		zval **connection;
		if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaderArray), "connection", sizeof("connection"), 13869595640170944373, (void**) &connection) == SUCCESS
		&& !strcasecmp(Z_STRVAL_PP(connection), "keep-alive")) {
			// We can simply set the zval of the request header as zval for the answer header as they both contain "keep-alive"
			PancakeSetAnswerHeader(answerHeaderArray, "connection", sizeof("connection"), *connection, 1, 13869595640170944373 TSRMLS_CC);
		} else {
			zval *connectionAnswer;

			MAKE_STD_ZVAL(connectionAnswer);
			connectionAnswer->type = IS_STRING;
			connectionAnswer->value.str.val = estrdup("close");
			connectionAnswer->value.str.len = strlen("close");

			PancakeSetAnswerHeader(answerHeaderArray, "connection", sizeof("connection"), connectionAnswer, 1, 13869595640170944373 TSRMLS_CC);
		}
	}

	if(PANCAKE_GLOBALS(exposePancake)) {
		Z_ADDREF_P(PANCAKE_GLOBALS(pancakeVersionString));
		PancakeSetAnswerHeader(answerHeaderArray, "server", sizeof("server"), PANCAKE_GLOBALS(pancakeVersionString), 1, 229482452699676 TSRMLS_CC);
	}

	if(Z_LVAL_PP(contentLength)
	&& !zend_hash_quick_exists(Z_ARRVAL_P(answerHeaderArray), "content-type", sizeof("content-type"), 14553278787112811407)) {
		Z_ADDREF_P(PANCAKE_GLOBALS(defaultContentType));
		PancakeSetAnswerHeader(answerHeaderArray, "content-type", sizeof("content-type"), PANCAKE_GLOBALS(defaultContentType), 1, 14553278787112811407 TSRMLS_CC);
	}

	if(!zend_hash_quick_exists(Z_ARRVAL_P(answerHeaderArray), "date", sizeof("date"), 210709757379)) {
		char *date = php_format_date("r", 1, time(NULL), 1 TSRMLS_CC);

		zval *dateZval;
		MAKE_STD_ZVAL(dateZval);
		Z_TYPE_P(dateZval) = IS_STRING;
		Z_STRVAL_P(dateZval) = date;
		Z_STRLEN_P(dateZval) = strlen(date);

		PancakeSetAnswerHeader(answerHeaderArray, "date", sizeof("date"), dateZval, 1, 210709757379 TSRMLS_CC);
	}

	zend_update_property_long(HTTPRequest_ce, this_ptr, "answerCode", sizeof("answerCode") - 1, answerCode TSRMLS_CC);

	char *returnValue;
	char *answerCodeString;
	int returnValue_len;
	char *answerHeaders = PancakeBuildAnswerHeaders(answerHeaderArray);

	PANCAKE_ANSWER_CODE_STRING(answerCodeString, answerCode);

	returnValue_len = spprintf(&returnValue, 0, "HTTP/%s %lu %s\r\n%s\r\n", Z_STRVAL_P(protocolVersion), answerCode, answerCodeString, answerHeaders);
	efree(answerHeaders);

	// Another request served by Pancake.
	// Let's deliver the result to the client
	RETURN_STRINGL(returnValue, returnValue_len, 0);
}

PHP_METHOD(HTTPRequest, invalidRequest) {
	zval *exception;
	zval *vHost;
	zval *exceptionPageHandler;
	zval *mimeType;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "O", &exception, invalidHTTPRequestException_ce) == FAILURE) {
		RETURN_FALSE;
	}

	zval *answerCode = zend_read_property(invalidHTTPRequestException_ce, exception, "code", sizeof("code") - 1, 0 TSRMLS_CC);
	zend_update_property(HTTPRequest_ce, this_ptr, "answerCode", sizeof("answerCode") - 1, answerCode TSRMLS_CC);

	vHost = zend_read_property(HTTPRequest_ce, this_ptr, "vHost", sizeof("vHost") - 1, 0 TSRMLS_CC);
	exceptionPageHandler = zend_read_property(HTTPRequest_ce, vHost, "exceptionPageHandler", sizeof("exceptionPageHandler") - 1, 0 TSRMLS_CC);

	mimeType = PancakeMIMEType(Z_STRVAL_P(exceptionPageHandler), Z_STRLEN_P(exceptionPageHandler) TSRMLS_CC);
	Z_ADDREF_P(mimeType);
	PancakeSetAnswerHeader(this_ptr, "content-type", sizeof("content-type"), mimeType, 1, 14553278787112811407 TSRMLS_CC);

	FILE *handle;
	char *contents;
	long len;
	int useDefaultHandler = 0;
	zval *output;
	MAKE_STD_ZVAL(output);

	if(!virtual_access(Z_STRVAL_P(exceptionPageHandler), F_OK | R_OK TSRMLS_CC)) {
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
		if(php_output_start_user(NULL, 0, PHP_OUTPUT_HANDLER_STDFLAGS TSRMLS_CC) == FAILURE) {
			RETURN_FALSE;
		}

		char *eval = "?>";

		if(!strncmp(contents, "<?php", 5)) {
			eval = &contents[5];
		} else {
			strcat(eval, contents);
		}

		char *description = zend_make_compiled_string_description(useDefaultHandler ? "Pancake Exception Page Handler" : Z_STRVAL_P(exceptionPageHandler) TSRMLS_CC);

		ZEND_SET_SYMBOL_WITH_LENGTH(EG(active_symbol_table), "exception", sizeof("exception"), exception, 2, 0); /// ! refcount?

		if(zend_eval_stringl(eval, strlen(eval), NULL, description TSRMLS_CC) == FAILURE) {
			if(useDefaultHandler) {
				zend_error(E_WARNING, "Pancake Default Exception Page Handler execution failed");
			} else {
				efree(description);
				efree(contents);
				goto defaultHandler;
			}
		}

		efree(description);

		php_output_get_contents(output TSRMLS_CC);
		php_output_discard(TSRMLS_C);
	}

	Z_DELREF_P(output);
	zend_update_property(HTTPRequest_ce, this_ptr, "answerBody", sizeof("answerBody") - 1, output TSRMLS_CC);

	efree(contents);
}

PHP_METHOD(HTTPRequest, getAnswerCodeString) {
	long answerCode;
	char *answerCodeString;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &answerCode) == FAILURE) {
		RETURN_FALSE;
	}

	PANCAKE_ANSWER_CODE_STRING(answerCodeString, answerCode);

	RETURN_STRING(answerCodeString, 1);
}

zval *PancakeRecursiveResolveParameter(char *part, zval *value) {
	char *begin, *end;

	if((begin = strchr(part, '[')) && (end = strchr(part, ']'))) {
		char *name = begin + 1;
		zval *array;
		*end = '\0';

		MAKE_STD_ZVAL(array);
		array_init(array);

		zval *nvalue = PancakeRecursiveResolveParameter(end + 1, value);

		if(!strlen(name)) {
			add_index_zval(array, 0, nvalue);
		} else {
			add_assoc_zval(array, name, nvalue);
		}

		return array;
	}

	return value;
}

zval *PancakeProcessQueryString(zval *destination, zval *queryString, const char *delimiter) {
	char *queryString_c = estrndup(Z_STRVAL_P(queryString), Z_STRLEN_P(queryString));
	char *part, *ptr1;

	part = strtok_r(queryString_c, delimiter, &ptr1);

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
			Z_TYPE_P(zvalue) = IS_NULL;
		}

		if(!strlen(part) && !Z_STRLEN_P(zvalue)) {
			zval_dtor(zvalue);
			efree(zvalue);
			continue;
		}

		php_url_decode(part, strlen(part));

		zvalue = PancakeRecursiveResolveParameter(part, zvalue);

		char *pos = strchr(part, '[');
		if(pos != NULL) {
			*pos = '\0';
		}

		if(Z_TYPE_P(zvalue) == IS_ARRAY) {
			zval *array;
			MAKE_STD_ZVAL(array);
			array_init(array);

			add_assoc_zval(array, part, zvalue);
			php_array_merge(Z_ARRVAL_P(destination), Z_ARRVAL_P(array), 1 TSRMLS_CC);
			zval_dtor(array);
			efree(array);
		} else {
			add_assoc_zval(destination, part, zvalue);
		}
	} while((part = strtok_r(NULL, delimiter, &ptr1)) != NULL);

	efree(queryString_c);

	return destination;
}

zval *PancakeFetchGET(zval *this_ptr TSRMLS_DC) {
	zval *queryString = zend_read_property(HTTPRequest_ce, this_ptr, "queryString", sizeof("queryString") - 1, 1 TSRMLS_CC);
	zval *return_value;
	MAKE_STD_ZVAL(return_value);

	if(Z_STRLEN_P(queryString)) {
		zval *GETParameters = zend_read_property(HTTPRequest_ce, this_ptr, "GETParameters", sizeof("GETParameters") - 1, 1 TSRMLS_CC);

		if(Z_TYPE_P(GETParameters) != IS_ARRAY) {
			MAKE_STD_ZVAL(GETParameters);
			array_init(GETParameters);

			GETParameters = PancakeProcessQueryString(GETParameters, queryString, "&");

			zend_update_property(HTTPRequest_ce, this_ptr, "GETParameters", sizeof("GETParameters") - 1, GETParameters TSRMLS_CC);
		}

		RETVAL_ZVAL(GETParameters, 1, 0);
		return return_value;
	}

	zval *array;
	MAKE_STD_ZVAL(array);
	array_init(array);
	RETVAL_ZVAL(array, 0, 1);
	return return_value;
}

zend_bool PancakeJITFetchGET(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchGET(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);
	zend_hash_update(&EG(symbol_table), name, name_len + 1, &retval, sizeof(zval*), NULL);
	Z_ADDREF_P(retval);

	return 0;
}

PHP_METHOD(HTTPRequest, getGETParams) {
	zval *retval = PancakeFetchGET(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 0 , 1);
}

zval *PancakeFetchPOST(zval *this_ptr TSRMLS_DC) {
	zval *POSTParameters = zend_read_property(HTTPRequest_ce, this_ptr, "POSTParameters", sizeof("POSTParameters") - 1, 1 TSRMLS_CC);
	zval *return_value;
	MAKE_STD_ZVAL(return_value);

	if(Z_TYPE_P(POSTParameters) != IS_ARRAY) {
		zval *rawPOSTData = zend_read_property(HTTPRequest_ce, this_ptr, "rawPOSTData", sizeof("rawPOSTData") - 1, 1 TSRMLS_CC);

		MAKE_STD_ZVAL(POSTParameters);
		array_init(POSTParameters);

		if(Z_STRLEN_P(rawPOSTData)) {
			zval *requestHeaders = zend_read_property(HTTPRequest_ce, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, 1 TSRMLS_CC);
			zval **contentType;

			if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaders), "content-type", sizeof("content-type"), 14553278787112811407, (void**) &contentType) == SUCCESS) {
				if(strcmp(Z_STRVAL_PP(contentType), "application/x-www-form-urlencoded") >= 0) {
					POSTParameters = PancakeProcessQueryString(POSTParameters, rawPOSTData, "&");
				} else if(strcmp(Z_STRVAL_PP(contentType), "multipart/form-data") >= 0) {

				}
			}
		}

		zend_update_property(HTTPRequest_ce, this_ptr, "POSTParameters", sizeof("POSTParameters") - 1, POSTParameters TSRMLS_CC);
	}

	RETVAL_ZVAL(POSTParameters, 1, 0);
	return return_value;
}

zend_bool PancakeJITFetchPOST(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchPOST(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);
	zend_hash_update(&EG(symbol_table), name, name_len + 1, &retval, sizeof(zval*), NULL);
	Z_ADDREF_P(retval);

	return 0;
}

PHP_METHOD(HTTPRequest, getPOSTParams) {
	zval *retval = PancakeFetchPOST(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 0 , 1);
}

zval *PancakeFetchCookies(zval *this_ptr TSRMLS_DC) {
	zval *cookies = zend_read_property(HTTPRequest_ce, this_ptr, "cookies", sizeof("cookies") - 1, 1 TSRMLS_CC);
	zval *return_value;
	MAKE_STD_ZVAL(return_value);

	if(Z_TYPE_P(cookies) != IS_ARRAY) {
		zval *requestHeaders = zend_read_property(HTTPRequest_ce, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, 1 TSRMLS_CC);
		zval **cookie;

		MAKE_STD_ZVAL(cookies);
		array_init(cookies);

		if(zend_hash_quick_find(Z_ARRVAL_P(requestHeaders), "cookie", sizeof("cookie"), 229462176616959, (void**) &cookie) == SUCCESS) {
			cookies = PancakeProcessQueryString(cookies, *cookie, ";");
		}

		zend_update_property(HTTPRequest_ce, this_ptr, "cookies", sizeof("cookies") - 1, cookies TSRMLS_CC);
	}

	RETVAL_ZVAL(cookies, 1, 0);
	return return_value;
}

zend_bool PancakeJITFetchCookies(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchCookies(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);

	zend_hash_update(&EG(symbol_table), name, name_len + 1, &retval, sizeof(zval*), NULL);
	Z_ADDREF_P(retval);

	return 0;
}

PHP_METHOD(HTTPRequest, getCookies) {
	zval *retval = PancakeFetchCookies(this_ptr TSRMLS_CC);
	RETURN_ZVAL(retval, 0 , 1);
}

zend_bool PancakeCreateSERVER(const char *name, uint name_len TSRMLS_DC) {
	zval *this_ptr = PANCAKE_GLOBALS(JITGlobalsHTTPRequest);
	zval *server;

	MAKE_STD_ZVAL(server);
	array_init_size(server, 20); // 17 basic elements + 3 overhead for headers (faster init; low overhead when not needed)

	zval *requestTime = zend_read_property(HTTPRequest_ce, this_ptr, "requestTime", sizeof("requestTime") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "REQUEST_TIME", sizeof("REQUEST_TIME"), requestTime);

	zval *requestMicrotime = zend_read_property(HTTPRequest_ce, this_ptr, "requestMicrotime", sizeof("requestMicrotime") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "REQUEST_TIME_FLOAT", sizeof("REQUEST_TIME_FLOAT"), requestMicrotime);

	// USER

	zval *requestMethod = zend_read_property(HTTPRequest_ce, this_ptr, "requestType", sizeof("requestType") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "REQUEST_METHOD", sizeof("REQUEST_METHOD"), requestMethod);

	zval *protocolVersion = zend_read_property(HTTPRequest_ce, this_ptr, "protocolVersion", sizeof("protocolVersion") - 1, 1 TSRMLS_CC);
	if(!strcmp(Z_STRVAL_P(protocolVersion), "1.1")) {
		add_assoc_stringl_ex(server, "SERVER_PROTOCOL", sizeof("SERVER_PROTOCOL"), "HTTP/1.1", 8, 1);
	} else {
		add_assoc_stringl_ex(server, "SERVER_PROTOCOL", sizeof("SERVER_PROTOCOL"), "HTTP/1.0", 8, 1);
	}

	add_assoc_zval_ex(server, "SERVER_SOFTWARE", sizeof("SERVER_SOFTWARE"), PANCAKE_GLOBALS(pancakeVersionString));

	zval *requestFilePath = zend_read_property(HTTPRequest_ce, this_ptr, "requestFilePath", sizeof("requestFilePath") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "PHP_SELF", sizeof("PHP_SELF"), requestFilePath);
	add_assoc_zval_ex(server, "SCRIPT_NAME", sizeof("SCRIPT_NAME"), requestFilePath);

	zval *originalRequestURI = zend_read_property(HTTPRequest_ce, this_ptr, "originalRequestURI", sizeof("originalRequestURI") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "REQUEST_URI", sizeof("REQUEST_URI"), originalRequestURI);

	zval *requestURI = zend_read_property(HTTPRequest_ce, this_ptr, "requestURI", sizeof("requestURI") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "DOCUMENT_URI", sizeof("DOCUMENT_URI"), requestURI);

	zval *vHost = zend_read_property(HTTPRequest_ce, this_ptr, "vHost", sizeof("vHost") - 1, 1 TSRMLS_CC);
	zval *documentRoot = zend_read_property(HTTPRequest_ce, vHost, "documentRoot", sizeof("documentRoot") -1, 1 TSRMLS_CC);
	char *fullPath = estrndup(Z_STRVAL_P(documentRoot), Z_STRLEN_P(documentRoot) + Z_STRLEN_P(requestFilePath));
	strcat(fullPath, Z_STRVAL_P(requestFilePath));
	char *resolvedPath = realpath(fullPath, NULL);
	efree(fullPath);
	add_assoc_string_ex(server, "SCRIPT_FILENAME", sizeof("SCRIPT_FILENAME"), resolvedPath, 1);
	free(resolvedPath); // resolvedPath is OS malloc()ated

	zval *remoteIP = zend_read_property(HTTPRequest_ce, this_ptr, "remoteIP", sizeof("remoteIP") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "REMOTE_ADDR", sizeof("REMOTE_ADDR"), remoteIP);

	zval *remotePort = zend_read_property(HTTPRequest_ce, this_ptr, "remotePort", sizeof("remotePort") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "REMOTE_PORT", sizeof("REMOTE_PORT"), remotePort);

	zval *queryString = zend_read_property(HTTPRequest_ce, this_ptr, "queryString", sizeof("queryString") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "QUERY_STRING", sizeof("QUERY_STRING"), queryString);

	add_assoc_zval_ex(server, "DOCUMENT_ROOT", sizeof("DOCUMENT_ROOT"), documentRoot);

	zval *localIP = zend_read_property(HTTPRequest_ce, this_ptr, "localIP", sizeof("localIP") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "SERVER_ADDR", sizeof("SERVER_ADDR"), localIP);

	zval *localPort = zend_read_property(HTTPRequest_ce, this_ptr, "localPort", sizeof("localPort") - 1, 1 TSRMLS_CC);
	add_assoc_zval_ex(server, "SERVER_PORT", sizeof("SERVER_PORT"), localPort);

	zval *requestHeaderArray = zend_read_property(HTTPRequest_ce, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, 1 TSRMLS_CC);
	zval **data;
	char *index;
	int index_len, haveServerName = 0;

	for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(requestHeaderArray));
		zend_hash_get_current_data(Z_ARRVAL_P(requestHeaderArray), (void**) &data) == SUCCESS,
		zend_hash_get_current_key_ex(Z_ARRVAL_P(requestHeaderArray), &index, &index_len, NULL, 0, NULL) == HASH_KEY_IS_STRING;
		zend_hash_move_forward(Z_ARRVAL_P(requestHeaderArray))) {
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
		if(!strcmp(CGIIndex, "HTTP_HOST")) {
			add_assoc_zval_ex(server, "SERVER_NAME", sizeof("SERVER_NAME"), *data);
			haveServerName = 1;
		}
		add_assoc_zval_ex(server, CGIIndex, strlen(CGIIndex) + 1, *data);
	}

	if(!haveServerName) {
		zval *listen = zend_read_property(HTTPRequest_ce, vHost, "listen", sizeof("listen") - 1, 1 TSRMLS_CC);
		zend_hash_index_find(Z_ARRVAL_P(listen), 0, (void**) &data);
		add_assoc_zval_ex(server, "SERVER_NAME", sizeof("SERVER_NAME"), *data);
	}

	zend_hash_update(&EG(symbol_table), name, name_len + 1, &server, sizeof(zval*), NULL);
	Z_ADDREF_P(server);

	return 0;
}

zend_bool PancakeJITFetchREQUEST(const char *name, uint name_len TSRMLS_DC) {
	zval *REQUEST;
	unsigned char _gpc_flags[3] = {0, 0, 0};
	char *p;

	MAKE_STD_ZVAL(REQUEST);
	array_init(REQUEST);

	if (PG(request_order) != NULL) {
		p = PG(request_order);
	} else {
		p = PG(variables_order);
	}

	for (; p && *p; p++) {
		switch (*p) {
			case 'g':
			case 'G':
				if (!_gpc_flags[0]) {
					zval *GET = PancakeFetchGET(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);
					PancakeAutoglobalMerge(Z_ARRVAL_P(REQUEST), Z_ARRVAL_P(GET) TSRMLS_CC);
					_gpc_flags[0] = 1;
				}
				break;
			case 'p':
			case 'P':
				if (!_gpc_flags[1]) {
					zval *POST = PancakeFetchPOST(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);
					PancakeAutoglobalMerge(Z_ARRVAL_P(REQUEST), Z_ARRVAL_P(POST) TSRMLS_CC);
					_gpc_flags[1] = 1;
				}
				break;
			case 'c':
			case 'C':
				if (!_gpc_flags[2]) {
					zval *COOKIE = PancakeFetchCookies(PANCAKE_GLOBALS(JITGlobalsHTTPRequest) TSRMLS_CC);
					PancakeAutoglobalMerge(Z_ARRVAL_P(REQUEST), Z_ARRVAL_P(COOKIE) TSRMLS_CC);
					_gpc_flags[2] = 1;
				}
				break;
		}
	}

	zend_hash_update(&EG(symbol_table), name, name_len + 1, &REQUEST, sizeof(zval*), NULL);
	Z_ADDREF_P(REQUEST);

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
	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s|slsslll", &name, &name_len, &value, &value_len, &expire, &path, &path_len, &domain, &domain_len, &secure, &httpOnly, &raw) == FAILURE) {
		RETURN_FALSE;
	}

	if(!raw) {
		value = php_url_encode(value, value_len, &value_len);
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

	PancakeSetAnswerHeader(this_ptr, "set-cookie", sizeof("set-cookie"), cookie, 0, 13893642455224896184 TSRMLS_CC);

	RETURN_TRUE;
}

PHP_METHOD(HTTPRequest, __destruct) {
	/* Free memory */
	zval *requestHeaderArray = zend_read_property(HTTPRequest_ce, this_ptr, "requestHeaders", sizeof("requestHeaders") - 1, 1);
	if(Z_TYPE_P(requestHeaderArray) == IS_ARRAY) {
		zval_dtor(requestHeaderArray);
		efree(requestHeaderArray);
	}

	zval *answerHeaderArray = zend_read_property(HTTPRequest_ce, this_ptr, "answerHeaders", sizeof("answerHeaders") - 1, 1);
	zval_dtor(answerHeaderArray);
	efree(answerHeaderArray);

	zval *acceptedCompressions = zend_read_property(HTTPRequest_ce, this_ptr, "acceptedCompressions", sizeof("acceptedCompressions") - 1, 1);
	if(Z_TYPE_P(acceptedCompressions) == IS_ARRAY) {
		zval_dtor(acceptedCompressions);
		efree(acceptedCompressions);
	}

	zval *GETParameters = zend_read_property(HTTPRequest_ce, this_ptr, "GETParameters", sizeof("GETParameters") - 1, 1);
	if(Z_TYPE_P(GETParameters) == IS_ARRAY) {
		zval_dtor(GETParameters);
		efree(GETParameters);
	}

	zval *cookies = zend_read_property(HTTPRequest_ce, this_ptr, "cookies", sizeof("cookies") - 1, 1);
	if(Z_TYPE_P(cookies) == IS_ARRAY) {
		zval_dtor(cookies);
		efree(cookies);
	}

	zval *POSTParameters = zend_read_property(HTTPRequest_ce, this_ptr, "POSTParameters", sizeof("POSTParameters") - 1, 1);
	if(Z_TYPE_P(POSTParameters) == IS_ARRAY) {
		zval_dtor(POSTParameters);
		efree(POSTParameters);
	}
}
