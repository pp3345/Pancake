
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI.c                                            	*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "PancakeSAPI.h"

ZEND_DECLARE_MODULE_GLOBALS(PancakeSAPI)

const zend_function_entry PancakeSAPI_functions[] = {
	ZEND_NS_FE("Pancake", SAPIPrepare, NULL)
	ZEND_NS_FE("Pancake", SAPIRequest, NULL)
	ZEND_NS_FE("Pancake", SAPIFinishRequest, NULL)
	ZEND_NS_FE("Pancake", SAPIFlushBuffers, NULL)
	ZEND_NS_FE("Pancake", SAPIPostRequestCleanup, NULL)
	ZEND_FE_END
};

zend_module_entry PancakeSAPI_module_entry = {
	STANDARD_MODULE_HEADER,
	"PancakeSAPI",
	PancakeSAPI_functions,
	PHP_MINIT(PancakeSAPI),
	NULL,
	PHP_RINIT(PancakeSAPI),
	PHP_RSHUTDOWN(PancakeSAPI),
	NULL,
	PANCAKE_VERSION,
	STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_PANCAKE
ZEND_GET_MODULE(PancakeSAPI)
#endif

PHP_MINIT_FUNCTION(PancakeSAPI) {
#ifdef ZTS
	ZEND_INIT_MODULE_GLOBALS(PancakeSAPI, NULL, NULL);
#endif

	PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes) = NULL;
	PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes) = NULL;

	return SUCCESS;
}

static void PancakeSAPIStoreHTTPStatusLine(TSRMLS_D) {
	if(SG(sapi_headers).http_status_line) {
		if(strlen(SG(sapi_headers).http_status_line) >= sizeof("HTTP/1.0 200 ") && SG(sapi_headers).http_status_line[12] == ' ') {
			char *answerCodeString = &(SG(sapi_headers).http_status_line[13]);

			PancakeQuickWritePropertyString(PANCAKE_SAPI_GLOBALS(request), "answerCodeString", sizeof("answerCodeString"), HASH_OF_answerCodeString, answerCodeString,
					strlen(answerCodeString), 1);
		}

		efree(SG(sapi_headers).http_status_line);
		SG(sapi_headers).http_status_line = NULL;
	}
}

static int PancakeSAPISendHeaders(sapi_headers_struct *sapi_headers TSRMLS_DC) {
	// We don't actually send headers at the moment
	SG(headers_sent) = 0;

	// sapi_send_headers() will destroy the status line afterwards
	PancakeSAPIStoreHTTPStatusLine(TSRMLS_C);

	return SAPI_HEADER_SENT_SUCCESSFULLY;
}

static int PancakeSAPIHeaderHandler(sapi_header_struct *sapi_header, sapi_header_op_enum op, sapi_headers_struct *sapi_headers TSRMLS_DC) {
	zval *answerHeaders;

	if(!PANCAKE_SAPI_GLOBALS(inExecution)) {
		return SUCCESS;
	}

	switch(op) {
		case SAPI_HEADER_DELETE_ALL:
			FAST_READ_PROPERTY(answerHeaders, PANCAKE_SAPI_GLOBALS(request), "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);

			zend_hash_clean(Z_ARRVAL_P(answerHeaders));
			break;
		case SAPI_HEADER_DELETE:
			FAST_READ_PROPERTY(answerHeaders, PANCAKE_SAPI_GLOBALS(request), "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);

			sapi_header->header = php_strtolower(sapi_header->header, sapi_header->header_len);
			zend_hash_del(Z_ARRVAL_P(answerHeaders), sapi_header->header, sapi_header->header_len + 1);
			break;
		case SAPI_HEADER_REPLACE:
		case SAPI_HEADER_ADD: {
			char *valueStr = strchr(sapi_header->header, ':'), *ptr;
			int name_len;
			zval *value;

			if(!valueStr) {
				return 0;
			}

			ptr = valueStr;
			*valueStr = '\0';
			name_len = valueStr - sapi_header->header;
			valueStr++;
			LEFT_TRIM(valueStr);

			sapi_header->header = php_strtolower(sapi_header->header, name_len);

			MAKE_STD_ZVAL(value);
			Z_TYPE_P(value) = IS_STRING;
			Z_STRLEN_P(value) = strlen(valueStr);
			Z_STRVAL_P(value) = estrndup(valueStr, Z_STRLEN_P(value));

			name_len++;

			PancakeSetAnswerHeader(PANCAKE_SAPI_GLOBALS(request), sapi_header->header, name_len, value, op == SAPI_HEADER_REPLACE,
					zend_inline_hash_func(sapi_header->header, name_len) TSRMLS_CC);

			*ptr = ':';
		}
			return SAPI_HEADER_ADD;
	}

	return SUCCESS;
}

static int PancakeSAPIOutputHandler(const char *str, unsigned int str_length TSRMLS_DC) {
	if(!PANCAKE_SAPI_GLOBALS(inExecution)) {
		return SUCCESS;
	}

	if(PANCAKE_SAPI_GLOBALS(output) == NULL) {
		PANCAKE_SAPI_GLOBALS(outputLength) = str_length;

		PANCAKE_SAPI_GLOBALS(output) = emalloc(MAX(str_length + 1, 32786));
		memcpy(PANCAKE_SAPI_GLOBALS(output), str, str_length);
	} else {
		int totalLength = PANCAKE_SAPI_GLOBALS(outputLength) + str_length;

		if(totalLength + 1 > 32786) {
			PANCAKE_SAPI_GLOBALS(output) = erealloc(PANCAKE_SAPI_GLOBALS(output), totalLength + 1);
		}

		memcpy(PANCAKE_SAPI_GLOBALS(output) + PANCAKE_SAPI_GLOBALS(outputLength), str, str_length);
		PANCAKE_SAPI_GLOBALS(outputLength) = totalLength;
	}

	return SUCCESS;
}

static int PancakeSAPISetINIEntriesUnmodified(zend_ini_entry **ini_entry TSRMLS_DC) {
	(*ini_entry)->modified = 0;

	return 0;
}

static HashTable *PancakeSAPITransformHashTableValuesToKeys(HashTable *table) {
	HashTable *new;
	zval **value;

	ALLOC_HASHTABLE(new);
	zend_hash_init(new, table->nTableSize, NULL, NULL, 0);

	PANCAKE_FOREACH(table, value) {
		if(Z_TYPE_PP(value) != IS_STRING) {
			continue;
		}

		php_strtolower(Z_STRVAL_PP(value), Z_STRLEN_PP(value));
		zend_hash_add(new, Z_STRVAL_PP(value), Z_STRLEN_PP(value) + 1, NULL, 0, NULL);
	}

	return new;
}

PHP_RINIT_FUNCTION(PancakeSAPI) {
	zend_function *function;
	zend_class_entry **vars;
	zval *disabledFunctions, *autoDelete, *autoDeleteExcludes;

	if(PANCAKE_GLOBALS(inSAPIReboot) == 1) {
		return SUCCESS;
	}

	PANCAKE_SAPI_GLOBALS(inExecution) = 0;
	PANCAKE_SAPI_GLOBALS(outputLength) = 0;
	PANCAKE_SAPI_GLOBALS(output) = NULL;

	// Fetch vHost
	FAST_READ_PROPERTY(PANCAKE_SAPI_GLOBALS(vHost), PANCAKE_GLOBALS(currentThread), "vHost", sizeof("vHost") - 1, HASH_OF_vHost);

	// Find DeepTrace (we must not shutdown DeepTrace on SAPI module init)
	zend_hash_find(&module_registry, "deeptrace", sizeof("deeptrace"), (void**) &PANCAKE_SAPI_GLOBALS(DeepTrace));

	// Set SAPI module handlers
	sapi_module.name = "pancake";
	sapi_module.pretty_name = "Pancake SAPI";
	sapi_module.header_handler = PancakeSAPIHeaderHandler;
	sapi_module.ub_write = PancakeSAPIOutputHandler;
	sapi_module.send_headers = PancakeSAPISendHeaders;
	sapi_module.flush = NULL;

	// Disable functions
	disabledFunctions = zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpDisabledFunctions", sizeof("phpDisabledFunctions") - 1, 0 TSRMLS_CC);

	if(Z_TYPE_P(disabledFunctions) == IS_ARRAY) {
		zval **value;

		PANCAKE_FOREACH(Z_ARRVAL_P(disabledFunctions), value) {
			php_strtolower(Z_STRVAL_PP(value), Z_STRLEN_PP(value));
			zend_disable_function(Z_STRVAL_PP(value), Z_STRLEN_PP(value) TSRMLS_CC);
		}
	}

	// Read auto deletes
	autoDelete = zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "autoDelete", sizeof("autoDelete") - 1, 0 TSRMLS_CC);
	PANCAKE_SAPI_GLOBALS(autoDeleteFunctions) = Z_TYPE_P(autoDelete) == IS_ARRAY && zend_hash_exists(Z_ARRVAL_P(autoDelete), "functions", sizeof("functions"));
	PANCAKE_SAPI_GLOBALS(autoDeleteClasses) = Z_TYPE_P(autoDelete) == IS_ARRAY && zend_hash_exists(Z_ARRVAL_P(autoDelete), "classes", sizeof("classes"));
	PANCAKE_SAPI_GLOBALS(autoDeleteIncludes) = Z_TYPE_P(autoDelete) == IS_ARRAY && zend_hash_exists(Z_ARRVAL_P(autoDelete), "includes", sizeof("includes"));

	autoDeleteExcludes = zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "autoDeleteExcludes", sizeof("autoDeleteExcludes") - 1, 0 TSRMLS_CC);
	if(Z_TYPE_P(autoDeleteExcludes) == IS_ARRAY) {
		zval **data;

		if(zend_hash_find(Z_ARRVAL_P(autoDeleteExcludes), "functions", sizeof("functions"), (void**) &data) == SUCCESS
		&& Z_TYPE_PP(data) == IS_ARRAY) {
			PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes) = PancakeSAPITransformHashTableValuesToKeys(Z_ARRVAL_PP(data));
		}

		if(zend_hash_find(Z_ARRVAL_P(autoDeleteExcludes), "includes", sizeof("includes"), (void**) &data) == SUCCESS
		&& Z_TYPE_PP(data) == IS_ARRAY) {
			PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes) = PancakeSAPITransformHashTableValuesToKeys(Z_ARRVAL_PP(data));
		}
	}

	// Hook some functions
	zend_hash_find(EG(function_table), "headers_sent", sizeof("headers_sent"), (void**) &function);
	PHP_headers_sent = function->internal_function.handler;
	function->internal_function.handler = Pancake_headers_sent;

	if(zend_hash_find(EG(function_table), "session_start", sizeof("session_start"), (void**) &function) == SUCCESS) {
		PHP_session_start = function->internal_function.handler;
		function->internal_function.handler = Pancake_session_start;
	}

	// Set current php.ini state as initial
	if (EG(modified_ini_directives)) {
		zend_hash_apply(EG(modified_ini_directives), (apply_func_t) PancakeSAPISetINIEntriesUnmodified TSRMLS_CC);
		zend_hash_destroy(EG(modified_ini_directives));
		FREE_HASHTABLE(EG(modified_ini_directives));
		EG(modified_ini_directives) = NULL;
	}

	// Destroy uploaded files array
	if(SG(rfc1867_uploaded_files)) {
		zend_hash_destroy(SG(rfc1867_uploaded_files));
		FREE_HASHTABLE(SG(rfc1867_uploaded_files));
		SG(rfc1867_uploaded_files) = NULL;
	}

	// Set some SAPI globals
	SG(request_info).no_headers = 0;
	SG(headers_sent) = 0;
	SG(sapi_headers).http_response_code = 0;
	if(SG(sapi_headers).http_status_line) {
		efree(SG(sapi_headers).http_status_line);
		SG(sapi_headers).http_status_line = NULL;
	}

	zend_llist_clean(&SG(sapi_headers).headers);

	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(PancakeSAPI) {
	if(PANCAKE_GLOBALS(inSAPIReboot) == 1) {
		return SUCCESS;
	}

	if(PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes)) {
		zend_hash_destroy(PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes));
		efree(PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes));
	}

	if(PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes)) {
		zend_hash_destroy(PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes));
		efree(PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes));
	}

	return SUCCESS;
}

PHP_FUNCTION(SAPIPrepare) {
	// Fetch amount of currently existing functions
	if(PANCAKE_SAPI_GLOBALS(autoDeleteFunctions)) {
		PANCAKE_SAPI_GLOBALS(functionsPre) = EG(function_table)->nNumOfElements;
	}

	// Fetch currently included files
	if(PANCAKE_SAPI_GLOBALS(autoDeleteIncludes)) {
		PANCAKE_SAPI_GLOBALS(includesPre) = EG(included_files).nNumOfElements;
	}
}

PHP_FUNCTION(SAPIRequest) {
	zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z", &PANCAKE_SAPI_GLOBALS(request));
	PANCAKE_GLOBALS(JITGlobalsHTTPRequest) = PANCAKE_SAPI_GLOBALS(request);

	PANCAKE_SAPI_GLOBALS(inExecution) = 1;

	zend_activate_auto_globals(TSRMLS_C);
}

PHP_FUNCTION(SAPIFinishRequest) {
	zval *answerCode;

	php_output_end_all(TSRMLS_C);
	PANCAKE_SAPI_GLOBALS(inExecution) = 0;

	PancakeQuickWritePropertyLong(PANCAKE_SAPI_GLOBALS(request), "answerCode", sizeof("answerCode"), HASH_OF_answerCode, SG(sapi_headers).http_response_code);

	PancakeSAPIStoreHTTPStatusLine(TSRMLS_C);

	SG(sapi_headers).http_response_code = 0;
	zend_llist_clean(&SG(sapi_headers).headers);
	SG(headers_sent) = 0;

	if(PANCAKE_SAPI_GLOBALS(output)) {
		// PHP does not always null-terminate buffered output strings
		PANCAKE_SAPI_GLOBALS(output)[PANCAKE_SAPI_GLOBALS(outputLength)] = '\0';
		PancakeQuickWritePropertyString(PANCAKE_SAPI_GLOBALS(request), "answerBody", sizeof("answerBody"), HASH_OF_answerBody,
				PANCAKE_SAPI_GLOBALS(output), PANCAKE_SAPI_GLOBALS(outputLength), 0);
	}

	PANCAKE_SAPI_GLOBALS(outputLength) = 0;
	PANCAKE_SAPI_GLOBALS(output) = NULL;
}

PHP_FUNCTION(SAPIFlushBuffers) {
	php_output_end_all(TSRMLS_C);
}

static int PancakeSAPIShutdownModule(zend_module_entry *module TSRMLS_DC) {
	if (module->request_shutdown_func && module != PANCAKE_SAPI_GLOBALS(DeepTrace)) {
		module->request_shutdown_func(module->type, module->module_number TSRMLS_CC);
	}

	return 0;
}

static int PancakeSAPIStartupModule(zend_module_entry *module TSRMLS_DC) {
	if (module->request_startup_func) {
		module->request_startup_func(module->type, module->module_number TSRMLS_CC);
	}

	return 0;
}

PHP_FUNCTION(SAPIPostRequestCleanup) {
	HashTable *shutdownFunctions = BG(user_shutdown_function_names);

	// Tell Pancake not to shutdown
	PANCAKE_GLOBALS(inSAPIReboot) = 1;

	// Run RSHUTDOWN for modules
	zend_hash_reverse_apply(&module_registry, (apply_func_t) PancakeSAPIShutdownModule TSRMLS_CC);

	// Initialize modules again
	zend_hash_reverse_apply(&module_registry, (apply_func_t) PancakeSAPIStartupModule TSRMLS_CC);
	PANCAKE_GLOBALS(inSAPIReboot) = 0;

	// RINIT of basic will destroy shutdown functions, however we still need them
	BG(user_shutdown_function_names) = shutdownFunctions;

	// Restore ini entries
	zend_try {
		zend_ini_deactivate(TSRMLS_C);
	} zend_end_try();

	// Destroy uploaded files array
	if(SG(rfc1867_uploaded_files)) {
		zend_hash_destroy(SG(rfc1867_uploaded_files));
		FREE_HASHTABLE(SG(rfc1867_uploaded_files));
		SG(rfc1867_uploaded_files) = NULL;
	}

	// Destroy functions
	if(PANCAKE_SAPI_GLOBALS(autoDeleteFunctions) && EG(function_table)->nNumOfElements > PANCAKE_SAPI_GLOBALS(functionsPre)) {
		char *functionName;
		uint functionName_len;
		int iterationCount = EG(function_table)->nNumOfElements - PANCAKE_SAPI_GLOBALS(functionsPre);

		for(zend_hash_internal_pointer_end_ex(EG(function_table), NULL);
			iterationCount--
			&& zend_hash_get_current_key_ex(EG(function_table), &functionName, &functionName_len, NULL, 0, NULL) == HASH_KEY_IS_STRING;) {
			if(PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes)
			&&	zend_hash_quick_exists(PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes), functionName, functionName_len, EG(function_table)->pInternalPointer->h)) {
				zend_hash_move_backward(EG(function_table));
				continue;
			}

			if(EG(function_table)->pInternalPointer->h == HASH_OF___autoload) {
				EG(autoload_func) = NULL;
			}

			zend_hash_quick_del(EG(function_table), functionName, functionName_len, EG(function_table)->pInternalPointer->h);
			zend_hash_internal_pointer_end(EG(function_table));
		}
	}

	PANCAKE_SAPI_GLOBALS(functionsPre) = EG(function_table)->nNumOfElements;

	// Destroy includes
	if(PANCAKE_SAPI_GLOBALS(autoDeleteIncludes)) {
		if(PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes) || PANCAKE_SAPI_GLOBALS(includesPre)) {
			char *fileName;
			int fileName_len;
			int iterationCount = EG(included_files).nNumOfElements - PANCAKE_SAPI_GLOBALS(includesPre);

			for(zend_hash_internal_pointer_end(&EG(included_files));
				iterationCount--
				&& zend_hash_get_current_key_ex(&EG(included_files), &fileName, &fileName_len, NULL, 0, NULL) == HASH_KEY_IS_STRING;){
				if(PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes)
				&& zend_hash_quick_exists(PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes), fileName, fileName_len, EG(included_files).pInternalPointer->h)) {
					zend_hash_move_forward(&EG(included_files));
					continue;
				}

				zend_hash_quick_del(&EG(included_files), fileName, fileName_len, EG(included_files).pInternalPointer->h);
				zend_hash_internal_pointer_end(&EG(included_files));
			}
		} else {
			zend_hash_clean(&EG(included_files));
		}
	}

	PANCAKE_SAPI_GLOBALS(includesPre) = EG(included_files).nNumOfElements;
}
