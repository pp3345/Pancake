
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
	ZEND_NS_FE("Pancake", SAPIFinishRequest, NULL)
	ZEND_NS_FE("Pancake", SAPIPostRequestCleanup, NULL)
	ZEND_NS_FE("Pancake", SAPIWait, NULL)
	ZEND_NS_FE("Pancake", SAPIExitHandler, NULL)
	ZEND_FE(apache_child_terminate, NULL)
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
	PANCAKE_SAPI_GLOBALS(processedRequests) = 0;
	PANCAKE_SAPI_GLOBALS(exit) = 0;

	return SUCCESS;
}

static int PancakeSAPISendHeaders(sapi_headers_struct *sapi_headers TSRMLS_DC) {
	zval *answerHeaders;
	php_serialize_data_t varHash;
	smart_str buf = {0};
	size_t offset = 0;
	short responseCode = (short) sapi_headers->http_response_code;

	if(!PANCAKE_SAPI_GLOBALS(inExecution)) {
		return SUCCESS;
	}

	FAST_READ_PROPERTY(answerHeaders, PANCAKE_SAPI_GLOBALS(request), "answerHeaders", sizeof("answerHeaders") - 1, HASH_OF_answerHeaders);

	PHP_VAR_SERIALIZE_INIT(varHash);
	php_var_serialize(&buf, &answerHeaders, &varHash TSRMLS_CC);
	PHP_VAR_SERIALIZE_DESTROY(varHash);

	/* Write packet type */
	if(write(PANCAKE_SAPI_GLOBALS(clientSocket), "\0", sizeof(char)) == -1) {
		/* What do we do here? */
		return SAPI_HEADER_SEND_FAILED;
	}

	/* Write header length */
	write(PANCAKE_SAPI_GLOBALS(clientSocket), &buf.len, sizeof(size_t));

	/* Write headers */
	while(offset < buf.len) {
		ssize_t result = write(PANCAKE_SAPI_GLOBALS(clientSocket), &buf.c[offset], buf.len - offset);
		if(result == -1) {
			/* What do we do here? */
			return SAPI_HEADER_SEND_FAILED;
		}

		offset += result;
	}

	/* Write status code */
	write(PANCAKE_SAPI_GLOBALS(clientSocket), &responseCode, sizeof(short));

	if(sapi_headers->http_status_line && strlen(sapi_headers->http_status_line) >= sizeof("HTTP/1.0 200 ") && sapi_headers->http_status_line[12] == ' ') {
		char *answerCodeString = &(SG(sapi_headers).http_status_line[13]);
		short statusLineLength = (short) strlen(answerCodeString);
		offset = 0;

		write(PANCAKE_SAPI_GLOBALS(clientSocket), &statusLineLength, sizeof(short));

		while(offset < statusLineLength) {
			ssize_t result = write(PANCAKE_SAPI_GLOBALS(clientSocket), &answerCodeString[offset], statusLineLength - offset);
			if(result == -1) {
				/* What do we do here? */
				return SAPI_HEADER_SEND_FAILED;
			}

			offset += result;
		}
	} else {
		/* Write 0 length */
		write(PANCAKE_SAPI_GLOBALS(clientSocket), "\0", sizeof(short));
	}

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
	unsigned int offset = 0;

	if(!PANCAKE_SAPI_GLOBALS(inExecution) || !str_length) {
		return SUCCESS;
	}

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
	zval *disabledFunctions, *autoDelete, *autoDeleteExcludes, *HTMLErrors, *documentRoot, *processingLimit;

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

	// Reset error handler stack
	zend_stack_destroy(&EG(user_error_handlers_error_reporting));
	zend_stack_init(&EG(user_error_handlers_error_reporting));

	// Fetch Pancake error handler
	PANCAKE_SAPI_GLOBALS(errorHandler) = EG(user_error_handler);
	Z_ADDREF_P(PANCAKE_SAPI_GLOBALS(errorHandler));

	// Reset last errors
	if (PG(last_error_message)) {
		free(PG(last_error_message));
		PG(last_error_message) = NULL;
	}
	if (PG(last_error_file)) {
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

	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(PancakeSAPI) {
	if(PANCAKE_GLOBALS(inSAPIReboot) == 1) {
		return SUCCESS;
	}

	if(PANCAKE_SAPI_GLOBALS(inExecution)) {
		/* We are executing a PHP script which bailed out */
		PANCAKE_SAPI_GLOBALS(inExecution) = 0;

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

	return SUCCESS;
}

PHP_FUNCTION(SAPIPrepare) {
	struct epoll_event event = {0}, event2 = {0};

	if(PANCAKE_SAPI_GLOBALS(epoll)
	|| zend_parse_parameters(ZEND_NUM_ARGS(), "ll", &PANCAKE_SAPI_GLOBALS(listenSocket), &PANCAKE_SAPI_GLOBALS(controlSocket)) == FAILURE) {
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

	// Fetch amount of currently existing functions
	if(PANCAKE_SAPI_GLOBALS(autoDeleteFunctions)) {
		PANCAKE_SAPI_GLOBALS(functionsPre) = EG(function_table)->nNumOfElements;
	}

	// Fetch currently included files
	if(PANCAKE_SAPI_GLOBALS(autoDeleteIncludes)) {
		PANCAKE_SAPI_GLOBALS(includesPre) = EG(included_files).nNumOfElements;
	}
}

static void PancakeSAPIInitializeRequest(zval *request) {
	zval *requestFilePath;
	char *directory;

	PANCAKE_GLOBALS(JITGlobalsHTTPRequest) = PANCAKE_SAPI_GLOBALS(request) = request;

	// Switch to correct directory
	FAST_READ_PROPERTY(requestFilePath, request, "requestFilePath", sizeof("requestFilePath") - 1, HASH_OF_requestFilePath);
	directory = emalloc(Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)) + Z_STRLEN_P(requestFilePath));
	memcpy(directory, Z_STRVAL_P(requestFilePath), Z_STRLEN_P(requestFilePath) + 1);
	dirname(directory);
	memmove(directory + Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)), directory, strlen(directory) + 1);
	memcpy(directory, Z_STRVAL_P(PANCAKE_SAPI_GLOBALS(documentRoot)), Z_STRLEN_P(PANCAKE_SAPI_GLOBALS(documentRoot)));
	chdir(directory);
	efree(directory);

	PANCAKE_SAPI_GLOBALS(inExecution) = 1;

	if (PG(expose_php)) {
		sapi_add_header(SAPI_PHP_VERSION_HEADER, sizeof(SAPI_PHP_VERSION_HEADER)-1, 1);
	}

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

	zend_activate_auto_globals(TSRMLS_C);
}

PHP_FUNCTION(SAPIFinishRequest) {
	zval *answerCode;

	// Disable tick functions
	zend_llist_clean(&PG(tick_functions));

	zend_try {
		php_call_shutdown_functions(TSRMLS_C);
	} zend_end_try();

	php_output_end_all(TSRMLS_C);
	php_output_deactivate(TSRMLS_C);
	PANCAKE_SAPI_GLOBALS(inExecution) = 0;
	SG(headers_sent) = 0;

	write(PANCAKE_SAPI_GLOBALS(clientSocket), "\2", sizeof(char));

	if((PANCAKE_SAPI_GLOBALS(processingLimit)
	&& ++PANCAKE_SAPI_GLOBALS(processedRequests) >= PANCAKE_SAPI_GLOBALS(processingLimit))
	|| PANCAKE_SAPI_GLOBALS(exit)) {
		write(PANCAKE_SAPI_GLOBALS(controlSocket), "EXPECTED_SHUTDOWN", sizeof("EXPECTED_SHUTDOWN") - 1);
		zend_bailout();
	}
}

zend_bool PancakeSAPIFetchRequest(int fd, zval *return_value) {
	size_t length, offset = 0;
	unsigned char *buf, *bufP;
	php_unserialize_data_t varHash;
	zval *HTTPRequest;

	read(fd, &length, sizeof(size_t));
	buf = emalloc(length + 1);
	buf[length] = '\0';

	while(offset < length) {
		size_t readLength = read(fd, &buf[offset], length - offset);
		if(readLength == -1) {
			efree(buf);
			close(fd);
			return FAILURE;
		}

		offset += readLength;
	}

	MAKE_STD_ZVAL(HTTPRequest);

	bufP = buf;

	PHP_VAR_UNSERIALIZE_INIT(varHash);
	if (!php_var_unserialize(&HTTPRequest, (const unsigned char**) &buf, buf + length, &varHash TSRMLS_CC)) {
		/* Malformed value */
		PHP_VAR_UNSERIALIZE_DESTROY(varHash);
		zval_ptr_dtor(&HTTPRequest);
		close(fd);
		efree(bufP);

		return FAILURE;
	}
	PHP_VAR_UNSERIALIZE_DESTROY(varHash);

	efree(bufP);

	PancakeSAPIInitializeRequest(HTTPRequest);

	RETVAL_ZVAL(HTTPRequest, 0, 0);
	return SUCCESS;
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

		if(PancakeSAPIFetchRequest(fd, return_value) == SUCCESS) {
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

		if(PancakeSAPIFetchRequest(events[0].data.fd, return_value) == SUCCESS) {
			return;
		} else {
			goto wait;
		}
	}
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
	// Reset error handler stack
	zend_stack_destroy(&EG(user_error_handlers_error_reporting));
	zend_stack_init(&EG(user_error_handlers_error_reporting));

	// Set Pancake error handler
	if(EG(user_error_handler)) {
		zval_ptr_dtor(&EG(user_error_handler));
	}
	EG(user_error_handler) = PANCAKE_SAPI_GLOBALS(errorHandler);
	EG(user_error_handler_error_reporting) = E_ALL;

	// Reset exception handler stack
	zend_ptr_stack_destroy(&EG(user_exception_handlers));
	zend_ptr_stack_init(&EG(user_exception_handlers));

	// Reset exception handler
	if (EG(user_exception_handler)) {
		zval_ptr_dtor(&EG(user_exception_handler));
	}

	// Load output layer
	php_output_activate(TSRMLS_C);

	// Tell Pancake not to shutdown
	PANCAKE_GLOBALS(inSAPIReboot) = 1;

	// Run RSHUTDOWN for modules
	zend_hash_reverse_apply(&module_registry, (apply_func_t) PancakeSAPIShutdownModule TSRMLS_CC);

	// Initialize modules again
	zend_hash_reverse_apply(&module_registry, (apply_func_t) PancakeSAPIStartupModule TSRMLS_CC);
	PANCAKE_GLOBALS(inSAPIReboot) = 0;

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
				zend_hash_move_backwards(EG(function_table));
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

PHP_FUNCTION(SAPIExitHandler) {
	zval *exitmsg;

	zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|z", &exitmsg);

	if(ZEND_NUM_ARGS() && Z_TYPE_P(exitmsg) != IS_LONG) {
		zend_print_variable(exitmsg);
	}

	RETURN_BOOL(!PANCAKE_SAPI_GLOBALS(inExecution));
}
