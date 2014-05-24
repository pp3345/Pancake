
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI_Globals.c                                        */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* See LICENSE file for license information                     */
    /****************************************************************/

#include "PancakeSAPI.h"

/* Copy of php_autoglobal_merge() with small changes since it is not available as a PHP_API */
static void PancakeSAPIAutoglobalMerge(HashTable *dest, HashTable *src TSRMLS_DC) {
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
			PancakeSAPIAutoglobalMerge(Z_ARRVAL_PP(dest_entry), Z_ARRVAL_PP(src_entry) TSRMLS_CC);
		}
		zend_hash_move_forward_ex(src, &pos);
	}
}

static zend_bool PancakeSAPICodeCacheJITFetch(const char *name, uint name_len TSRMLS_DC) {
	// Every superglobal fetched now can not be JIT fetched
	if(!strcmp(name, "_GET")) {
		PANCAKE_SAPI_GLOBALS(JIT_GET) = 0;
	} else if(!strcmp(name, "_SERVER")) {
		PANCAKE_SAPI_GLOBALS(JIT_SERVER) = 0;
	} else if(!strcmp(name, "_COOKIE")) {
		PANCAKE_SAPI_GLOBALS(JIT_COOKIE) = 0;
	} else if(!strcmp(name, "_REQUEST")) {
		PANCAKE_SAPI_GLOBALS(JIT_REQUEST) = 0;
	} else if(!strcmp(name, "_POST")) {
		PANCAKE_SAPI_GLOBALS(JIT_POST) = 0;
	} else if(!strcmp(name, "_FILES")) {
		PANCAKE_SAPI_GLOBALS(JIT_FILES) = 0;
	} else if(!strcmp(name, "_ENV")) {
		PANCAKE_SAPI_GLOBALS(JIT_ENV) = 0;
	}

	return 0;
}

PHP_FUNCTION(SAPICodeCacheJIT) {
	// Kill Zend auto globals HashTable and rebuild it
	zend_hash_destroy(CG(auto_globals));
	zend_hash_init_ex(CG(auto_globals), 9, NULL, NULL, 1, 0);

	zend_register_auto_global(ZEND_STRL("_GET"), 1, (zend_auto_global_callback) PancakeSAPICodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_COOKIE"), 1, (zend_auto_global_callback) PancakeSAPICodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_SERVER"), 1, (zend_auto_global_callback) PancakeSAPICodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_REQUEST"), 1, (zend_auto_global_callback) PancakeSAPICodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_POST"), 1, (zend_auto_global_callback) PancakeSAPICodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_FILES"), 1, (zend_auto_global_callback) PancakeSAPICodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_ENV"), 1, (zend_auto_global_callback) PancakeSAPICodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_SESSION"), 0, (zend_auto_global_callback) PancakeSAPICodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("GLOBALS"), 0, (zend_auto_global_callback) PancakeSAPICodeCacheJITFetch TSRMLS_CC);

	zend_activate_auto_globals(TSRMLS_C);
}

static zend_bool PancakeSAPIJITFetchGLOBALS(const char *name, uint name_len TSRMLS_DC) {
	zval *globals;

	ALLOC_ZVAL(globals);
	Z_SET_REFCOUNT_P(globals, 1);
	Z_SET_ISREF_P(globals);
	Z_TYPE_P(globals) = IS_ARRAY;
	Z_ARRVAL_P(globals) = &EG(symbol_table);

	zend_hash_quick_update(&EG(symbol_table), "GLOBALS", sizeof("GLOBALS"), HASH_OF_GLOBALS, &globals, sizeof(zval*), NULL);

	return 0;
}

static zval *PancakeSAPIFetchSERVER(TSRMLS_D) {
	zval *server, *requestTime, *requestMicrotime, *requestMethod, *protocolVersion, *requestFilePath,
		*originalRequestURI, *requestURI, *remoteIP, *remotePort, *queryString,
		*localIP, *localPort, *requestHeaderArray, **data, *pathInfo, *TLS;

	MAKE_STD_ZVAL(server);
	array_init_size(server, 20); // 17 basic elements + 3 overhead for headers (faster init; low overhead when not needed)

	FAST_READ_PROPERTY(requestTime, PANCAKE_SAPI_GLOBALS(request), "requestTime", sizeof("requestTime") - 1, HASH_OF_requestTime);
	Z_ADDREF_P(requestTime);
	add_assoc_zval_ex(server, "REQUEST_TIME", sizeof("REQUEST_TIME"), requestTime);

	FAST_READ_PROPERTY(requestMicrotime, PANCAKE_SAPI_GLOBALS(request), "requestMicrotime", sizeof("requestMicrotime") - 1, HASH_OF_requestMicrotime);
	Z_ADDREF_P(requestMicrotime);
	add_assoc_zval_ex(server, "REQUEST_TIME_FLOAT", sizeof("REQUEST_TIME_FLOAT"), requestMicrotime);

	// USER

	FAST_READ_PROPERTY(requestMethod, PANCAKE_SAPI_GLOBALS(request), "requestType", sizeof("requestType") -1 , HASH_OF_requestType);
	Z_ADDREF_P(requestMethod);
	add_assoc_zval_ex(server, "REQUEST_METHOD", sizeof("REQUEST_METHOD"), requestMethod);

	FAST_READ_PROPERTY(protocolVersion, PANCAKE_SAPI_GLOBALS(request), "protocolVersion", sizeof("protocolVersion") - 1, HASH_OF_protocolVersion);
	if(EXPECTED(!strcmp(Z_STRVAL_P(protocolVersion), "1.1"))) {
		add_assoc_stringl_ex(server, "SERVER_PROTOCOL", sizeof("SERVER_PROTOCOL"), "HTTP/1.1", 8, 1);
	} else {
		add_assoc_stringl_ex(server, "SERVER_PROTOCOL", sizeof("SERVER_PROTOCOL"), "HTTP/1.0", 8, 1);
	}

	Z_ADDREF_P(PANCAKE_GLOBALS(pancakeVersionString));
	add_assoc_zval_ex(server, "SERVER_SOFTWARE", sizeof("SERVER_SOFTWARE"), PANCAKE_GLOBALS(pancakeVersionString));

	FAST_READ_PROPERTY(requestFilePath, PANCAKE_SAPI_GLOBALS(request), "requestFilePath", sizeof("requestFilePath") - 1, HASH_OF_requestFilePath);
	Z_SET_REFCOUNT_P(requestFilePath, Z_REFCOUNT_P(requestFilePath) + 2);
	add_assoc_zval_ex(server, "PHP_SELF", sizeof("PHP_SELF"), requestFilePath);
	add_assoc_zval_ex(server, "SCRIPT_NAME", sizeof("SCRIPT_NAME"), requestFilePath);

	FAST_READ_PROPERTY(originalRequestURI, PANCAKE_SAPI_GLOBALS(request), "originalRequestURI", sizeof("originalRequestURI") - 1, HASH_OF_originalRequestURI);
	Z_ADDREF_P(originalRequestURI);
	add_assoc_zval_ex(server, "REQUEST_URI", sizeof("REQUEST_URI"), originalRequestURI);

	FAST_READ_PROPERTY(requestURI, PANCAKE_SAPI_GLOBALS(request), "requestURI", sizeof("requestURI") - 1, HASH_OF_requestURI);
	Z_ADDREF_P(requestURI);
	add_assoc_zval_ex(server, "DOCUMENT_URI", sizeof("DOCUMENT_URI"), requestURI);

	char *fullPath = emalloc(Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)) + Z_STRLEN_P(requestFilePath) + 1);
	memcpy(fullPath, Z_STRVAL_P(PANCAKE_SAPI_GLOBALS(documentRoot)), Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)));
	memcpy(fullPath + Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)), Z_STRVAL_P(requestFilePath), Z_STRLEN_P(requestFilePath) + 1);

	char *resolvedPath = realpath(fullPath, NULL);
	efree(fullPath);
	add_assoc_string_ex(server, "SCRIPT_FILENAME", sizeof("SCRIPT_FILENAME"), resolvedPath, 1);
	free(resolvedPath); // resolvedPath is OS malloc()ated

	FAST_READ_PROPERTY(remoteIP, PANCAKE_SAPI_GLOBALS(request), "remoteIP", sizeof("remoteIP") - 1, HASH_OF_remoteIP);
	Z_ADDREF_P(remoteIP);
	add_assoc_zval_ex(server, "REMOTE_ADDR", sizeof("REMOTE_ADDR"), remoteIP);

	FAST_READ_PROPERTY(remotePort, PANCAKE_SAPI_GLOBALS(request), "remotePort", sizeof("remotePort") - 1, HASH_OF_remotePort);
	Z_ADDREF_P(remotePort);
	add_assoc_zval_ex(server, "REMOTE_PORT", sizeof("REMOTE_PORT"), remotePort);

	FAST_READ_PROPERTY(queryString, PANCAKE_SAPI_GLOBALS(request), "queryString", sizeof("queryString") - 1, HASH_OF_queryString);
	Z_ADDREF_P(queryString);
	add_assoc_zval_ex(server, "QUERY_STRING", sizeof("QUERY_STRING"), queryString);

	Z_ADDREF_P(PANCAKE_SAPI_GLOBALS(documentRoot));
	add_assoc_zval_ex(server, "DOCUMENT_ROOT", sizeof("DOCUMENT_ROOT"), PANCAKE_SAPI_GLOBALS(documentRoot));

	FAST_READ_PROPERTY(localIP, PANCAKE_SAPI_GLOBALS(request), "localIP", sizeof("localIP") - 1, HASH_OF_localIP);
	Z_ADDREF_P(localIP);
	add_assoc_zval_ex(server, "SERVER_ADDR", sizeof("SERVER_ADDR"), localIP);

	FAST_READ_PROPERTY(localPort, PANCAKE_SAPI_GLOBALS(request), "localPort", sizeof("localPort") - 1, HASH_OF_localPort);
	Z_ADDREF_P(localPort);
	add_assoc_zval_ex(server, "SERVER_PORT", sizeof("SERVER_PORT"), localPort);

	FAST_READ_PROPERTY(TLS, PANCAKE_SAPI_GLOBALS(request), "TLS", sizeof("TLS") - 1, HASH_OF_TLS);
	if(Z_LVAL_P(TLS)) {
		add_assoc_long_ex(server, "HTTPS", sizeof("HTTPS"), 1);
	}

	FAST_READ_PROPERTY(pathInfo, PANCAKE_SAPI_GLOBALS(request), "pathInfo", sizeof("pathInfo") - 1, HASH_OF_pathInfo);
	if(Z_TYPE_P(pathInfo) == IS_STRING && Z_STRLEN_P(pathInfo)) {
		Z_ADDREF_P(pathInfo);
		add_assoc_zval_ex(server, "PATH_INFO", sizeof("PATH_INFO"), pathInfo);

		char *pathTranslated = emalloc(Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)) + Z_STRLEN_P(pathInfo) + 1);
		memcpy(pathTranslated, Z_STRVAL_P(PANCAKE_SAPI_GLOBALS(documentRoot)), Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)));
		memcpy(pathTranslated + Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)), Z_STRVAL_P(pathInfo), Z_STRLEN_P(pathInfo) + 1);

		add_assoc_stringl_ex(server, "PATH_TRANSLATED", sizeof("PATH_TRANSLATED"), pathTranslated, Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)) + Z_STRLEN_P(pathInfo), 0);
	}

	FAST_READ_PROPERTY(requestHeaderArray, PANCAKE_SAPI_GLOBALS(request), "requestHeaders", sizeof("requestHeaders") - 1, HASH_OF_requestHeaders);
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

		FAST_READ_PROPERTY(listen, PANCAKE_SAPI_GLOBALS(vHost), "listen", sizeof("listen") - 1, HASH_OF_listen);
		zend_hash_index_find(Z_ARRVAL_P(listen), 0, (void**) &data);
		Z_ADDREF_PP(data);
		add_assoc_zval_ex(server, "SERVER_NAME", sizeof("SERVER_NAME"), *data);
	}

	if(PG(http_globals)[TRACK_VARS_SERVER]) {
		zval_ptr_dtor(&PG(http_globals)[TRACK_VARS_SERVER]);
	}

	Z_ADDREF_P(server);
	PG(http_globals)[TRACK_VARS_SERVER] = server;

	return server;
}

static zend_bool PancakeSAPIJITFetchSERVER(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeSAPIFetchSERVER(TSRMLS_C);

	zend_hash_quick_update(&EG(symbol_table), "_SERVER", sizeof("_SERVER"), HASH_OF__SERVER, &retval, sizeof(zval*), NULL);

	return 0;
}

PHP_FUNCTION(SAPIFetchSERVER) {
	zval *retval = PancakeSAPIFetchSERVER(TSRMLS_C);
	RETURN_ZVAL(retval, 0 , 1);
}

static zend_bool PancakeSAPIJITFetchREQUEST(const char *name, uint name_len TSRMLS_DC) {
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
					zval *GET = PancakeFetchGET(PANCAKE_SAPI_GLOBALS(request) TSRMLS_CC);
					PancakeSAPIAutoglobalMerge(Z_ARRVAL_P(REQUEST), Z_ARRVAL_P(GET) TSRMLS_CC);
					_gpc_flags[0] = 1;
				}
				break;
			case 'p':
			case 'P':
				if (EXPECTED(!_gpc_flags[1])) {
					zval *POST = PancakeFetchPOST(PANCAKE_SAPI_GLOBALS(request) TSRMLS_CC);
					PancakeSAPIAutoglobalMerge(Z_ARRVAL_P(REQUEST), Z_ARRVAL_P(POST) TSRMLS_CC);
					_gpc_flags[1] = 1;
				}
				break;
			case 'c':
			case 'C':
				if (EXPECTED(!_gpc_flags[2])) {
					zval *COOKIE = PancakeFetchCookies(PANCAKE_SAPI_GLOBALS(request) TSRMLS_CC);
					PancakeSAPIAutoglobalMerge(Z_ARRVAL_P(REQUEST), Z_ARRVAL_P(COOKIE) TSRMLS_CC);
					_gpc_flags[2] = 1;
				}
				break;
		}
	}

	zend_hash_quick_update(&EG(symbol_table), "_REQUEST", sizeof("_REQUEST"), HASH_OF__REQUEST, &REQUEST, sizeof(zval*), NULL);

	return 0;
}

static zend_bool PancakeSAPIJITFetchENV(const char *name, uint name_len TSRMLS_DC) {
	zval *ENV;
	MAKE_STD_ZVAL(ENV);
	array_init(ENV);

	zend_hash_quick_update(&EG(symbol_table), "_ENV", sizeof("_ENV"), HASH_OF__ENV, &ENV, sizeof(zval*), NULL);

	return 0;
}

static zend_bool PancakeSAPIJITFetchCookies(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchCookies(PANCAKE_SAPI_GLOBALS(request) TSRMLS_CC);

	Z_ADDREF_P(retval);
	zend_hash_quick_update(&EG(symbol_table), "_COOKIE", sizeof("_COOKIE"), HASH_OF__COOKIE, &retval, sizeof(zval*), NULL);

	return 0;
}

static zend_bool PancakeSAPIJITFetchFILES(const char *name, uint name_len TSRMLS_DC) {
	zval *files;

	PancakeFetchPOST(PANCAKE_SAPI_GLOBALS(request) TSRMLS_CC);
	FAST_READ_PROPERTY(files, PANCAKE_SAPI_GLOBALS(request), "uploadedFiles", sizeof("uploadedFiles") - 1, HASH_OF_uploadedFiles);
	Z_ADDREF_P(files);
	zend_hash_quick_update(&EG(symbol_table), "_FILES", sizeof("_FILES"), HASH_OF__FILES, &files, sizeof(zval*), NULL);

	return 0;
}

static zend_bool PancakeSAPIJITFetchPOST(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchPOST(PANCAKE_SAPI_GLOBALS(request) TSRMLS_CC);

	Z_ADDREF_P(retval);
	zend_hash_quick_update(&EG(symbol_table), "_POST", sizeof("_POST"), HASH_OF__POST, &retval, sizeof(zval*), NULL);

	return 0;
}

static zend_bool PancakeSAPIJITFetchGET(const char *name, uint name_len TSRMLS_DC) {
	zval *retval = PancakeFetchGET(PANCAKE_SAPI_GLOBALS(request) TSRMLS_CC);

	Z_ADDREF_P(retval);
	zend_hash_quick_update(&EG(symbol_table), "_GET", sizeof("_GET"), HASH_OF__GET, &retval, sizeof(zval*), NULL);

	return 0;
}

void PancakeSAPIGlobalsPrepare(TSRMLS_D) {
	// Kill Zend auto globals HashTable and rebuild it
	zend_hash_destroy(CG(auto_globals));
	zend_hash_init_ex(CG(auto_globals), 9, NULL, NULL, 1, 0);

	zend_register_auto_global(ZEND_STRL("_COOKIE"), PANCAKE_SAPI_GLOBALS(JIT_COOKIE), PancakeSAPIJITFetchCookies TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_GET"), PANCAKE_SAPI_GLOBALS(JIT_GET), PancakeSAPIJITFetchGET TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_SERVER"), PANCAKE_SAPI_GLOBALS(JIT_SERVER), PancakeSAPIJITFetchSERVER TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_REQUEST"), PANCAKE_SAPI_GLOBALS(JIT_REQUEST), PancakeSAPIJITFetchREQUEST TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_POST"), PANCAKE_SAPI_GLOBALS(JIT_POST), PancakeSAPIJITFetchPOST TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_FILES"), PANCAKE_SAPI_GLOBALS(JIT_FILES), PancakeSAPIJITFetchFILES TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_ENV"), PANCAKE_SAPI_GLOBALS(JIT_ENV), PancakeSAPIJITFetchENV TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("GLOBALS"), 0, PancakeSAPIJITFetchGLOBALS TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_SESSION"), 0, NULL TSRMLS_CC);
}
