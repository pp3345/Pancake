
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake_coreFunctions.c                                      */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "Pancake.h"

PANCAKE_API int PancakeLoadFilePointers(TSRMLS_D) {
	int flushedSystemLogStream = 0;
	int flushedErrorLogStream = 0;
	int flushedRequestLogStream = 0;
	if(PANCAKE_GLOBALS(systemLogStream) != NULL) {
		flushedSystemLogStream = 1;
		fclose(PANCAKE_GLOBALS(systemLogStream));
	}
	if(PANCAKE_GLOBALS(errorLogStream) != NULL) {
		flushedErrorLogStream = 1;
		fclose(PANCAKE_GLOBALS(errorLogStream));
	}
	if(PANCAKE_GLOBALS(requestLogStream) != NULL) {
		flushedRequestLogStream = 1;
		fclose(PANCAKE_GLOBALS(requestLogStream));
	}

	zval *array, *arg;

	MAKE_STD_ZVAL(array);
	array_init(array);
	add_next_index_stringl(array, "Pancake\\Config", sizeof("Pancake\\Config") - 1, 1);
	add_next_index_stringl(array, "get", 3, 1);

	int i;
	for(i = 0; i < 3; i++) {
		FILE **stream;

		MAKE_STD_ZVAL(arg);
		Z_TYPE_P(arg) = IS_STRING;

		switch(i) {
			case 0:
				stream = &PANCAKE_GLOBALS(systemLogStream);
				Z_STRLEN_P(arg) = sizeof("main.logging.system") - 1;
				Z_STRVAL_P(arg) = estrndup("main.logging.system", Z_STRLEN_P(arg));
				break;
			case 1:
				stream = &PANCAKE_GLOBALS(errorLogStream);
				Z_STRLEN_P(arg) = sizeof("main.logging.error") - 1;
				Z_STRVAL_P(arg) = estrndup("main.logging.error", Z_STRLEN_P(arg));
				break;
			case 2:
				stream = &PANCAKE_GLOBALS(requestLogStream);
				Z_STRLEN_P(arg) = sizeof("main.logging.request") - 1;
				Z_STRVAL_P(arg) = estrndup("main.logging.request", Z_STRLEN_P(arg));
				break;
		}

		/* Get system log configuration value */
		zval retval;

		if(call_user_function(CG(function_table), NULL, array, &retval, 1, &arg TSRMLS_CC) == FAILURE) {
			zval_ptr_dtor(&array);
			zval_ptr_dtor(&arg);
			return 0;
		}

		if(Z_TYPE(retval) == IS_STRING)
			*stream = fopen(Z_STRVAL(retval), "a+");

		zval_ptr_dtor(&arg);

		if(*stream == NULL) {
			char *errorString = "Couldn\'t open file for logging - Check if it exists and is accessible for Pancake";
			PancakeOutput(&errorString, strlen(errorString), OUTPUT_SYSTEM TSRMLS_CC);
			zval_dtor(&retval);
			efree(errorString);
			return 0;
		} else {
			chmod(Z_STRVAL(retval), S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP | S_IROTH | S_IWOTH);
		}

		zval_dtor(&retval);

		switch(i) {
			case 0:
				if(flushedSystemLogStream) {
					char *string = "Flushed system log stream";
					PancakeOutput(&string, strlen(string), OUTPUT_SYSTEM | OUTPUT_LOG TSRMLS_CC);
					efree(string);
				}
				break;
			case 1:
				if(flushedErrorLogStream) {
					// Emit a dirty error
					zend_error(E_WARNING, "Flushed error log stream");
				}
				break;
			case 2:
				if(flushedRequestLogStream) {
					char *string = "Flushed request log stream";
					PancakeOutput(&string, strlen(string), OUTPUT_REQUEST | OUTPUT_LOG TSRMLS_CC);
					efree(string);
				}
				break;
		}
	}

	zval_ptr_dtor(&array);
	return 1;
}

PHP_FUNCTION(loadFilePointers) {
	PancakeLoadFilePointers(TSRMLS_C);
}

PANCAKE_API int PancakeOutput(char **string, int string_len, long flags TSRMLS_DC) {
	char *outputString;
	zval daemonized;

	if(!flags) {
		flags = OUTPUT_SYSTEM | OUTPUT_LOG;
	}

	if(flags & OUTPUT_DEBUG) {
		zval constant;

		if(!zend_get_constant("pancake\\DEBUG_MODE", sizeof("pancake\\DEBUG_MODE") - 1, &constant TSRMLS_CC)
		|| Z_LVAL(constant) == 0) {
			return 0;
		}
	}

	if(!strlen(PANCAKE_GLOBALS(dateFormat))) {
		/* Get system log configuration value */
		zval *array, retval, *arg;

		MAKE_STD_ZVAL(array);
		array_init(array);
		add_next_index_stringl(array, "Pancake\\Config", sizeof("Pancake\\Config") - 1, 1);
		add_next_index_stringl(array, "get", 3, 1);

		MAKE_STD_ZVAL(arg);
		Z_TYPE_P(arg) = IS_STRING;
		Z_STRLEN_P(arg) = sizeof("main.dateformat") - 1;
		Z_STRVAL_P(arg) = estrndup("main.dateformat", sizeof("main.dateformat") - 1);

		if(call_user_function(CG(function_table), NULL, array, &retval, 1, &arg TSRMLS_CC) == FAILURE) {
			zval_ptr_dtor(&array);
			zval_ptr_dtor(&arg);
			return 0;
		}

		if(Z_TYPE(retval) == IS_STRING) {
			PANCAKE_GLOBALS(dateFormat) = Z_STRVAL(retval);
		}

		zval_ptr_dtor(&array);
		zval_ptr_dtor(&arg);
	}

	char *date = php_format_date(PANCAKE_GLOBALS(dateFormat), strlen(PANCAKE_GLOBALS(dateFormat)), time(NULL), 1 TSRMLS_CC);
	int outputString_len, date_len = strlen(date);

	if(PANCAKE_GLOBALS(currentThread) == NULL) {
		outputString_len = sizeof("[Master]  \n") - 1 + date_len + string_len;
		outputString = emalloc(outputString_len + 1);
		memcpy(outputString, "[Master] ", sizeof("[Master] ") - 1);
		memcpy(outputString + sizeof("[Master] ") - 1, date, date_len);
		outputString[sizeof("[Master] ") - 1 + date_len] = ' ';
		memcpy(outputString + sizeof("[Master] ") + date_len, *string, string_len);
		memcpy(outputString + outputString_len - 1, "\n", 2);
	} else {
		zval *name;
		FAST_READ_PROPERTY(name, PANCAKE_GLOBALS(currentThread), "friendlyName", sizeof("friendlyName") - 1, HASH_OF_friendlyName);

		outputString_len = 5 + Z_STRLEN_P(name) + date_len + string_len; // 5 = [ + ] + "  \n"
		outputString = emalloc(outputString_len + 1);
		outputString[0] = '[';
		memcpy(outputString + 1, Z_STRVAL_P(name), Z_STRLEN_P(name));
		outputString[1 + Z_STRLEN_P(name)] = ']';
		outputString[2 + Z_STRLEN_P(name)] = ' ';
		memcpy(outputString + 3 + Z_STRLEN_P(name), date, date_len);
		outputString[3 + Z_STRLEN_P(name) + date_len] = ' ';
		memcpy(outputString + 4 + Z_STRLEN_P(name) + date_len, *string, string_len);
		memcpy(outputString + 4 + Z_STRLEN_P(name) + date_len + string_len, "\n", 2);
	}

	efree(date);

	if(!zend_get_constant("pancake\\DAEMONIZED", sizeof("pancake\\DAEMONIZED") - 1, &daemonized TSRMLS_CC)
	|| Z_LVAL(daemonized) == 0) {
		fwrite(outputString, outputString_len, 1, stdout);
		fflush(stdout);
	}

	if((flags & OUTPUT_LOG)) {
		if((flags & OUTPUT_SYSTEM)) {
			if((!PANCAKE_GLOBALS(systemLogStream) && PancakeLoadFilePointers(TSRMLS_C))
			|| PANCAKE_GLOBALS(systemLogStream)) {
				fwrite(outputString, outputString_len, 1, PANCAKE_GLOBALS(systemLogStream));
				fflush(PANCAKE_GLOBALS(systemLogStream));
			}
		}

		if((flags & OUTPUT_REQUEST)) {
			if((!PANCAKE_GLOBALS(requestLogStream) && PancakeLoadFilePointers(TSRMLS_C))
			|| PANCAKE_GLOBALS(requestLogStream)) {
				fwrite(outputString, outputString_len, 1, PANCAKE_GLOBALS(requestLogStream));
				fflush(PANCAKE_GLOBALS(requestLogStream));
			}
		}
	}

	*string = outputString;

	return 1;
}

PHP_FUNCTION(out)
{
	char *string;
	int string_len;
	long flags = 0;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s|l", &string, &string_len, &flags) == FAILURE) {
		RETURN_FALSE;
	}

	if(!PancakeOutput(&string, string_len, flags TSRMLS_CC)) {
		RETURN_FALSE;
	}

	efree(string);

	RETURN_TRUE;
}

PHP_FUNCTION(errorHandler)
{
	char *errstr, *errfile, *errorMessage;
	int errstr_len, errfile_len;
	long errtype, errline;
	zval *scope;

	if(EG(error_reporting) == 0) {
		RETURN_TRUE;
	}

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ls|slz", &errtype, &errstr, &errstr_len, &errfile, &errfile_len, &errline, &scope) == FAILURE) {
		RETURN_FALSE;
	}

	if(errtype & (ERROR_REPORTING)) {
		int message_len = spprintf(&errorMessage, 0, "An error (%d) occurred: %s in %s on line %d", errtype, errstr, errfile, errline);
		char *errorMessage_d = errorMessage;
		PancakeOutput(&errorMessage, message_len, OUTPUT_SYSTEM TSRMLS_CC);
		efree(errorMessage_d);

		if((!PANCAKE_GLOBALS(errorLogStream) && PancakeLoadFilePointers(TSRMLS_C))
		|| PANCAKE_GLOBALS(errorLogStream)) {
			fwrite(errorMessage, strlen(errorMessage), 1, PANCAKE_GLOBALS(errorLogStream));
			fflush(PANCAKE_GLOBALS(errorLogStream));
		}

		efree(errorMessage);
	}

	RETURN_TRUE;
}

PHP_FUNCTION(setThread) {
	zval *virtualHostArray = NULL;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "o|oall", &PANCAKE_GLOBALS(currentThread), &PANCAKE_GLOBALS(defaultVirtualHost), &virtualHostArray, &PANCAKE_GLOBALS(postMaxSize), &PANCAKE_GLOBALS(enableAuthentication)) == FAILURE) {
		RETURN_FALSE;
	}

	Z_ADDREF_P(PANCAKE_GLOBALS(currentThread));

	zval *array, *arg, retval;

	/* Fetch allowHEAD */
	MAKE_STD_ZVAL(array);
	array_init(array);
	add_next_index_stringl(array, "Pancake\\Config", sizeof("Pancake\\Config") - 1, 1);
	add_next_index_stringl(array, "get", 3, 1);

	if(virtualHostArray != NULL) {
		Z_ADDREF_P(virtualHostArray);
		Z_ADDREF_P(PANCAKE_GLOBALS(defaultVirtualHost));
		PANCAKE_GLOBALS(virtualHostArray) = virtualHostArray;

		MAKE_STD_ZVAL(arg);
		Z_TYPE_P(arg) = IS_STRING;
		Z_STRLEN_P(arg) = sizeof("main.allowhead") - 1;
		Z_STRVAL_P(arg) = estrndup("main.allowhead", sizeof("main.allowhead") - 1);

		if(call_user_function(CG(function_table), NULL, array, &retval, 1, &arg TSRMLS_CC) == FAILURE) {
			zval_ptr_dtor(&array);
			zval_ptr_dtor(&arg);
			RETURN_FALSE;
		}

		if(Z_TYPE(retval) <= IS_BOOL)
			PANCAKE_GLOBALS(allowHEAD) = Z_LVAL(retval);

		zval_ptr_dtor(&arg);

		/* Fetch allowTRACE */
		MAKE_STD_ZVAL(arg);
		Z_TYPE_P(arg) = IS_STRING;
		Z_STRLEN_P(arg) = sizeof("main.allowtrace") - 1;
		Z_STRVAL_P(arg) = estrndup("main.allowtrace", sizeof("main.allowtrace") - 1);

		if(call_user_function(CG(function_table), NULL, array, &retval, 1, &arg TSRMLS_CC) == FAILURE) {
			zval_ptr_dtor(&array);
			zval_ptr_dtor(&arg);
			RETURN_FALSE;
		}

		if(Z_TYPE(retval) <= IS_BOOL)
			PANCAKE_GLOBALS(allowTRACE) = Z_LVAL(retval);

		zval_ptr_dtor(&arg);

		/* Fetch allowOPTIONS */
		MAKE_STD_ZVAL(arg);
		Z_TYPE_P(arg) = IS_STRING;
		Z_STRLEN_P(arg) = sizeof("main.allowoptions") - 1;
		Z_STRVAL_P(arg) = estrndup("main.allowoptions", sizeof("main.allowoptions") - 1);

		if(call_user_function(CG(function_table), NULL, array, &retval, 1, &arg TSRMLS_CC) == FAILURE) {
			zval_ptr_dtor(&array);
			zval_ptr_dtor(&arg);
			RETURN_FALSE;
		}

		if(Z_TYPE(retval) <= IS_BOOL)
			PANCAKE_GLOBALS(allowOPTIONS) = Z_LVAL(retval);

		zval_ptr_dtor(&arg);
	}

	/* Fetch exposePancake */
	MAKE_STD_ZVAL(arg);
	Z_TYPE_P(arg) = IS_STRING;
	Z_STRLEN_P(arg) = sizeof("main.exposepancake") - 1;
	Z_STRVAL_P(arg) = estrndup("main.exposepancake", sizeof("main.exposepancake") - 1);

	if(call_user_function(CG(function_table), NULL, array, &retval, 1, &arg TSRMLS_CC) == FAILURE) {
		zval_ptr_dtor(&array);
		zval_ptr_dtor(&arg);
		RETURN_FALSE;
	}

	PANCAKE_GLOBALS(exposePancake) = Z_LVAL(retval);

	zval_ptr_dtor(&arg);

	/* Fetch tmpDir */
	MAKE_STD_ZVAL(arg);
	Z_TYPE_P(arg) = IS_STRING;
	Z_STRLEN_P(arg) = sizeof("main.tmppath") - 1;
	Z_STRVAL_P(arg) = estrndup("main.tmppath", sizeof("main.tmppath") - 1);

	if(call_user_function(CG(function_table), NULL, array, &retval, 1, &arg TSRMLS_CC) == FAILURE) {
		zval_ptr_dtor(&array);
		zval_ptr_dtor(&arg);
		RETURN_FALSE;
	}

	PANCAKE_GLOBALS(tmpDir) = Z_STRVAL(retval);

	zval_ptr_dtor(&arg);
	zval_ptr_dtor(&array);

	MAKE_STD_ZVAL(PANCAKE_GLOBALS(pancakeVersionString));
	Z_TYPE_P(PANCAKE_GLOBALS(pancakeVersionString)) = IS_STRING;
	Z_STRLEN_P(PANCAKE_GLOBALS(pancakeVersionString)) = strlen(PANCAKE_VERSION_STRING);
	Z_STRVAL_P(PANCAKE_GLOBALS(pancakeVersionString)) = estrndup(PANCAKE_VERSION_STRING, Z_STRLEN_P(PANCAKE_GLOBALS(pancakeVersionString)));

	MAKE_STD_ZVAL(PANCAKE_GLOBALS(defaultContentType));
	Z_TYPE_P(PANCAKE_GLOBALS(defaultContentType)) = IS_STRING;
	Z_STRVAL_P(PANCAKE_GLOBALS(defaultContentType)) = estrndup("text/html", sizeof("text/html") - 1);
	Z_STRLEN_P(PANCAKE_GLOBALS(defaultContentType)) = sizeof("text/html") - 1;
}

static zend_bool PancakeJITFetchGLOBALS(const char *name, uint name_len TSRMLS_DC) /* {{{ */
{
	zval *globals;

	ALLOC_ZVAL(globals);
	Z_SET_REFCOUNT_P(globals, 1);
	Z_SET_ISREF_P(globals);
	Z_TYPE_P(globals) = IS_ARRAY;
	Z_ARRVAL_P(globals) = &EG(symbol_table);

	zend_hash_quick_update(&EG(symbol_table), "GLOBALS", sizeof("GLOBALS"), HASH_OF_GLOBALS, &globals, sizeof(zval*), NULL);

	return 0;
}

static zend_bool CodeCacheJITFetch(const char *name, uint name_len TSRMLS_DC) {
	// Every superglobal fetched now can not be JIT fetched
	if(!strcmp(name, "_GET")) {
		PANCAKE_GLOBALS(JIT_GET) = 0;
	} else if(!strcmp(name, "_SERVER")) {
		PANCAKE_GLOBALS(JIT_SERVER) = 0;
	} else if(!strcmp(name, "_COOKIE")) {
		PANCAKE_GLOBALS(JIT_COOKIE) = 0;
	} else if(!strcmp(name, "_REQUEST")) {
		PANCAKE_GLOBALS(JIT_REQUEST) = 0;
	} else if(!strcmp(name, "_POST")) {
		PANCAKE_GLOBALS(JIT_POST) = 0;
	} else if(!strcmp(name, "_FILES")) {
		PANCAKE_GLOBALS(JIT_FILES) = 0;
	} else if(!strcmp(name, "_ENV")) {
		PANCAKE_GLOBALS(JIT_ENV) = 0;
	}

	return 0;
}

PHP_FUNCTION(CodeCacheJITGlobals) {
	// Kill Zend auto globals HashTable and rebuild it
	zend_hash_destroy(CG(auto_globals));
	zend_hash_init_ex(CG(auto_globals), 7, NULL, NULL, 1, 0);

	zend_register_auto_global(ZEND_STRL("_GET"), 1, (zend_auto_global_callback) CodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_COOKIE"), 1, (zend_auto_global_callback) CodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_SERVER"), 1, (zend_auto_global_callback) CodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_REQUEST"), 1, (zend_auto_global_callback) CodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_POST"), 1, (zend_auto_global_callback) CodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_FILES"), 1, (zend_auto_global_callback) CodeCacheJITFetch TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_ENV"), 1, (zend_auto_global_callback) CodeCacheJITFetch TSRMLS_CC);

	zend_activate_auto_globals(TSRMLS_C);
}

PHP_FUNCTION(ExecuteJITGlobals) {
	// Kill Zend auto globals HashTable and rebuild it
	zend_hash_destroy(CG(auto_globals));
	zend_hash_init_ex(CG(auto_globals), 9, NULL, NULL, 1, 0);

	zend_register_auto_global(ZEND_STRL("_COOKIE"), PANCAKE_GLOBALS(JIT_COOKIE), PancakeJITFetchCookies TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_GET"), PANCAKE_GLOBALS(JIT_GET), PancakeJITFetchGET TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_SERVER"), PANCAKE_GLOBALS(JIT_SERVER), PancakeJITFetchSERVER TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_REQUEST"), PANCAKE_GLOBALS(JIT_REQUEST), PancakeJITFetchREQUEST TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_POST"), PANCAKE_GLOBALS(JIT_POST), PancakeJITFetchPOST TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_FILES"), PANCAKE_GLOBALS(JIT_FILES), PancakeJITFetchFILES TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_ENV"), PANCAKE_GLOBALS(JIT_ENV), PancakeJITFetchENV TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("GLOBALS"), 0, PancakeJITFetchGLOBALS TSRMLS_CC);
	zend_register_auto_global(ZEND_STRL("_SESSION"), 0, NULL TSRMLS_CC);
}

PHP_FUNCTION(makeSID) {
	if(PS(id) != NULL)
		efree(PS(id));
	PS(id) = PS(mod)->s_create_sid(&PS(mod_data), NULL TSRMLS_CC);
}
