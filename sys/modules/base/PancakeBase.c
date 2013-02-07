
	/****************************************************************/
    /* Pancake                                                      */
    /* PancakeBase.c                                            	*/
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/


/* $Id$ */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php_PancakeBase.h"

ZEND_DECLARE_MODULE_GLOBALS(PancakeBase)

ZEND_BEGIN_ARG_INFO(arginfo_pancake_out, 0)
	ZEND_ARG_INFO(0, "text")
	ZEND_ARG_INFO(0, "flags")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_errorHandler, 0)
	ZEND_ARG_INFO(0, "errtype")
	ZEND_ARG_INFO(0, "errstr")
	ZEND_ARG_INFO(0, "errfile")
	ZEND_ARG_INFO(0, "errline")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_setThread, 0)
	ZEND_ARG_INFO(0, "thread")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_HTTPRequest_construct, 0)
	ZEND_ARG_INFO(0, "remoteIP")
	ZEND_ARG_INFO(0, "remotePort")
	ZEND_ARG_INFO(0, "localIP")
	ZEND_ARG_INFO(0, "localPort")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_HTTPRequest_init, 0)
	ZEND_ARG_INFO(0, "requestHeader")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_HTTPRequest_setHeader, 0)
	ZEND_ARG_INFO(0, "headerName")
	ZEND_ARG_INFO(0, "headerValue")
	ZEND_ARG_INFO(0, "replace")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_HTTPRequest_invalidRequest, 0)
	ZEND_ARG_INFO(0, "exception")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_HTTPRequest_getAnswerCodeString, 0)
	ZEND_ARG_INFO(0, "answerCode")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_HTTPRequest_setCookie, 0)
	ZEND_ARG_INFO(0, "name")
	ZEND_ARG_INFO(0, "value")
	ZEND_ARG_INFO(0, "expire")
	ZEND_ARG_INFO(0, "path")
	ZEND_ARG_INFO(0, "domain")
	ZEND_ARG_INFO(0, "secure")
	ZEND_ARG_INFO(0, "httpOnly")
	ZEND_ARG_INFO(0, "raw")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_invalidHTTPRequestException_construct, 0)
	ZEND_ARG_INFO(0, "message")
	ZEND_ARG_INFO(0, "code")
	ZEND_ARG_INFO(0, "header")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_pancake_MIME_typeOf, 0)
	ZEND_ARG_INFO(0, "filePath")
ZEND_END_ARG_INFO()

const zend_function_entry PancakeBase_functions[] = {
	ZEND_NS_FE("Pancake", out,	arginfo_pancake_out)
	ZEND_NS_FE("Pancake", errorHandler, arginfo_pancake_errorHandler)
	ZEND_NS_FE("Pancake", setThread, arginfo_pancake_setThread)
	ZEND_NS_FE("Pancake", CodeCacheJITGlobals, NULL)
	ZEND_NS_FE("Pancake", ExecuteJITGlobals, NULL)
	ZEND_NS_FE("Pancake", loadFilePointers, NULL)
	ZEND_NS_FE("Pancake", makeSID, NULL)
	ZEND_NS_FE("Pancake", makeFastClass, NULL)
	ZEND_FE_END
};

const zend_function_entry HTTPRequest_methods[] = {
	ZEND_ME(HTTPRequest, __construct, arginfo_pancake_HTTPRequest_construct, ZEND_ACC_PUBLIC | ZEND_ACC_CTOR)
	ZEND_ME(HTTPRequest, __destruct, NULL, ZEND_ACC_PUBLIC | ZEND_ACC_DTOR)
	ZEND_ME(HTTPRequest, init, arginfo_pancake_HTTPRequest_init, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, buildAnswerHeaders, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, setHeader, arginfo_pancake_HTTPRequest_setHeader, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, getAnswerCodeString, arginfo_pancake_HTTPRequest_getAnswerCodeString, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
	ZEND_ME(HTTPRequest, invalidRequest, arginfo_pancake_HTTPRequest_invalidRequest, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, getGETParams, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, getPOSTParams, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, getCookies, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, registerJITGlobals, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, setCookie, arginfo_pancake_HTTPRequest_setCookie, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

const zend_function_entry invalidHTTPRequestException_methods[] = {
	ZEND_ME(invalidHTTPRequestException, __construct, arginfo_pancake_invalidHTTPRequestException_construct, ZEND_ACC_PUBLIC)
	ZEND_ME(invalidHTTPRequestException, getHeader, NULL, ZEND_ACC_PUBLIC | ZEND_ACC_DEPRECATED)
	ZEND_FE_END
};

const zend_function_entry MIME_methods[] = {
	ZEND_ME(MIME, typeOf, arginfo_pancake_MIME_typeOf, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
	ZEND_ME(MIME, load, NULL, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
	ZEND_FE_END
};

zend_module_entry PancakeBase_module_entry = {
	STANDARD_MODULE_HEADER,
	"PancakeBase",
	PancakeBase_functions,
	PHP_MINIT(PancakeBase),
	PHP_MSHUTDOWN(PancakeBase),
	PHP_RINIT(PancakeBase),
	PHP_RSHUTDOWN(PancakeBase),
	NULL,
	PANCAKE_VERSION,
	STANDARD_MODULE_PROPERTIES
};

zend_class_entry *HTTPRequest_ce;
zend_class_entry *invalidHTTPRequestException_ce;
zend_class_entry *MIME_ce;

#ifdef COMPILE_DL_PANCAKEBASE
ZEND_GET_MODULE(PancakeBase)
#endif

void php_PancakeBase_init_globals(zend_PancakeBase_globals *PancakeBase_globals)
{
	PancakeBase_globals->systemLogStream = NULL;
	PancakeBase_globals->requestLogStream = NULL;
	PancakeBase_globals->errorLogStream = NULL;
	PancakeBase_globals->currentThread = NULL;
	PancakeBase_globals->dateFormat = "";
	PancakeBase_globals->allowHEAD = 0;
	PancakeBase_globals->allowTRACE = 0;
	PancakeBase_globals->allowOPTIONS = 0;
	PancakeBase_globals->exposePancake = 0;
	PancakeBase_globals->JIT_COOKIE = 1;
	PancakeBase_globals->JIT_GET = 1;
	PancakeBase_globals->JIT_SERVER = 1;
	PancakeBase_globals->JIT_REQUEST = 1;
	PancakeBase_globals->JIT_POST = 1;
	PancakeBase_globals->JIT_FILES = 1;
}

PHP_MINIT_FUNCTION(PancakeBase)
{
	zend_class_entry http, exception, mime;

	/* Init module globals */
	ZEND_INIT_MODULE_GLOBALS(PancakeBase, php_PancakeBase_init_globals, NULL);

	REGISTER_NS_LONG_CONSTANT("Pancake", "OUTPUT_SYSTEM", 1, 0);
	REGISTER_NS_LONG_CONSTANT("Pancake", "OUTPUT_REQUEST", 2, 0);
	REGISTER_NS_LONG_CONSTANT("Pancake", "OUTPUT_LOG", 4, 0);
	REGISTER_NS_LONG_CONSTANT("Pancake", "OUTPUT_DEBUG", 8, 0);
	REGISTER_NS_LONG_CONSTANT("Pancake", "ERROR_REPORTING", ERROR_REPORTING, 0);
	REGISTER_NS_STRINGL_CONSTANT("Pancake", "VERSION", PANCAKE_VERSION, strlen(PANCAKE_VERSION), CONST_PERSISTENT);

	INIT_NS_CLASS_ENTRY(http, "Pancake", "HTTPRequest", HTTPRequest_methods);
	http.create_object = PancakeCreateObject;
	HTTPRequest_ce = zend_register_internal_class(&http TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "answerBody", sizeof("answerBody") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "queryString", sizeof("queryString") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "acceptedCompressions", sizeof("acceptedCompressions") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "requestHeaders", sizeof("requestHeaders") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "answerHeaders", sizeof("answerHeaders") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "protocolVersion", sizeof("protocolVersion") - 1, "1.0", 3, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "vHost", sizeof("vHost") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "localIP", sizeof("localIP") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "remoteIP", sizeof("remoteIP") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_long(HTTPRequest_ce, "localPort", sizeof("localPort") - 1, 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_long(HTTPRequest_ce, "remotePort", sizeof("remotePort") - 1, 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "requestLine", sizeof("requestLine") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "requestType", sizeof("requestType") - 1, "GET", 3, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "originalRequestURI", sizeof("originalRequestURI") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "requestURI", sizeof("requestURI") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "requestFilePath", sizeof("requestFilePath") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "mimeType", sizeof("mimeType") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_long(HTTPRequest_ce, "requestTime", sizeof("requestTime") - 1, 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_double(HTTPRequest_ce, "requestMicrotime", sizeof("requestMicrotime") - 1, (double) 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_long(HTTPRequest_ce, "answerCode", sizeof("answerCode") - 1, 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "GETParameters", sizeof("GETParameters") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_stringl(HTTPRequest_ce, "rawPOSTData", sizeof("rawPOSTData") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "cookies", sizeof("cookies") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "POSTParameters", sizeof("POSTParameters") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "uploadedFiles", sizeof("uploadedFiles") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "uploadedFileTempNames", sizeof("uploadedFileTempNames") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_long(HTTPRequest_ce, "rangeFrom", sizeof("rangeFrom") - 1, 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_long(HTTPRequest_ce, "rangeTo", sizeof("rangeTo") - 1, 0, ZEND_ACC_PUBLIC TSRMLS_CC);

	INIT_NS_CLASS_ENTRY(exception, "Pancake", "invalidHTTPRequestException", invalidHTTPRequestException_methods);
	exception.create_object = PancakeCreateObject;
	invalidHTTPRequestException_ce = zend_register_internal_class_ex(&exception, zend_exception_get_default(TSRMLS_C), "Exception" TSRMLS_CC);

	INIT_NS_CLASS_ENTRY(mime, "Pancake", "MIME", MIME_methods);
	MIME_ce = zend_register_internal_class(&mime TSRMLS_CC);

	//char *getHash = "rewriteRules";
	//printf("#define HASH_OF_%s %luU\n", getHash, zend_inline_hash_func(getHash, strlen(getHash) + 1));

	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(PancakeBase)
{
	if(PANCAKE_GLOBALS(requestLogStream)) {
		fclose(PANCAKE_GLOBALS(requestLogStream));
	}

	if(PANCAKE_GLOBALS(systemLogStream)) {
		fclose(PANCAKE_GLOBALS(systemLogStream));
	}

	if(PANCAKE_GLOBALS(errorLogStream)) {
		fclose(PANCAKE_GLOBALS(errorLogStream));
	}

	return SUCCESS;
}

PHP_RINIT_FUNCTION(PancakeBase)
{
	zval *errorHandler;

	MAKE_STD_ZVAL(errorHandler);
	Z_TYPE_P(errorHandler) = IS_STRING;
	Z_STRLEN_P(errorHandler) = sizeof("Pancake\\errorHandler") - 1;
	Z_STRVAL_P(errorHandler) = estrndup("Pancake\\errorHandler", sizeof("Pancake\\errorHandler") - 1);

	/* Set error handler */
	EG(user_error_handler) = errorHandler;
	EG(user_error_handler_error_reporting) = E_ALL;

	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(PancakeBase)
{
	if(PANCAKE_GLOBALS(mimeTable)) {
		zend_hash_destroy(PANCAKE_GLOBALS(mimeTable));
		FREE_HASHTABLE(PANCAKE_GLOBALS(mimeTable));

		zval_ptr_dtor(&PANCAKE_GLOBALS(defaultMimeType));
	}

	if(PANCAKE_GLOBALS(pancakeVersionString)) {
		zval_ptr_dtor(&PANCAKE_GLOBALS(pancakeVersionString));
		zval_ptr_dtor(&PANCAKE_GLOBALS(defaultContentType));
		efree(PANCAKE_GLOBALS(tmpDir));
	}

	if(PANCAKE_GLOBALS(virtualHostArray)) {
		zval_ptr_dtor(&PANCAKE_GLOBALS(virtualHostArray));
		zval_ptr_dtor(&PANCAKE_GLOBALS(defaultVirtualHost));
	}

	if(PANCAKE_GLOBALS(currentThread)) {
		zval_ptr_dtor(&PANCAKE_GLOBALS(currentThread));
	}

	if(strlen(PANCAKE_GLOBALS(dateFormat))) {
		efree(PANCAKE_GLOBALS(dateFormat));
	}

	return SUCCESS;
}
