
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake.h                                            		*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#ifndef PANCAKE_H
#define PANCAKE_H

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#define PANCAKE_DEBUG 1

#include "php.h"
#include "ext/standard/info.h"
#include "ext/date/php_date.h"
#include "ext/pcre/php_pcre.h"
#include "SAPI.h"
#include "Zend/zend_extensions.h"
#include "Zend/zend_exceptions.h"
#include "Zend/zend_object_handlers.h"
#include "ext/standard/base64.h"
#include "ext/standard/url.h"
#include "ext/session/php_session.h"

extern zend_module_entry Pancake_module_entry;
#define phpext_Pancake_ptr &Pancake_module_entry

#ifdef PHP_WIN32
#	error "Microsoft Windows is not supported by Pancake"
#endif

#if defined(__GNUC__) && __GNUC__ >= 4
#	define PANCAKE_API __attribute__ ((visibility("default")))
#else
#	define PANCAKE_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

PHP_MINIT_FUNCTION(Pancake);
PHP_MSHUTDOWN_FUNCTION(Pancake);
PHP_RINIT_FUNCTION(Pancake);
PHP_RSHUTDOWN_FUNCTION(Pancake);

PHP_FUNCTION(out);
PHP_FUNCTION(errorHandler);
PHP_FUNCTION(setThread);
PHP_FUNCTION(CodeCacheJITGlobals);
PHP_FUNCTION(ExecuteJITGlobals);
PHP_FUNCTION(loadFilePointers);
PHP_FUNCTION(makeSID);

PHP_FUNCTION(makeFastClass);

PHP_METHOD(HTTPRequest, __construct);
PHP_METHOD(HTTPRequest, init);
PHP_METHOD(HTTPRequest, buildAnswerHeaders);
PHP_METHOD(HTTPRequest, setHeader);
PHP_METHOD(HTTPRequest, invalidRequest);
PHP_METHOD(HTTPRequest, getAnswerCodeString);
PHP_METHOD(HTTPRequest, getGETParams);
PHP_METHOD(HTTPRequest, getPOSTParams);
PHP_METHOD(HTTPRequest, getCookies);
PHP_METHOD(HTTPRequest, createSERVER);
PHP_METHOD(HTTPRequest, registerJITGlobals);
PHP_METHOD(HTTPRequest, setCookie);

PHP_METHOD(invalidHTTPRequestException, __construct);
PHP_METHOD(invalidHTTPRequestException, getHeader);

PHP_METHOD(MIME, typeOf);
PHP_METHOD(MIME, load);

zend_bool PancakeJITFetchSERVER(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchGET(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchCookies(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchREQUEST(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchPOST(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchFILES(const char *name, uint name_len TSRMLS_DC);
zend_bool PancakeJITFetchENV(const char *name, uint name_len TSRMLS_DC);

static zend_bool CodeCacheJITFetch(const char *name, uint name_len TSRMLS_DC);

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

#define PANCAKE_THROW_INVALID_HTTP_REQUEST_EXCEPTION_NO_HEADER(message, message_len, code) { \
	zval *exception; \
	\
	MAKE_STD_ZVAL(exception); \
	object_init_ex(exception, invalidHTTPRequestException_ce); \
	zend_update_property_stringl(invalidHTTPRequestException_ce, exception, "message", sizeof("message") - 1, message, message_len TSRMLS_CC); \
	zend_update_property_long(invalidHTTPRequestException_ce, exception, "code", sizeof("code") - 1, code TSRMLS_CC); \
	\
	zend_throw_exception_object(exception TSRMLS_CC); \
	}

#define LEFT_TRIM(str) while(isspace(*str)) str++

#ifdef ZTS
#define PANCAKE_GLOBALS(v) TSRMG(Pancake_globals_id, zend_Pancake_globals *, v)
#else
#define PANCAKE_GLOBALS(v) (Pancake_globals.v)
#endif

ZEND_BEGIN_MODULE_GLOBALS(Pancake)
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
	int JIT_ENV;
	int enableAuthentication;
	char *tmpDir;
ZEND_END_MODULE_GLOBALS(Pancake)
extern ZEND_DECLARE_MODULE_GLOBALS(Pancake);

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
		case 102: \
			dest = "Processing"; \
			break; \
		case 118: \
			dest = "Connection timed out"; \
			break; \
		case 201: \
			dest = "Created"; \
			break; \
		case 202: \
			dest = "Accepted"; \
			break; \
		case 203: \
			dest = "Non-Authoritative Information"; \
			break; \
		case 205: \
			dest = "Reset Content"; \
			break; \
		case 206: \
			dest = "Partial Content"; \
			break; \
		case 207: \
			dest = "Multi-Status"; \
			break; \
		case 208: \
			dest = "Already Reported"; \
			break; \
		case 226: \
			dest = "IM Used"; \
			break; \
		case 300: \
			dest = "Multiple Choices"; \
			break; \
		case 302: \
			dest = "Found"; \
			break; \
		case 303: \
			dest = "See Other"; \
			break; \
		case 304: \
			dest = "Not Modified"; \
			break; \
		case 305: \
			dest = "Use Proxy"; \
			break; \
		case 307: \
			dest = "Temporary Redirect"; \
			break; \
		case 401: \
			dest = "Unauthorized"; \
			break; \
		case 402: \
			dest = "Payment Required"; \
			break; \
		case 405: \
			dest = "Method Not Allowed"; \
			break; \
		case 406: \
			dest = "Not Acceptable"; \
			break; \
		case 407: \
			dest = "Proxy Authentication Required"; \
			break; \
		case 408: \
			dest = "Request Timeout"; \
			break; \
		case 409: \
			dest = "Conflict"; \
			break; \
		case 410: \
			dest = "Gone"; \
			break; \
		case 411: \
			dest = "Length Required"; \
			break; \
		case 412: \
			dest = "Precondition Failed"; \
			break; \
		case 414: \
			dest = "Request-URI Too Long"; \
			break; \
		case 415: \
			dest = "Unsupported Media Type"; \
			break; \
		case 416: \
			dest = "Requested Range Not Satisfiable"; \
			break; \
		case 417: \
			dest = "Expectation Failed"; \
			break; \
		case 418: \
			dest = "I'm a Pancake"; \
			break; \
		case 421: \
			dest = "There are too many connections from your internet address"; \
			break; \
		case 422: \
			dest = "Unprocessable Entity"; \
			break; \
		case 423: \
			dest = "Locked"; \
			break; \
		case 424: \
			dest = "Failed Dependency"; \
			break; \
		case 426: \
			dest = "Upgrade Required"; \
			break; \
		case 428: /* RFC 6585 */ \
			dest = "Precondition Required"; \
			break; \
		case 429: /* RFC 6585 */ \
			dest = "Too Many Requests"; \
			break; \
		case 431: /* RFC 6585 */ \
			dest = "Request Header Fields Too Large"; \
			break; \
		case 502: \
			dest = "Bad Gateway"; \
			break; \
		case 503: \
			dest = "Service Unavailable"; \
			break; \
		case 504: \
			dest = "Gateway Timeout"; \
			break; \
		case 505: \
			dest = "HTTP Version not supported"; \
			break; \
		case 506: \
			dest = "Variant Also Negotiates"; \
			break; \
		case 507: \
			dest = "Insufficient Storage"; \
			break; \
		case 508: \
			dest = "Loop Detected"; \
			break; \
		case 509: \
			dest = "Bandwith Limit Exceeded"; \
			break; \
		case 510: \
			dest = "Not Extended"; \
			break; \
		case 511: /* RFC 6585 */ \
			dest = "Network Authentication Required"; \
			break; \
		default: \
			dest = ""; \
			break; \
	}

PANCAKE_API int PancakeOutput(char **string, int string_len, long flags TSRMLS_DC);
PANCAKE_API void PancakeSetAnswerHeader(zval *answerHeaderArray, char *name, uint name_len, zval *value, uint replace, ulong h TSRMLS_DC);
PANCAKE_API zval *PancakeMIMEType(char *filePath, int filePath_len TSRMLS_DC);
char *PancakeBuildAnswerHeaders(zval *answerHeaderArray, uint *answerHeader_len);
zval *PancakeFastReadProperty(zval *object, zval *member, ulong hashValue, const zend_literal *key TSRMLS_DC);
void PancakeFastWriteProperty(zval *object, zval *member, zval *value, const zend_literal *key TSRMLS_DC);

#define Z_OBJ_P(zval_p) \
	((zend_object*)(EG(objects_store).object_buckets[Z_OBJ_HANDLE_P(zval_p)].bucket.obj.object))

zend_object_value PancakeCreateObject(zend_class_entry *classType TSRMLS_DC);
zend_class_entry *PancakeObjectGetClass(const zval *object TSRMLS_DC);
static union _zend_function *PancakeFastObjectGetMethod(zval **object_ptr, char *method_name, int method_len, const zend_literal *key TSRMLS_DC);
static int PancakeFastHasProperty(zval *object, zval *member, int has_set_exists, const zend_literal *key TSRMLS_DC);

#define FAST_READ_PROPERTY(destination, object, name, nameLen, hash) {\
	zval *__property; \
	MAKE_STD_ZVAL(__property); \
	Z_TYPE_P(__property) = IS_STRING; \
	Z_STRVAL_P(__property) = estrndup(name, nameLen); \
	Z_STRLEN_P(__property) = nameLen; \
	destination = PancakeFastReadProperty(object, __property, hash, NULL TSRMLS_CC); \
	zval_ptr_dtor(&__property); \
	}

#define HASH_OF_answerHeaders 18278774163892064849U
#define HASH_OF_listen 229473570079380U
#define HASH_OF_requestHeaders 9954895853317176298U
#define HASH_OF_acceptedCompressions 12705014063304977923U
#define HASH_OF_GETParameters 11405648739312147641U
#define HASH_OF_POSTParameters 6130107874256024511U
#define HASH_OF_cookies 7572251828363442U
#define HASH_OF_uploadedFiles 15250737160651435334U
#define HASH_OF_uploadedFileTempNames 11448553736935094685U
#define HASH_OF_documentRoot 16204378207404372328U
#define HASH_OF_AJP13 6952023629892U
#define HASH_OF_indexFiles 13878724278335976880U
#define HASH_OF_allowDirectoryListings 2637217105949475782U
#define HASH_OF_gzipStatic 13876225513030952551U
#define HASH_OF_answerCode 13866493486854215632U
#define HASH_OF_answerBody 13866493486853030371U
#define HASH_OF_protocolVersion 3860956662933781277U
#define HASH_OF_vHost 6954096621945U
#define HASH_OF_onEmptyPage204 62850130289894436U
#define HASH_OF_code 210709057152U
#define HASH_OF_exceptionPageHandler 14133101413685728847U
#define HASH_OF_queryString 15691166075706376146U
#define HASH_OF_rawPOSTData 15711862400059257807U
#define HASH_OF_requestTime 15717763264417086621U
#define HASH_OF_requestMicrotime 12643050781665598615U
#define HASH_OF_requestType 15717763264417664880U
#define HASH_OF_requestFilePath 14916911278280748091U
#define HASH_OF_originalRequestURI 13013963900158340691U
#define HASH_OF_requestURI 13892109728286261886U
#define HASH_OF_remoteIP 249904977867445098U
#define HASH_OF_remotePort 13892103865723532566U
#define HASH_OF_localIP 7572634912942473U
#define HASH_OF_localPort 8246599420203896565U
#define HASH_OF_rewriteRules 2186409550171618610U
#define HASH_OF__GET 210702841668U
#define HASH_OF__POST 6953204809386U
#define HASH_OF__REQUEST 249877393482598893U
#define HASH_OF__FILES 229455359975127U
#define HASH_OF__SERVER 7572043519435131U
#define HASH_OF__COOKIE 7572023243352414U
#define HASH_OF__ENV 0
#define HASH_OF_headers 7572451449572097U
#define HASH_OF_pathInfo 249902003292126174U
#define HASH_OF_exception 8246287202855534580U
#define HASH_OF_content_encoding 6922690342783561268U
#define HASH_OF_friendlyName 1253487734700793347U
#define HASH_OF_GLOBALS 7571012008160073U

#endif	/* PANCAKE_H */
