
	/****************************************************************/
    /* Pancake                                                      */
    /* php_PancakeBase.h                                            */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#ifndef PHP_PANCAKEBASE_H
#define PHP_PANCAKEBASE_H

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#define PANCAKE_DEBUG 1

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "ext/date/php_date.h"
#include "ext/pcre/php_pcre.h"
#include "SAPI.h"
#include "Zend/zend_extensions.h"
#include "Zend/zend_exceptions.h"

extern zend_module_entry PancakeBase_module_entry;
#define phpext_PancakeBase_ptr &PancakeBase_module_entry

#ifdef PHP_WIN32
#	error "Windows is not supported by Pancake"
#endif

#if defined(__GNUC__) && __GNUC__ >= 4
#	define PANCAKE_API __attribute__ ((visibility("default")))
#else
#	define PANCAKE_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

PHP_MINIT_FUNCTION(PancakeBase);
PHP_MSHUTDOWN_FUNCTION(PancakeBase);
PHP_RINIT_FUNCTION(PancakeBase);
PHP_RSHUTDOWN_FUNCTION(PancakeBase);

PHP_FUNCTION(out);
PHP_FUNCTION(errorHandler);
PHP_FUNCTION(setThread);
PHP_FUNCTION(CodeCacheJITGlobals);
PHP_FUNCTION(ExecuteJITGlobals);
PHP_FUNCTION(loadFilePointers);

PHP_METHOD(HTTPRequest, __construct);
PHP_METHOD(HTTPRequest, __destruct);
PHP_METHOD(HTTPRequest, init);
PHP_METHOD(HTTPRequest, buildAnswerHeaders);
PHP_METHOD(HTTPRequest, setHeader);
PHP_METHOD(HTTPRequest, invalidRequest);
PHP_METHOD(HTTPRequest, getAnswerCodeString);
PHP_METHOD(HTTPRequest, getGETParams);
PHP_METHOD(HTTPRequest, getPOSTParams);
PHP_METHOD(HTTPRequest, getCookies);
PHP_METHOD(HTTPRequest, registerJITGlobals);
PHP_METHOD(HTTPRequest, setCookie);

PHP_METHOD(invalidHTTPRequestException, __construct);
PHP_METHOD(invalidHTTPRequestException, getHeader);

PHP_METHOD(MIME, typeOf);
PHP_METHOD(MIME, load);

zend_bool PancakeCreateSERVER(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchGET(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchCookies(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchREQUEST(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchPOST(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchFILES(const char *name, uint name_len TSRMLS_DC);

zend_bool CodeCacheJITFetch(const char *name, uint name_len TSRMLS_DC);

PANCAKE_API int PancakeLoadFilePointers(TSRMLS_C);

extern zend_class_entry *HTTPRequest_ce;
extern zend_class_entry *invalidHTTPRequestException_ce;
extern zend_class_entry *MIME_ce;

#define PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION(message, code, header, header_len) { \
	zval *exception; \
	\
	MAKE_STD_ZVAL(exception); \
	object_init_ex(exception, invalidHTTPRequestException_ce); \
	zend_update_property_string(invalidHTTPRequestException_ce, exception, "message", sizeof("message") - 1, message TSRMLS_CC); \
	zend_update_property_long(invalidHTTPRequestException_ce, exception, "code", sizeof("code") - 1, code TSRMLS_CC); \
	zend_update_property_stringl(invalidHTTPRequestException_ce, exception, "header", sizeof("header") - 1, header, header_len TSRMLS_CC); \
	\
	zend_throw_exception_object(exception TSRMLS_CC); \
	}

#define PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTIONL(message, message_len, code, header, header_len) { \
	zval *exception; \
	\
	MAKE_STD_ZVAL(exception); \
	object_init_ex(exception, invalidHTTPRequestException_ce); \
	zend_update_property_stringl(invalidHTTPRequestException_ce, exception, "message", sizeof("message") - 1, message, message_len TSRMLS_CC); \
	zend_update_property_long(invalidHTTPRequestException_ce, exception, "code", sizeof("code") - 1, code TSRMLS_CC); \
	zend_update_property_stringl(invalidHTTPRequestException_ce, exception, "header", sizeof("header") - 1, header, header_len TSRMLS_CC); \
	\
	zend_throw_exception_object(exception TSRMLS_CC); \
	}

#define PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER(message, code) { \
	zval *exception; \
	\
	MAKE_STD_ZVAL(exception); \
	object_init_ex(exception, invalidHTTPRequestException_ce); \
	zend_update_property_string(invalidHTTPRequestException_ce, exception, "message", sizeof("message") - 1, message TSRMLS_CC); \
	zend_update_property_long(invalidHTTPRequestException_ce, exception, "code", sizeof("code") - 1, code TSRMLS_CC); \
	\
	zend_throw_exception_object(exception TSRMLS_CC); \
	}

#define LEFT_TRIM(str) while(isspace(*str)) str++

#ifdef ZTS
#define PANCAKE_GLOBALS(v) TSRMG(PancakeBase_globals_id, zend_PancakeBase_globals *, v)
#else
#define PANCAKE_GLOBALS(v) (PancakeBase_globals.v)
#endif

ZEND_BEGIN_MODULE_GLOBALS(PancakeBase)
	FILE *systemLogStream;
	FILE *requestLogStream;
	FILE *errorLogStream;
	zval *currentThread;
	char *dateFormat;
	int allowHEAD;
	int allowTRACE;
	int allowOPTIONS;
	long postMaxSize;
	zval *defaultVirtualHost;
	zval *virtualHostArray;
	HashTable *mimeTable;
	zval *defaultMimeType;
	int exposePancake;
	zval *pancakeVersionString;
	zval *defaultContentType;
	zval *JITGlobalsHTTPRequest;
	int JIT_GET;
	int JIT_COOKIE;
	int JIT_SERVER;
	int JIT_REQUEST;
	int JIT_POST;
	int JIT_FILES;
	int enableAuthentication;
	char *tmpDir;
ZEND_END_MODULE_GLOBALS(PancakeBase)
extern ZEND_DECLARE_MODULE_GLOBALS(PancakeBase);

#define OUTPUT_SYSTEM 1
#define OUTPUT_REQUEST 2
#define OUTPUT_LOG 4
#define OUTPUT_DEBUG 8
#define ERROR_REPORTING E_COMPILE_ERROR | E_COMPILE_WARNING | E_CORE_ERROR | E_CORE_WARNING | E_ERROR | E_PARSE | E_RECOVERABLE_ERROR | E_USER_ERROR | E_USER_WARNING | E_WARNING

#define PANCAKE_VERSION "1.3-devel"
#define PANCAKE_VERSION_STRING "Pancake/1.3-devel"

#define PANCAKE_ANSWER_CODE_STRING(dest, code) \
	switch(code) { \
		case 200: \
			dest = "OK"; \
			break; \
		case 204: \
			dest = "No Content"; \
			break; \
		case 404: \
			dest = "Not Found"; \
			break; \
		case 403: \
			dest = "Forbidden"; \
			break; \
		case 500: \
			dest = "Internal Server Error"; \
			break; \
		case 400: \
			dest = "Bad Request"; \
			break; \
		case 301: \
			dest = "Moved Permanently"; \
			break; \
		case 501: \
			dest = "Not Implemented"; \
			break; \
		case 413: \
			dest = "Request Entity Too Large"; \
			break; \
		case 100: \
			dest = "Continue"; \
			break; \
		case 101: \
			dest = "Switching Protocols"; \
			break; \
		default: \
			dest = ""; \
			break; \
	}

PANCAKE_API int PancakeOutput(char **string, int string_len, long flags TSRMLS_DC);
PANCAKE_API void PancakeSetAnswerHeader(zval *answerHeaderArray, char *name, uint name_len, zval *value, uint replace, ulong h TSRMLS_DC);
PANCAKE_API zval *PancakeMIMEType(char *filePath, int filePath_len TSRMLS_DC);
char *PancakeBuildAnswerHeaders(zval *object);

#endif	/* PHP_PANCAKEBASE_H */
