
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI.c                                            	*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "PancakeSAPI.h"

ZEND_DECLARE_MODULE_GLOBALS(PancakeSAPI)

static zend_module_entry **PancakeSAPIModulePostDeactivateHandlers;

const zend_function_entry PancakeSAPI_functions[] = {
	ZEND_NS_FE("Pancake", SAPIPrepare, NULL)
	ZEND_NS_FE("Pancake", SAPIFinishRequest, NULL)
	ZEND_NS_FE("Pancake", SAPIWait, NULL)
	ZEND_NS_FE("Pancake", SAPIExitHandler, NULL)
	ZEND_NS_FE("Pancake", SAPICodeCachePrepare, NULL)
	ZEND_NS_FE("Pancake", SAPICodeCacheJIT, NULL)
	ZEND_NS_FE("Pancake", SAPIFetchSERVER, NULL)
	ZEND_NS_FE("Pancake", SetErrorHandling, NULL)
	ZEND_FE(apache_child_terminate, NULL)
	ZEND_FE(apache_request_headers, NULL)
	ZEND_FALIAS(getallheaders, apache_request_headers, NULL)
	ZEND_FE(apache_response_headers, NULL)
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
	PANCAKE_SAPI_GLOBALS(autoDeleteClassesExcludes) = NULL;
	PANCAKE_SAPI_GLOBALS(processedRequests) = 0;
	PANCAKE_SAPI_GLOBALS(exit) = 0;
	PANCAKE_SAPI_GLOBALS(persistentSymbols) = NULL;
	PANCAKE_SAPI_GLOBALS(CodeCache) = 0;
	PANCAKE_SAPI_GLOBALS(haveCriticalDeletions) = 0;
	PANCAKE_SAPI_GLOBALS(JIT_GET) = PG(auto_globals_jit);
	PANCAKE_SAPI_GLOBALS(JIT_COOKIE) = PG(auto_globals_jit);
	PANCAKE_SAPI_GLOBALS(JIT_SERVER) = PG(auto_globals_jit);
	PANCAKE_SAPI_GLOBALS(JIT_REQUEST) = PG(auto_globals_jit);
	PANCAKE_SAPI_GLOBALS(JIT_POST) = PG(auto_globals_jit);
	PANCAKE_SAPI_GLOBALS(JIT_FILES) = PG(auto_globals_jit);
	PANCAKE_SAPI_GLOBALS(JIT_ENV) = PG(auto_globals_jit);
	return SUCCESS;
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

static int PancakeSAPICleanNonPersistentConstant(const zend_constant *c TSRMLS_DC) {
	if(c->flags & PANCAKE_PSEUDO_PERSISTENT) {
		return ZEND_HASH_APPLY_STOP;
	} else {
		PANCAKE_SAPI_GLOBALS(haveCriticalDeletions) = 1;
		return ZEND_HASH_APPLY_REMOVE;
	}
}

static int PancakeSAPISendHeaders(sapi_headers_struct *sapi_headers TSRMLS_DC) {
	zval *answerHeaders, **headerValue;
	size_t offset = sizeof(char) + sizeof(size_t) + sizeof(short) + sizeof(short) + sizeof(short),
			bufLength,
			offset2 = 0;
	unsigned short responseCode = (unsigned short) sapi_headers->http_response_code,
		statusLineLength = 0, num = 0;
	char *headerName, *buf, *answerCodeString = NULL;
	int headerName_len, bufSize;

	// We don't need to send anything if the headers didn't change
	// Also, PHP will call this function when Pancake shuts down
	if(!PANCAKE_SAPI_GLOBALS(inExecution) || !PANCAKE_SAPI_GLOBALS(haveChangedHeaders)) {
		return SAPI_HEADER_SENT_SUCCESSFULLY;
	}

	// Check for custom answer string
	if(sapi_headers->http_status_line && strlen(sapi_headers->http_status_line) >= sizeof("HTTP/1.0 200 ") && sapi_headers->http_status_line[12] == ' ') {
		answerCodeString = &(sapi_headers->http_status_line[13]);
		statusLineLength = (short) strlen(answerCodeString) + 1;
	}

	// Write answer headers
	FAST_READ_PROPERTY(answerHeaders, PANCAKE_SAPI_GLOBALS(request), "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);

	bufSize = 512 + statusLineLength;
	buf = emalloc(bufSize);
	buf[0] = '\0';

	memcpy(buf + sizeof(char) + sizeof(short) + sizeof(size_t), &responseCode, sizeof(short));
	memcpy(buf + sizeof(char) + sizeof(short) + sizeof(size_t) + sizeof(short), &statusLineLength, sizeof(short));
	memcpy(buf + sizeof(char) + sizeof(short) + sizeof(size_t) + sizeof(short) + sizeof(short), answerCodeString, statusLineLength);
	offset += statusLineLength;

	PANCAKE_FOREACH_KEY(Z_ARRVAL_P(answerHeaders), headerName, headerName_len, headerValue) {
		if(Z_TYPE_PP(headerValue) == IS_ARRAY) {
			zval **singleValue;

			PANCAKE_FOREACH(Z_ARRVAL_PP(headerValue), singleValue) {
				if(bufSize < offset + headerName_len + Z_STRLEN_PP(singleValue)) {
					bufSize += headerName_len + Z_STRLEN_PP(singleValue) + 256;
					buf = erealloc(buf, bufSize);
				}

				memcpy(buf + offset, &headerName_len, sizeof(int));
				offset += sizeof(int);
				memcpy(buf + offset, headerName, headerName_len);
				offset += headerName_len;

				memcpy(buf + offset, &Z_STRLEN_PP(singleValue), sizeof(int));
				offset += sizeof(int);
				memcpy(buf + offset, Z_STRVAL_PP(singleValue), Z_STRLEN_PP(singleValue));
				offset += Z_STRLEN_PP(singleValue);

				num++;
			}
		} else {
			if(bufSize < offset + headerName_len + Z_STRLEN_PP(headerValue)) {
				bufSize += headerName_len + Z_STRLEN_PP(headerValue) + 256;
				buf = erealloc(buf, bufSize);
			}

			memcpy(buf + offset, &headerName_len, sizeof(int));
			offset += sizeof(int);
			memcpy(buf + offset, headerName, headerName_len);
			offset += headerName_len;

			memcpy(buf + offset, &Z_STRLEN_PP(headerValue), sizeof(int));
			offset += sizeof(int);
			memcpy(buf + offset, Z_STRVAL_PP(headerValue), Z_STRLEN_PP(headerValue));
			offset += Z_STRLEN_PP(headerValue);

			num++;
		}
	}

	// Set the number of headers
	memcpy(buf + sizeof(char) + sizeof(size_t), &num, sizeof(short));

	// Set the total buffer size
	bufLength = offset - sizeof(char) - sizeof(size_t);
	memcpy(buf + sizeof(char), &bufLength, sizeof(size_t));

	// Write buffer to socket
	while(offset2 < offset) {
		ssize_t result = write(PANCAKE_SAPI_GLOBALS(clientSocket), &buf[offset2], offset - offset2);
		if(result == -1) {
			efree(buf);
			return SAPI_HEADER_SEND_FAILED;
		}

		offset2 += result;
	}

	efree(buf);

	return SAPI_HEADER_SENT_SUCCESSFULLY;
}

static int PancakeSAPIHeaderHandler(sapi_header_struct *sapi_header, sapi_header_op_enum op, sapi_headers_struct *sapi_headers TSRMLS_DC) {
	zval *answerHeaders;

	if(!PANCAKE_SAPI_GLOBALS(inExecution)) {
		return SUCCESS;
	}

	PANCAKE_SAPI_GLOBALS(haveChangedHeaders) = 1;

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

static void PancakeSAPIFlush(void *context) {
	if(PANCAKE_SAPI_GLOBALS(output)) {
		unsigned int offset = 0;

		memcpy(PANCAKE_SAPI_GLOBALS(output) + 1, &PANCAKE_SAPI_GLOBALS(outputLength), sizeof(unsigned int));

		while(offset < PANCAKE_SAPI_GLOBALS(outputLength) + 5) {
			ssize_t result = write(PANCAKE_SAPI_GLOBALS(clientSocket), &PANCAKE_SAPI_GLOBALS(output)[offset], (PANCAKE_SAPI_GLOBALS(outputLength) + 5) - offset);
			if(result == -1) {
				free(PANCAKE_SAPI_GLOBALS(output));
				PANCAKE_SAPI_GLOBALS(outputLength) = 0;
				PANCAKE_SAPI_GLOBALS(output) = NULL;

				return;
			}

			offset += result;
		}

		free(PANCAKE_SAPI_GLOBALS(output));
		PANCAKE_SAPI_GLOBALS(outputLength) = 0;
		PANCAKE_SAPI_GLOBALS(output) = NULL;
	}
}

static int PancakeSAPIOutputHandler(const char *str, unsigned int str_length TSRMLS_DC) {
	unsigned int offset = 0;

	if(!PANCAKE_SAPI_GLOBALS(inExecution) || !str_length) {
		return SUCCESS;
	}

	if(PANCAKE_SAPI_GLOBALS(outputLength) + str_length < 4091) {
		if(PANCAKE_SAPI_GLOBALS(output) == NULL) {
			PANCAKE_SAPI_GLOBALS(output) = malloc(4096);
			PANCAKE_SAPI_GLOBALS(output)[0] = '\1';
		}

		memcpy(PANCAKE_SAPI_GLOBALS(output) + PANCAKE_SAPI_GLOBALS(outputLength) + 5, str, str_length);
		PANCAKE_SAPI_GLOBALS(outputLength) += str_length;

		return SUCCESS;
	}

	PancakeSAPIFlush(NULL);

	/* Write packet type */
	if(write(PANCAKE_SAPI_GLOBALS(clientSocket), "\1", sizeof(char)) == -1) {
		/* What do we do here? */
		return FAILURE;
	}

	write(PANCAKE_SAPI_GLOBALS(clientSocket), &str_length, sizeof(unsigned int));

	while(offset < str_length) {
		ssize_t result = write(PANCAKE_SAPI_GLOBALS(clientSocket), &str[offset], str_length - offset);
		if(result == -1) {
			/* What do we do here? */
			return FAILURE;
		}

		offset += result;
	}

	return SUCCESS;
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

static int PancakeSAPIUnlinkFile(char **file TSRMLS_DC) {
	VCWD_UNLINK(*file);
	return ZEND_HASH_APPLY_REMOVE;
}

PHP_RINIT_FUNCTION(PancakeSAPI) {
	zend_function *function;
	zend_class_entry **vars;
	zval *disabledFunctions, *autoDelete, *autoDeleteExcludes, *HTMLErrors, *processingLimit, *timeout, *phpSocket,
		*controlSocket, *INISettings, **value;
	zend_constant *PHP_SAPI;
	zend_module_entry *core, *module;
	int count = 0;

	if(PANCAKE_GLOBALS(inSAPIReboot) == 1) {
		return SUCCESS;
	}

	PANCAKE_SAPI_GLOBALS(inExecution) = 0;
	PANCAKE_SAPI_GLOBALS(outputLength) = 0;
	PANCAKE_SAPI_GLOBALS(output) = NULL;
	PANCAKE_SAPI_GLOBALS(haveChangedHeaders) = 0;

	// Clean included files table
	zend_hash_clean(&EG(included_files));

	// Implicit flush should default to 0
	PG(implicit_flush) = 0;
	php_output_set_implicit_flush(0 TSRMLS_CC);

	// Fetch vHost
	FAST_READ_PROPERTY(PANCAKE_SAPI_GLOBALS(vHost), PANCAKE_GLOBALS(currentThread), "vHost", sizeof("vHost") - 1, HASH_OF_vHost);

	// Fetch sockets
	phpSocket = zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpSocket", sizeof("phpSocket") - 1, 0 TSRMLS_CC);
	controlSocket = zend_read_property(NULL, PANCAKE_GLOBALS(currentThread), "socket", sizeof("socket") - 1, 0 TSRMLS_CC);
	PANCAKE_SAPI_GLOBALS(listenSocket) = Z_LVAL_P(phpSocket);
	PANCAKE_SAPI_GLOBALS(controlSocket) = Z_LVAL_P(controlSocket);

	// Hide sockets from userspace
	Z_LVAL_P(phpSocket) = Z_LVAL_P(controlSocket) = -1;

	// Set error handling constants
	REGISTER_NS_LONG_CONSTANT("Pancake", "EH_THROW", EH_THROW, CONST_PERSISTENT);
	REGISTER_NS_LONG_CONSTANT("Pancake", "EH_NORMAL", EH_NORMAL, CONST_PERSISTENT);

	// Find DeepTrace (we must not shutdown DeepTrace on SAPI module init)
	zend_hash_find(&module_registry, "deeptrace", sizeof("deeptrace"), (void**) &PANCAKE_SAPI_GLOBALS(DeepTrace));

	// Delete STDOUT, STDERR and STDIN
	zend_hash_del(EG(zend_constants), "STDOUT", sizeof("STDOUT"));
	zend_hash_del(EG(zend_constants), "STDERR", sizeof("STDERR"));
	zend_hash_del(EG(zend_constants), "STDIN", sizeof("STDIN"));

	// Open fake fds (disallow access to Pancake sockets via php:// streams)
	if(fcntl(0, F_GETFD) == -1) {
		open("/dev/null", 0);
	}

	if(fcntl(1, F_GETFD) == -1) {
		open("/dev/null", 0);
	}

	if(fcntl(2, F_SETFD) == -1) {
		open("/dev/null", 0);
	}

	// Delete Pancake direct I/O functions from userspace
	zend_hash_del(EG(function_table), "pancake\\socket", sizeof("pancake\\socket"));
	zend_hash_del(EG(function_table), "pancake\\reuseaddress", sizeof("pancake\\reuseaddress"));
	zend_hash_del(EG(function_table), "pancake\\bind", sizeof("pancake\\bind"));
	zend_hash_del(EG(function_table), "pancake\\listen", sizeof("pancake\\listen"));
	zend_hash_del(EG(function_table), "pancake\\setblocking", sizeof("pancake\\setblocking"));
	zend_hash_del(EG(function_table), "pancake\\write", sizeof("pancake\\write"));
	zend_hash_del(EG(function_table), "pancake\\writebuffer", sizeof("pancake\\writebuffer"));
	zend_hash_del(EG(function_table), "pancake\\read", sizeof("pancake\\read"));
	zend_hash_del(EG(function_table), "pancake\\accept", sizeof("pancake\\accept"));
	zend_hash_del(EG(function_table), "pancake\\keepalive", sizeof("pancake\\keepalive"));
	zend_hash_del(EG(function_table), "pancake\\connect", sizeof("pancake\\connect"));
	zend_hash_del(EG(function_table), "pancake\\close", sizeof("pancake\\close"));
	zend_hash_del(EG(function_table), "pancake\\getsockname", sizeof("pancake\\getsockname"));
	zend_hash_del(EG(function_table), "pancake\\getpeername", sizeof("pancake\\getpeername"));
	zend_hash_del(EG(function_table), "pancake\\select", sizeof("pancake\\select"));
	zend_hash_del(EG(function_table), "pancake\\nonblockingaccept", sizeof("pancake\\nonblockingaccept"));
	zend_hash_del(EG(function_table), "pancake\\naglesalgorithm", sizeof("pancake\\naglesalgorithm"));

	// Fork() is also a bad idea, SetThread() too and scripts shouldn't output anything via Pancake
	zend_hash_del(EG(function_table), "pancake\\fork", sizeof("pancake\\fork"));
	zend_hash_del(EG(function_table), "pancake\\setthread", sizeof("pancake\\setthread"));
	zend_hash_del(EG(function_table), "pancake\\out", sizeof("pancake\\out"));

	// Fetch vHost php.ini settings
	INISettings = zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpINISettings", sizeof("phpINISettings") - 1, 0 TSRMLS_CC);
	if(Z_TYPE_P(INISettings) == IS_ARRAY && zend_hash_num_elements(Z_ARRVAL_P(INISettings))) {
		char *name;
		int name_len;
		zval **value;

		PANCAKE_FOREACH_KEY(Z_ARRVAL_P(INISettings), name, name_len, value) {
			zval constant;
			zend_ini_entry *ini_entry;
			char *new_value;
			int new_value_length;
			zend_bool modified;

			if(zend_hash_find(EG(ini_directives), name, name_len, (void **) &ini_entry) == FAILURE) {
				continue;
			}

			modified = ini_entry->modified;

			convert_to_string(*value);

			if(zend_get_constant(Z_STRVAL_PP(value), Z_STRLEN_PP(value), &constant)) {
				convert_to_string(&constant);
				new_value = estrndup(Z_STRVAL(constant), Z_STRLEN(constant));
				new_value_length = Z_STRLEN(constant);
				zval_dtor(&constant);
			} else {
				new_value = estrndup(Z_STRVAL_PP(value), Z_STRLEN_PP(value));
				new_value_length = Z_STRLEN_PP(value);
			}

			if(!ini_entry->on_modify
				|| ini_entry->on_modify(ini_entry, new_value, new_value_length, ini_entry->mh_arg1, ini_entry->mh_arg2, ini_entry->mh_arg3, ZEND_INI_STAGE_STARTUP TSRMLS_CC) == SUCCESS) {
				if(modified && ini_entry->orig_value != ini_entry->value) {
					efree(ini_entry->value);
					ini_entry->modified = 0;
				}

				ini_entry->value = new_value;
				ini_entry->value_length = new_value_length;
			} else {
				efree(new_value);
			}
		}
	}

	// Unset timeout
	zend_unset_timeout(TSRMLS_C);

	// Disable dl()
	PG(enable_dl) = 0;

	// Fetch post_deactivate functions
	for (zend_hash_internal_pointer_reset(&module_registry);
	     zend_hash_get_current_data(&module_registry, (void *) &module) == SUCCESS;
	     zend_hash_move_forward(&module_registry)) {
		if (module->post_deactivate_func) {
			count++;
		}
	}

	if(count) {
		PancakeSAPIModulePostDeactivateHandlers = emalloc(sizeof(zend_module_entry*) * (count + 1));
		PancakeSAPIModulePostDeactivateHandlers[count] = NULL;

		for (zend_hash_internal_pointer_reset(&module_registry);
			 zend_hash_get_current_data(&module_registry, (void *) &module) == SUCCESS;
			 zend_hash_move_forward(&module_registry)) {
			if (module->post_deactivate_func) {
				PancakeSAPIModulePostDeactivateHandlers[--count] = module;
			}
		}
	} else {
		PancakeSAPIModulePostDeactivateHandlers = NULL;
	}

	// Set SAPI module handlers
	sapi_module.name = "pancake";
	sapi_module.pretty_name = "Pancake SAPI";
	sapi_module.header_handler = PancakeSAPIHeaderHandler;
	sapi_module.ub_write = PancakeSAPIOutputHandler;
	sapi_module.send_headers = PancakeSAPISendHeaders;
	sapi_module.flush = PancakeSAPIFlush;
	sapi_module.phpinfo_as_text = 0;

	// Set PHP_SAPI constant
	zend_hash_find(EG(zend_constants), "PHP_SAPI", sizeof("PHP_SAPI"), (void**) &PHP_SAPI);
	PHP_SAPI->value.value.str.val = sapi_module.name;
	PHP_SAPI->value.value.str.len = sizeof("pancake") - 1;

	// Hook exceptions
	if(zend_throw_exception_hook) {
		PancakeSAPIPreviousExceptionHook = zend_throw_exception_hook;
	}
	zend_throw_exception_hook = PancakeSAPIExceptionHook;

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
	PANCAKE_SAPI_GLOBALS(autoDeleteFunctions) = Z_TYPE_P(autoDelete) == IS_ARRAY && zend_hash_find(Z_ARRVAL_P(autoDelete), "functions", sizeof("functions"), (void**) &value) == SUCCESS && zend_is_true(*value);
	PANCAKE_SAPI_GLOBALS(autoDeleteClasses) = Z_TYPE_P(autoDelete) == IS_ARRAY && zend_hash_find(Z_ARRVAL_P(autoDelete), "classes", sizeof("classes"), (void**) &value) == SUCCESS && zend_is_true(*value);
	PANCAKE_SAPI_GLOBALS(autoDeleteIncludes) = Z_TYPE_P(autoDelete) == IS_ARRAY && zend_hash_find(Z_ARRVAL_P(autoDelete), "includes", sizeof("includes"), (void**) &value) == SUCCESS && zend_is_true(*value);
	PANCAKE_SAPI_GLOBALS(autoDeleteConstants) = Z_TYPE_P(autoDelete) == IS_ARRAY && zend_hash_find(Z_ARRVAL_P(autoDelete), "constants", sizeof("constants"), (void**) &value) == SUCCESS && zend_is_true(*value);

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

		if(zend_hash_find(Z_ARRVAL_P(autoDeleteExcludes), "classes", sizeof("classes"), (void**) &data) == SUCCESS
		&& Z_TYPE_PP(data) == IS_ARRAY) {
			PANCAKE_SAPI_GLOBALS(autoDeleteClassesExcludes) = PancakeSAPITransformHashTableValuesToKeys(Z_ARRVAL_PP(data));
		}
	}

	// Fetch destruction settings
	PANCAKE_SAPI_GLOBALS(destroyObjects) = (zend_bool) zend_is_true(zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpDestroyObjects", sizeof("phpDestroyObjects") - 1, 0 TSRMLS_CC));
	PANCAKE_SAPI_GLOBALS(cleanUserClassData) = (zend_bool) zend_is_true(zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpCleanUserClassData", sizeof("phpCleanUserClassData") - 1, 0 TSRMLS_CC));
	PANCAKE_SAPI_GLOBALS(cleanUserFunctionData) = (zend_bool) zend_is_true(zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpCleanUserFunctionData", sizeof("phpCleanUserFunctionData") - 1, 0 TSRMLS_CC));

	// Fetch PHPHTMLErrors setting
	HTMLErrors = zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpHTMLErrors", sizeof("phpHTMLErrors") - 1, 0 TSRMLS_CC);
	if(zend_is_true(HTMLErrors)) {
		PG(html_errors) = 1;
	}

	// Fetch document root
	FAST_READ_PROPERTY(PANCAKE_SAPI_GLOBALS(documentRoot), PANCAKE_SAPI_GLOBALS(vHost), "documentRoot", sizeof("documentRoot") - 1, HASH_OF_documentRoot);

	// Fetch processing limit
	processingLimit = zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpWorkerLimit", sizeof("phpWorkerLimit") - 1, 0 TSRMLS_CC);
	if(Z_TYPE_P(processingLimit) == IS_LONG && Z_LVAL_P(processingLimit) > 0) {
		PANCAKE_SAPI_GLOBALS(processingLimit) = Z_LVAL_P(processingLimit);
	} else {
		PANCAKE_SAPI_GLOBALS(processingLimit) = 0;
	}

	// Fetch max_execution_time
	timeout = zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpMaxExecutionTime", sizeof("phpMaxExecutionTime") - 1, 0 TSRMLS_CC);
	if(Z_TYPE_P(timeout) == IS_LONG && Z_LVAL_P(timeout) > 0) {
		PANCAKE_SAPI_GLOBALS(timeout) = Z_LVAL_P(timeout);
	} else {
		PANCAKE_SAPI_GLOBALS(timeout) = 0;
	}

	// Hook some functions
	if(zend_hash_find(EG(function_table), "session_start", sizeof("session_start"), (void**) &function) == SUCCESS) {
		PHP_session_start = function->internal_function.handler;
		function->internal_function.handler = Pancake_session_start;
	}

	zend_hash_find(EG(function_table), "debug_backtrace", sizeof("debug_backtrace"), (void**) &function);
	function->internal_function.handler = Pancake_debug_backtrace;

	zend_hash_find(EG(function_table), "debug_print_backtrace", sizeof("debug_print_backtrace"), (void**) &function);
	PHP_debug_print_backtrace = function->internal_function.handler;
	function->internal_function.handler = Pancake_debug_print_backtrace;

	// Fetch rsrc list destructor
	PHP_list_entry_destructor = EG(regular_list).pDestructor;

	// Destroy uploaded files array
	if(PSG(rfc1867_uploaded_files, HashTable*)) {
		zend_hash_destroy(PSG(rfc1867_uploaded_files, HashTable*));
		FREE_HASHTABLE(PSG(rfc1867_uploaded_files, HashTable*));
		PSG(rfc1867_uploaded_files, HashTable*) = NULL;
	}

	// Reset error handler stack
	zend_stack_destroy(&EG(user_error_handlers_error_reporting));
	zend_stack_init(&EG(user_error_handlers_error_reporting));

	// Fetch Pancake error handler
	PANCAKE_SAPI_GLOBALS(errorHandler) = EG(user_error_handler);
	Z_ADDREF_P(PANCAKE_SAPI_GLOBALS(errorHandler));

	// Reset last errors
	if(PG(last_error_message)) {
		free(PG(last_error_message));
		PG(last_error_message) = NULL;
	}
	if(PG(last_error_file)) {
		free(PG(last_error_file));
		PG(last_error_file) = NULL;
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

	// Fetch PHP version
	zend_hash_find(&module_registry, "core", sizeof("core"), (void**) &core);
	PANCAKE_SAPI_GLOBALS(SAPIPHPVersionHeader_len) = sizeof("X-Powered-By: PHP/") + strlen(core->version) - 1;
	PANCAKE_SAPI_GLOBALS(SAPIPHPVersionHeader) = emalloc(PANCAKE_SAPI_GLOBALS(SAPIPHPVersionHeader_len) + 1);
	memcpy(PANCAKE_SAPI_GLOBALS(SAPIPHPVersionHeader), "X-Powered-By: PHP/", sizeof("X-Powered-By: PHP/") - 1);
	memcpy(PANCAKE_SAPI_GLOBALS(SAPIPHPVersionHeader) + sizeof("X-Powered-By: PHP/") - 1, core->version, strlen(core->version) + 1);

	// chdir() to document root
	chdir(Z_STRVAL_P(PANCAKE_SAPI_GLOBALS(documentRoot)));

	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(PancakeSAPI) {
	zend_ini_entry **ini_entry_ptr;
	zval *INISettings;

	if(PANCAKE_GLOBALS(inSAPIReboot) == 1) {
		return SUCCESS;
	}

	if(PANCAKE_SAPI_GLOBALS(inExecution)) {
		/* We are executing a PHP script which bailed out */
		PANCAKE_SAPI_GLOBALS(inExecution) = 0;

		/* Flush SAPI buffer */
		PancakeSAPIFlush(NULL);

		/* End request */
		write(PANCAKE_SAPI_GLOBALS(clientSocket), "\2", sizeof(char));

		/* Tell Master that we're expecting to die soon */
		write(PANCAKE_SAPI_GLOBALS(controlSocket), "EXPECTED_SHUTDOWN", sizeof("EXPECTED_SHUTDOWN") - 1);
	}

	if(PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes)) {
		zend_hash_destroy(PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes));
		efree(PANCAKE_SAPI_GLOBALS(autoDeleteFunctionsExcludes));
	}

	if(PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes)) {
		zend_hash_destroy(PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes));
		efree(PANCAKE_SAPI_GLOBALS(autoDeleteIncludesExcludes));
	}

	if(PANCAKE_SAPI_GLOBALS(persistentSymbols)) {
		zend_hash_destroy(PANCAKE_SAPI_GLOBALS(persistentSymbols));
		efree(PANCAKE_SAPI_GLOBALS(persistentSymbols));
	}

	if(PancakeSAPIModulePostDeactivateHandlers) {
		efree(PancakeSAPIModulePostDeactivateHandlers);
	}

	INISettings = zend_read_property(NULL, PANCAKE_SAPI_GLOBALS(vHost), "phpINISettings", sizeof("phpINISettings") - 1, 0 TSRMLS_CC);
	if(Z_TYPE_P(INISettings) == IS_ARRAY && zend_hash_num_elements(Z_ARRVAL_P(INISettings))) {
		char *name;
		int name_len;
		zval **value;

		PANCAKE_FOREACH_KEY(Z_ARRVAL_P(INISettings), name, name_len, value) {
			zend_ini_entry *ini_entry;

			if(zend_hash_find(EG(ini_directives), name, name_len, (void **) &ini_entry) == FAILURE) {
				continue;
			}

			if(!ini_entry->modified) {
				efree(ini_entry->value);
			}
		}
	}

	efree(PANCAKE_SAPI_GLOBALS(SAPIPHPVersionHeader));

	return SUCCESS;
}

PHP_FUNCTION(SetErrorHandling) {
	long mode;
	zend_class_entry *exception = NULL;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l|C", &mode, &exception) == FAILURE) {
		RETURN_FALSE;
	}

	if(mode != EH_THROW && mode != EH_NORMAL) {
		zend_error(E_WARNING, "Mode must be either Pancake\\EH_NORMAL or Pancake\\EH_THROW");
		RETURN_FALSE;
	}

	if(exception && !instanceof_function(exception, zend_exception_get_default(TSRMLS_C) TSRMLS_CC)) {
		zend_error(E_WARNING, "Class must be derived from \\Exception");
		RETURN_FALSE;
	}

	EG(error_handling) = mode;
	EG(exception_class) = exception;

	RETURN_TRUE;
}

static int PancakeSAPIMarkConstantPersistent(zend_constant *constant TSRMLS_DC) {
	constant->flags |= PANCAKE_PSEUDO_PERSISTENT;
	return ZEND_HASH_APPLY_KEEP;
}

PHP_FUNCTION(SAPIPrepare) {
	struct epoll_event event = {0}, event2 = {0};

	if(PANCAKE_SAPI_GLOBALS(epoll)) {
		RETURN_FALSE;
	}

	PANCAKE_SAPI_GLOBALS(epoll) = epoll_create(3);

	event.events = EPOLLIN | EPOLLRDHUP;
	event.data.fd = PANCAKE_SAPI_GLOBALS(listenSocket);
	epoll_ctl(PANCAKE_SAPI_GLOBALS(epoll), EPOLL_CTL_ADD, PANCAKE_SAPI_GLOBALS(listenSocket), &event);

	event2.events = EPOLLIN | EPOLLRDHUP;
	event2.data.fd = PANCAKE_SAPI_GLOBALS(controlSocket);
	epoll_ctl(PANCAKE_SAPI_GLOBALS(epoll), EPOLL_CTL_ADD, PANCAKE_SAPI_GLOBALS(controlSocket), &event2);

	PANCAKE_SAPI_GLOBALS(clientSocket) = -1;

	// Reset header linked list
	zend_llist_destroy(&SG(sapi_headers).headers);
	zend_llist_init(&SG(sapi_headers).headers, sizeof(sapi_header_struct), (void (*)(void *)) sapi_free_header, 0);

	// Fetch amount of currently existing functions
	if(PANCAKE_SAPI_GLOBALS(autoDeleteFunctions)) {
		PANCAKE_SAPI_GLOBALS(functionsPre) = EG(function_table)->nNumOfElements;
	}

	// Fetch currently included files
	if(PANCAKE_SAPI_GLOBALS(autoDeleteIncludes)) {
		PANCAKE_SAPI_GLOBALS(includesPre) = EG(included_files).nNumOfElements;
	}

	// Fetch classes
	if(PANCAKE_SAPI_GLOBALS(autoDeleteClasses)) {
		PANCAKE_SAPI_GLOBALS(classesPre) = EG(class_table)->nNumOfElements;
	}

	// Mark all constants persistent (so that we can do faster auto-deletion later on)
	zend_hash_apply(EG(zend_constants), (apply_func_t) PancakeSAPIMarkConstantPersistent TSRMLS_CC);

	PancakeSAPIGlobalsPrepare(TSRMLS_C);
}

static zend_bool PancakeSAPIInitializeRequest(zval *request TSRMLS_DC) {
	zval *requestFilePath, *queryString;
	char *directory;

	PANCAKE_SAPI_GLOBALS(request) = request;

	PANCAKE_SAPI_GLOBALS(inExecution) = 1;

	// X-Powered-By
	if (PG(expose_php)) {
		sapi_add_header(PANCAKE_SAPI_GLOBALS(SAPIPHPVersionHeader), PANCAKE_SAPI_GLOBALS(SAPIPHPVersionHeader_len), 1);
	}

	// Set request info data
	FAST_READ_PROPERTY(queryString, request, "queryString", sizeof("queryString") - 1, HASH_OF_queryString);
	SG(request_info).query_string = Z_STRVAL_P(queryString);

#if PHP_MINOR_VERSION < 5
	// Handle PHP UUID queries
	if(php_handle_special_queries(TSRMLS_C)) {
		return 0;
	}
#endif

	// Switch to correct directory
	FAST_READ_PROPERTY(requestFilePath, request, "requestFilePath", sizeof("requestFilePath") - 1, HASH_OF_requestFilePath);
	directory = emalloc(Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)) + Z_STRLEN_P(requestFilePath));
	memcpy(directory, Z_STRVAL_P(requestFilePath), Z_STRLEN_P(requestFilePath) + 1);
	dirname(directory);
	memmove(directory + Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)), directory, strlen(directory) + 1);
	memcpy(directory, Z_STRVAL_P(PANCAKE_SAPI_GLOBALS(documentRoot)), Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)));
	chdir(directory);
	efree(directory);

	EG(user_error_handler) = NULL;

	// Reset last errors
	if (PG(last_error_message)) {
		free(PG(last_error_message));
		PG(last_error_message) = NULL;
	}
	if (PG(last_error_file)) {
		free(PG(last_error_file));
		PG(last_error_file) = NULL;
	}

	if(PANCAKE_SAPI_GLOBALS(timeout)) {
		zend_set_timeout(PANCAKE_SAPI_GLOBALS(timeout), 1);
	}

	if (PG(output_handler) && PG(output_handler)[0]) {
		zval *oh;

		MAKE_STD_ZVAL(oh);
		ZVAL_STRING(oh, PG(output_handler), 1);
		php_output_start_user(oh, 0, PHP_OUTPUT_HANDLER_STDFLAGS TSRMLS_CC);
		zval_ptr_dtor(&oh);
	} else if (PG(output_buffering)) {
		php_output_start_user(NULL, PG(output_buffering) > 1 ? PG(output_buffering) : 0, PHP_OUTPUT_HANDLER_STDFLAGS TSRMLS_CC);
	} else if (PG(implicit_flush)) {
		php_output_set_implicit_flush(1 TSRMLS_CC);
	}

	zend_activate_auto_globals(TSRMLS_C);
	return 1;
}

PHP_FUNCTION(SAPICodeCachePrepare) {
	if(zend_hash_num_elements(&EG(symbol_table))) {
		// We probably have persistent symbols
		zval **value;
		char *key;
		uint key_len;

		ALLOC_HASHTABLE(PANCAKE_SAPI_GLOBALS(persistentSymbols));
		zend_hash_init(PANCAKE_SAPI_GLOBALS(persistentSymbols), 0, NULL, NULL, 0);

		PANCAKE_FOREACH_KEY(&EG(symbol_table), key, key_len, value) {
			// Ignore auto globals
			if(zend_hash_quick_exists(CG(auto_globals), key, key_len, EG(symbol_table).pInternalPointer->h)) {
				continue;
			}

			zend_hash_add_empty_element(PANCAKE_SAPI_GLOBALS(persistentSymbols), key, key_len);
		}

		// It might happen that only the auto globals existed, in this case we don't need the table
		if(!zend_hash_num_elements(PANCAKE_SAPI_GLOBALS(persistentSymbols))) {
			zend_hash_destroy(PANCAKE_SAPI_GLOBALS(persistentSymbols));
			FREE_HASHTABLE(PANCAKE_SAPI_GLOBALS(persistentSymbols));
			PANCAKE_SAPI_GLOBALS(persistentSymbols) = NULL;
		}
	}

	PANCAKE_SAPI_GLOBALS(CodeCache) = 1;
}

static int zval_call_destructor(zval **zv TSRMLS_DC) {
	if(EG(exception)) {
		return ZEND_HASH_APPLY_STOP;
	} else if (Z_TYPE_PP(zv) == IS_OBJECT && Z_REFCOUNT_PP(zv) == 1) {
		return ZEND_HASH_APPLY_REMOVE;
	} else {
		return ZEND_HASH_APPLY_KEEP;
	}
}

static int PancakeSAPIClearRuntimeCache(zend_function *func TSRMLS_DC) {
	if(func->type != ZEND_USER_FUNCTION) {
		return ZEND_HASH_APPLY_STOP;
	}

	if(func->op_array.last_cache_slot == 0 || func->op_array.run_time_cache == NULL) {
		return ZEND_HASH_APPLY_KEEP;
	}

	memset(func->op_array.run_time_cache, 0, (func->op_array.last_cache_slot) * sizeof(void*));
	return ZEND_HASH_APPLY_KEEP;
}

static int PancakeSAPIClearClassRuntimeCache(zend_class_entry **class TSRMLS_DC) {
	if((*class)->type != ZEND_USER_CLASS) {
		return ZEND_HASH_APPLY_STOP;
	}

	zend_hash_apply(&(*class)->function_table, (apply_func_t) PancakeSAPIClearRuntimeCache TSRMLS_CC);
	return ZEND_HASH_APPLY_KEEP;
}

static void PancakeSAPIMarkObjectsStoreDestructed(TSRMLS_D) {
	zend_uint i;

	if (!EG(objects_store).object_buckets) {
		return;
	}
	for (i = 1; i < EG(objects_store).top ; i++) {
		if (EG(objects_store).object_buckets[i].valid) {
			zend_object *object = (zend_object*) EG(objects_store).object_buckets[i].bucket.obj.object;

			if(object->ce->name[0] == 'P' && !strncmp(object->ce->name, "Pancake\\", sizeof("Pancake\\") - 1)) {
				continue;
			}

			EG(objects_store).object_buckets[i].destructor_called = 1;
		}
	}
}

PHP_FUNCTION(SAPIFinishRequest) {
	zval *answerCode, *zeh;
	int symbols, i;
	// Since Pancake currently can't recover from fatal errors anyway we don't use zend_try

	// Disable tick functions
	zend_llist_clean(&PG(tick_functions));

	// Call registered shutdown functions
	php_call_shutdown_functions(TSRMLS_C);

	// Call destructors
	do {
		symbols = zend_hash_num_elements(&EG(symbol_table));
		zend_hash_reverse_apply(&EG(symbol_table), (apply_func_t) zval_call_destructor TSRMLS_CC);

		if(EG(exception)) {
			PancakeSAPIMarkObjectsStoreDestructed(TSRMLS_C);

			/* We have an exception and should bailout */
			if(!strcmp("Pancake\\ExitException", Z_OBJ_P(EG(exception))->ce->name)) {
				/* Discard exception */
				zend_clear_exception(TSRMLS_C);
				goto destructionDone;
			}

			/* This isn't going to be cleaned */
			Z_DELREF_P(PANCAKE_SAPI_GLOBALS(errorHandler));
			zval_ptr_dtor(&PANCAKE_SAPI_GLOBALS(errorHandler));
			return;
		}
	} while (symbols != zend_hash_num_elements(&EG(symbol_table)));

	if(PANCAKE_SAPI_GLOBALS(destroyObjects)) {
		for(i = 1; i < EG(objects_store).top; i++) {
			if (EG(objects_store).object_buckets[i].valid) {
				struct _store_object *obj = &EG(objects_store).object_buckets[i].bucket.obj;


				if (!EG(objects_store).object_buckets[i].destructor_called) {
					if (obj->dtor && obj->object) {
						zend_object *object = (zend_object*) obj->object;

						if(object->ce->name[0] == 'P' && !strncmp(object->ce->name, "Pancake\\", sizeof("Pancake\\") - 1)) {
							continue;
						}

						EG(objects_store).object_buckets[i].destructor_called = 1;

						obj->refcount++;
						obj->dtor(obj->object, i TSRMLS_CC);
						obj = &EG(objects_store).object_buckets[i].bucket.obj;
						obj->refcount--;

						if(EG(exception)) {
							PancakeSAPIMarkObjectsStoreDestructed(TSRMLS_C);

							/* We have an exception and should bailout */
							if(!strcmp("Pancake\\ExitException", Z_OBJ_P(EG(exception))->ce->name)) {
								/* Discard exception */
								zend_clear_exception(TSRMLS_C);
								goto destructionDone;
							}

							/* This isn't going to be cleaned */
							Z_DELREF_P(PANCAKE_SAPI_GLOBALS(errorHandler));
							zval_ptr_dtor(&PANCAKE_SAPI_GLOBALS(errorHandler));
							return;
						}
					} else {
						EG(objects_store).object_buckets[i].destructor_called = 1;
					}
				}
			}
		}
	}

	destructionDone:

	// Flush all output buffers
	php_output_end_all(TSRMLS_C);

	// Disable time limit
	zend_unset_timeout(TSRMLS_C);

	// Tell Pancake not to shutdown
	PANCAKE_GLOBALS(inSAPIReboot) = 1;

	// Run RSHUTDOWN for modules
	zend_hash_reverse_apply(&module_registry, (apply_func_t) PancakeSAPIShutdownModule TSRMLS_CC);

	// Disable output layer
	php_output_deactivate(TSRMLS_C);

	// Flush buffers
	PancakeSAPIFlush(NULL);

	// End request
	write(PANCAKE_SAPI_GLOBALS(clientSocket), "\2", sizeof(char));

	// We have finished executing the script
	PANCAKE_SAPI_GLOBALS(inExecution) = 0;
	SG(headers_sent) = 0;
	PANCAKE_SAPI_GLOBALS(haveChangedHeaders) = 0;

	// Reset error handling
	EG(error_handling) = EH_NORMAL;
	EG(exception_class) = NULL;

	// Have we reached the processing limit or should we exit?
	if((PANCAKE_SAPI_GLOBALS(processingLimit)
	&& ++PANCAKE_SAPI_GLOBALS(processedRequests) >= PANCAKE_SAPI_GLOBALS(processingLimit))
	|| PANCAKE_SAPI_GLOBALS(exit)) {
		PANCAKE_GLOBALS(inSAPIReboot) = 0;
		write(PANCAKE_SAPI_GLOBALS(controlSocket), "EXPECTED_SHUTDOWN", sizeof("EXPECTED_SHUTDOWN") - 1);
		zend_bailout();
	}

	// Enable output layer
	php_output_activate(TSRMLS_C);

	// Free error information
	if(PG(last_error_message)) {
		free(PG(last_error_message));
		PG(last_error_message) = NULL;
	}
	if(PG(last_error_file)) {
		free(PG(last_error_file));
		PG(last_error_file) = NULL;
	}

	if(PANCAKE_SAPI_GLOBALS(persistentSymbols) == NULL) {
		// Clean global variables
		zend_hash_graceful_reverse_destroy(&EG(symbol_table));
		// Zend uses a default size of 50 for the symbol table, we tweak this a little bit
		zend_hash_init(&EG(symbol_table), 100, NULL, ZVAL_PTR_DTOR, 0);
	} else {
		Bucket *p;

		p = EG(symbol_table).pListTail;
		while (p != NULL) {
			if(!zend_hash_quick_exists(PANCAKE_SAPI_GLOBALS(persistentSymbols), p->arKey, p->nKeyLength, p->h)) {
				Bucket *q = p;
				p = p->pListLast;
				zend_hash_quick_del(&EG(symbol_table), q->arKey, q->nKeyLength, q->h);
			} else {
				p = p->pListLast;
			}
		}
	}

	// PHP now calls zend_deactivate() - we will just do the necessary things
	if(EG(user_error_handler)) {
		zeh = EG(user_error_handler);
		EG(user_error_handler) = NULL;
		zval_dtor(zeh);
		FREE_ZVAL(zeh);
	}

	if(EG(user_exception_handler)) {
		zeh = EG(user_exception_handler);
		EG(user_exception_handler) = NULL;
		zval_dtor(zeh);
		FREE_ZVAL(zeh);
	}

	zend_stack_destroy(&EG(user_error_handlers_error_reporting));
	zend_stack_init(&EG(user_error_handlers_error_reporting));
	zend_ptr_stack_clean(&EG(user_error_handlers), ZVAL_DESTRUCTOR, 1);
	zend_ptr_stack_clean(&EG(user_exception_handlers), ZVAL_DESTRUCTOR, 1);

	// Destroy autoload table
	if(EG(in_autoload)) {
		zend_hash_destroy(EG(in_autoload));
		FREE_HASHTABLE(EG(in_autoload));
		EG(in_autoload) = NULL;
	}

	// Set Pancake error handler
	EG(user_error_handler) = PANCAKE_SAPI_GLOBALS(errorHandler);
	EG(user_error_handler_error_reporting) = E_ALL;

	// Cleanup class and function data
	if(PANCAKE_SAPI_GLOBALS(cleanUserFunctionData)) {
		zend_hash_reverse_apply(EG(function_table), (apply_func_t) zend_cleanup_function_data TSRMLS_CC);
	}
	if(PANCAKE_SAPI_GLOBALS(cleanUserClassData)) {
		zend_hash_reverse_apply(EG(class_table), (apply_func_t) zend_cleanup_user_class_data TSRMLS_CC);
	}
	zend_cleanup_internal_classes(TSRMLS_C);

	if(EG(regular_list).nNumOfElements) {
		// Destroy resource list
		zend_hash_graceful_reverse_destroy(&EG(regular_list));
		// See zend_init_rsrc_list()
		zend_hash_init(&EG(regular_list), 0, NULL, PHP_list_entry_destructor, 0);
		EG(regular_list).nNextFreeElement = 1;

		// Open fake fds
		if(fcntl(0, F_GETFD) == -1) {
			open("/dev/null", 0);
		}

		if(fcntl(1, F_GETFD) == -1) {
			open("/dev/null", 0);
		}

		if(fcntl(2, F_SETFD) == -1) {
			open("/dev/null", 0);
		}
	}

	// Restore ini entries
	zend_try {
		zend_ini_deactivate(TSRMLS_C);
	} zend_end_try();

	// Call post_deactivate handlers
	if(PancakeSAPIModulePostDeactivateHandlers) {
		zend_module_entry **p = PancakeSAPIModulePostDeactivateHandlers;

		while (*p) {
			zend_module_entry *module = *p;

			module->post_deactivate_func();
			p++;
		}
	}

	// Shutdown stream hashes
	if (FG(stream_wrappers)) {
		zend_hash_destroy(FG(stream_wrappers));
		efree(FG(stream_wrappers));
		FG(stream_wrappers) = NULL;
	}

	if (FG(stream_filters)) {
		zend_hash_destroy(FG(stream_filters));
		efree(FG(stream_filters));
		FG(stream_filters) = NULL;
	}

    if (FG(wrapper_errors)) {
		zend_hash_destroy(FG(wrapper_errors));
		efree(FG(wrapper_errors));
		FG(wrapper_errors) = NULL;
    }

    // SAPI reset
    PSG(callback_run, zend_bool) = 0;
    if(PSG(callback_func, zval*)) {
		zval_ptr_dtor(&PSG(callback_func, zval*));
		PSG(callback_func, zval*) = NULL;
	}
	zend_llist_destroy(&SG(sapi_headers).headers);
	zend_llist_init(&SG(sapi_headers).headers, sizeof(sapi_header_struct), (void (*)(void *)) sapi_free_header, 0);

	// Reset executor
	EG(ticks_count) = 0;

	// Initialize modules again
	zend_hash_reverse_apply(&module_registry, (apply_func_t) PancakeSAPIStartupModule TSRMLS_CC);
	PANCAKE_GLOBALS(inSAPIReboot) = 0;

	// Destroy constants
	if(PANCAKE_SAPI_GLOBALS(autoDeleteConstants)) {
		zend_hash_reverse_apply(EG(zend_constants), (apply_func_t) PancakeSAPICleanNonPersistentConstant TSRMLS_CC);
	}

	// Destroy uploaded files array
	if(PSG(rfc1867_uploaded_files, HashTable*)) {
		zend_hash_apply(PSG(rfc1867_uploaded_files, HashTable*), (apply_func_t) PancakeSAPIUnlinkFile TSRMLS_CC);
		zend_hash_destroy(PSG(rfc1867_uploaded_files, HashTable*));
		FREE_HASHTABLE(PSG(rfc1867_uploaded_files, HashTable*));
		PSG(rfc1867_uploaded_files, HashTable*) = NULL;
	}

	// Reset autoloading
	EG(autoload_func) = NULL;

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
				zend_hash_move_backwards(EG(function_table));
				continue;
			}

			PANCAKE_SAPI_GLOBALS(haveCriticalDeletions) = 1;
			zend_hash_quick_del(EG(function_table), functionName, functionName_len, EG(function_table)->pInternalPointer->h);
			zend_hash_internal_pointer_end(EG(function_table));
		}
	}

	PANCAKE_SAPI_GLOBALS(functionsPre) = EG(function_table)->nNumOfElements;

	// Destroy classes
	if(PANCAKE_SAPI_GLOBALS(autoDeleteClasses) && EG(class_table)->nNumOfElements > PANCAKE_SAPI_GLOBALS(classesPre)) {
		char *className;
		uint className_len;
		int iterationCount = EG(class_table)->nNumOfElements - PANCAKE_SAPI_GLOBALS(classesPre);

		for(zend_hash_internal_pointer_end_ex(EG(class_table), NULL);
			iterationCount--
			&& zend_hash_get_current_key_ex(EG(class_table), &className, &className_len, NULL, 0, NULL) == HASH_KEY_IS_STRING;) {
			if(PANCAKE_SAPI_GLOBALS(autoDeleteClassesExcludes)
			&&	zend_hash_quick_exists(PANCAKE_SAPI_GLOBALS(autoDeleteClassesExcludes), className, className_len, EG(class_table)->pInternalPointer->h)) {
				zend_hash_move_backwards(EG(class_table));
				continue;
			}

			PANCAKE_SAPI_GLOBALS(haveCriticalDeletions) = 1;
			zend_hash_quick_del(EG(class_table), className, className_len, EG(class_table)->pInternalPointer->h);
			zend_hash_internal_pointer_end(EG(class_table));
		}
	}

	PANCAKE_SAPI_GLOBALS(classesPre) = EG(class_table)->nNumOfElements;

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

	// In case elements whose pointers may have been cached have been destroyed we need to reset the runtime cache (only when CodeCache is used)
	if(PANCAKE_SAPI_GLOBALS(CodeCache) && PANCAKE_SAPI_GLOBALS(haveCriticalDeletions)) {
		zend_hash_reverse_apply(EG(function_table), (apply_func_t) PancakeSAPIClearRuntimeCache TSRMLS_CC);
		zend_hash_reverse_apply(EG(class_table), (apply_func_t) PancakeSAPIClearClassRuntimeCache TSRMLS_CC);
	}

	PANCAKE_SAPI_GLOBALS(haveCriticalDeletions) = 0;
}

static void PancakeSAPIReadHeaderSet(int fd, zval **headerArray TSRMLS_DC) {
	int numHeaders = 0, i, bufSize, offset = sizeof(int);
	char *buf;

	zval_ptr_dtor(headerArray);
	MAKE_STD_ZVAL(*headerArray);

	read(fd, &bufSize, sizeof(int));

	if(!bufSize) {
		array_init_size(*headerArray, 2);
		return;
	}

	buf = emalloc(bufSize);
	read(fd, buf, bufSize);

	memcpy(&numHeaders, buf, sizeof(int));

	array_init_size(*headerArray, numHeaders);

	if(!numHeaders) {
		efree(buf);
		return;
	}

	for(i = 0;i < numHeaders;i++) {
		int key_len, keyOffset;
		zval *value;

		MAKE_STD_ZVAL(value);
		Z_TYPE_P(value) = IS_STRING;

		memcpy(&key_len, buf + offset, sizeof(int));
		keyOffset = (offset += sizeof(int));
		offset += key_len;

		memcpy(&Z_STRLEN_P(value), buf + offset, sizeof(int));
		Z_STRVAL_P(value) = emalloc(Z_STRLEN_P(value) + 1);
		offset += sizeof(int);

		memcpy(Z_STRVAL_P(value), buf + offset, Z_STRLEN_P(value));
		Z_STRVAL_P(value)[Z_STRLEN_P(value)] = '\0';
		offset += Z_STRLEN_P(value);

		zend_hash_add(Z_ARRVAL_PP(headerArray), &buf[keyOffset], key_len, (void*) &value, sizeof(zval*), NULL);
	}

	efree(buf);
}

zend_bool PancakeSAPIFetchRequest(int fd, zval *return_value TSRMLS_DC) {
	ssize_t length, offset = 0;
	unsigned char *buf;
	int i = 0, numHeaders = 0;
	zend_object *zobj;

	read(fd, &length, sizeof(ssize_t));
	buf = emalloc(length);
	while(offset < length) {
		ssize_t readLength = read(fd, &buf[offset], length - offset);

		if(readLength == -1) {
			efree(buf);
			close(fd);
			return 0;
		}

		offset += readLength;
	}

	object_init_ex(return_value, HTTPRequest_ce);
	zobj = Z_OBJ_P(return_value);

	offset = 0;

	for(i = 0;i <= PANCAKE_HTTP_REQUEST_LAST_RECV_SCALAR_OFFSET;i++) {
		/* Read properties */
		zval *property;
		zval **variable_ptr = &zobj->properties_table[i];

		ALLOC_ZVAL(property);
		memcpy(property, &buf[offset], sizeof(zval) - sizeof(zend_uchar));
		offset += sizeof(zval) - sizeof(zend_uchar);
		property->is_ref__gc = 0;
		property->refcount__gc = 1;

		/* Fetch string value */
		if(i >= PANCAKE_HTTP_REQUEST_FIRST_STRING_OFFSET) {
			Z_STRVAL_P(property) = emalloc(Z_STRLEN_P(property) + 1);
			memcpy(Z_STRVAL_P(property), &buf[offset], Z_STRLEN_P(property));
			offset += Z_STRLEN_P(property);
			Z_STRVAL_P(property)[Z_STRLEN_P(property)] = '\0';
		}

		zval_ptr_dtor(variable_ptr);
		*variable_ptr = property;
	}

	efree(buf);

	PancakeSAPIReadHeaderSet(fd, &zobj->properties_table[PANCAKE_HTTP_REQUEST_REQUEST_HEADERS_OFFSET] TSRMLS_CC);
	PancakeSAPIReadHeaderSet(fd, &zobj->properties_table[PANCAKE_HTTP_REQUEST_ANSWER_HEADERS_OFFSET] TSRMLS_CC);

	if(!PancakeSAPIInitializeRequest(return_value TSRMLS_CC)) {
		// Reload output layer
		php_output_end_all(TSRMLS_C);
		php_output_deactivate(TSRMLS_C);
		php_output_activate(TSRMLS_C);

		// Flush SAPI buffer
		PancakeSAPIFlush(NULL);

		write(fd, "\2", sizeof(char));

		SG(headers_sent) = 0;
		zval_dtor(return_value);
		return 0;
	}

	return 1;
}

PHP_FUNCTION(SAPIWait) {
	struct epoll_event events[1];

	wait:
	if(epoll_wait(PANCAKE_SAPI_GLOBALS(epoll), events, 1, -1) == -1)
		goto wait;

	if(events[0].data.fd == PANCAKE_SAPI_GLOBALS(listenSocket)) {
		/* Incoming SAPI connection */
		int fd = accept(events[0].data.fd, NULL, NULL);
		struct epoll_event event = {0};

		if(fd == -1) { /* We might get -1 when another worker was faster */
			goto wait;
		}

		event.events = EPOLLIN | EPOLLRDHUP;
		event.data.fd = PANCAKE_SAPI_GLOBALS(clientSocket) = fd;
		epoll_ctl(PANCAKE_SAPI_GLOBALS(epoll), EPOLL_CTL_ADD, fd, &event);

		if(PancakeSAPIFetchRequest(fd, return_value TSRMLS_CC)) {
			return;
		} else {
			goto wait;
		}
	} else if(events[0].data.fd == PANCAKE_SAPI_GLOBALS(controlSocket)) {
		/* Pancake master control instruction */
		if(events[0].events & EPOLLRDHUP) {
			/* Master closed connection, this should not happen - probably we should exit */
			close(PANCAKE_SAPI_GLOBALS(controlSocket));
			RETURN_FALSE;
		} else {
			char *buf = emalloc(128);
			int length;

			length = read(PANCAKE_SAPI_GLOBALS(controlSocket), buf, 127);

			if(length <= 0) {
				efree(buf);
				RETURN_FALSE;
			}

			buf[length] = '\0';

			if(!strcmp(buf, "GRACEFUL_SHUTDOWN")) {
				/* Master wants us to shutdown */
				efree(buf);
				RETURN_FALSE;
			} else if(!strcmp(buf, "LOAD_FILE_POINTERS")) {
				PancakeLoadFilePointers(TSRMLS_C);
			}

			efree(buf);
			goto wait;
		}
	} else {
		/* Keep-Alive SAPI connection */
		if(events[0].events & EPOLLRDHUP) {
			/* SAPIClient closed connection */
			close(events[0].data.fd);
			PANCAKE_SAPI_GLOBALS(clientSocket) = -1;
			goto wait;
		}

		PANCAKE_SAPI_GLOBALS(clientSocket) = events[0].data.fd;

		if(PancakeSAPIFetchRequest(events[0].data.fd, return_value TSRMLS_CC)) {
			return;
		} else {
			goto wait;
		}
	}
}

PHP_FUNCTION(SAPIExitHandler) {
	zval *exitmsg;

	zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|z", &exitmsg);

	if(ZEND_NUM_ARGS() && Z_TYPE_P(exitmsg) != IS_LONG) {
		zend_print_variable(exitmsg);
	}

	RETURN_BOOL(!PANCAKE_SAPI_GLOBALS(inExecution));
}
