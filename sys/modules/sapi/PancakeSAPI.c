
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeSAPI.c                                            	*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "PancakeSAPI.h"

ZEND_DECLARE_MODULE_GLOBALS(PancakeSAPI)

const zend_function_entry PancakeSAPI_functions[] = {
	ZEND_NS_FE("Pancake", SAPIRequest, NULL)
	ZEND_NS_FE("Pancake", SAPIFinishRequest, NULL)
	ZEND_NS_FE("Pancake", SAPIFlushBuffers, NULL)
	ZEND_FE_END
};

zend_module_entry PancakeSAPI_module_entry = {
	STANDARD_MODULE_HEADER,
	"PancakeSAPI",
	PancakeSAPI_functions,
	PHP_MINIT(PancakeSAPI),
	NULL,
	PHP_RINIT(PancakeSAPI),
	NULL,
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

	return SUCCESS;
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

PHP_RINIT_FUNCTION(PancakeSAPI) {
	zend_function *function;

	PANCAKE_SAPI_GLOBALS(inExecution) = 0;
	PANCAKE_SAPI_GLOBALS(outputLength) = 0;
	PANCAKE_SAPI_GLOBALS(output) = NULL;

	sapi_module.name = "pancake";
	sapi_module.header_handler = PancakeSAPIHeaderHandler;
	sapi_module.ub_write = PancakeSAPIOutputHandler;
	sapi_module.flush = NULL;

	// Hook some functions
	zend_hash_find(EG(function_table), "headers_sent", sizeof("headers_sent"), (void**) &function);
	PHP_headers_sent = function->internal_function.handler;
	function->internal_function.handler = Pancake_headers_sent;

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

	if(SG(sapi_headers).http_status_line) {
		if(strlen(SG(sapi_headers).http_status_line) >= sizeof("HTTP/1.0 200 ") && SG(sapi_headers).http_status_line[12] == ' ') {
			char *answerCodeString = &(SG(sapi_headers).http_status_line[13]);

			PancakeQuickWritePropertyString(PANCAKE_SAPI_GLOBALS(request), "answerCodeString", sizeof("answerCodeString"), HASH_OF_answerCodeString, answerCodeString,
					strlen(answerCodeString), 1);
		}

		efree(SG(sapi_headers).http_status_line);
		SG(sapi_headers).http_status_line = NULL;
	}

	SG(sapi_headers).http_response_code = 0;
	zend_llist_clean(&SG(sapi_headers).headers);

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
