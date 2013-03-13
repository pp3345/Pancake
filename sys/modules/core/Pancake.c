
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake.c                                            		*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/


/* $Id$ */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "Pancake.h"

ZEND_DECLARE_MODULE_GLOBALS(Pancake)

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

ZEND_BEGIN_ARG_INFO(arginfo_sigwaitinfo, 0)
	ZEND_ARG_INFO(0, "set")
	ZEND_ARG_INFO(1, "info")
	ZEND_ARG_INFO(0, "seconds")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_wait, 0)
	ZEND_ARG_INFO(1, "status")
	ZEND_ARG_INFO(0, "options")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_writeBuffer, 0)
	ZEND_ARG_INFO(0, "fd")
	ZEND_ARG_INFO(1, "buffer")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_getnName, 0)
	ZEND_ARG_INFO(0, "fd")
	ZEND_ARG_INFO(1, "address")
	ZEND_ARG_INFO(1, "port")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_select, 0)
	ZEND_ARG_INFO(1, "read")
	ZEND_ARG_INFO(1, "write")
	ZEND_ARG_INFO(0, "nanoseconds")
ZEND_END_ARG_INFO()

const zend_function_entry Pancake_functions[] = {
	ZEND_NS_FE("Pancake", out,	arginfo_pancake_out)
	ZEND_NS_FE("Pancake", errorHandler, arginfo_pancake_errorHandler)
	ZEND_NS_FE("Pancake", setThread, arginfo_pancake_setThread)
	ZEND_NS_FE("Pancake", CodeCacheJITGlobals, NULL)
	ZEND_NS_FE("Pancake", ExecuteJITGlobals, NULL)
	ZEND_NS_FE("Pancake", loadFilePointers, NULL)
	ZEND_NS_FE("Pancake", makeSID, NULL)
	ZEND_NS_FE("Pancake", makeFastClass, NULL)
	ZEND_NS_FE("Pancake", loadModule, NULL)
	ZEND_NS_FE("Pancake", disableModuleLoader, NULL)
	ZEND_NS_FE("Pancake", sigwaitinfo, arginfo_sigwaitinfo)
	ZEND_NS_FE("Pancake", fork, NULL)
	ZEND_NS_FE("Pancake", wait, arginfo_wait)
	ZEND_NS_FE("Pancake", sigprocmask, NULL)
	ZEND_NS_FE("Pancake", waitpid, NULL)
	ZEND_NS_FE("Pancake", socket, NULL)
	ZEND_NS_FE("Pancake", reuseaddress, NULL)
	ZEND_NS_FE("Pancake", bind, NULL)
	ZEND_NS_FE("Pancake", listen, NULL)
	ZEND_NS_FE("Pancake", setBlocking, NULL)
	ZEND_NS_FE("Pancake", write, NULL)
	ZEND_NS_FE("Pancake", writeBuffer, arginfo_writeBuffer)
	ZEND_NS_FE("Pancake", read, NULL)
	ZEND_NS_FE("Pancake", accept, NULL)
	ZEND_NS_FE("Pancake", keepAlive, NULL)
	ZEND_NS_FE("Pancake", connect, NULL)
	ZEND_NS_FE("Pancake", close, NULL)
	ZEND_NS_FE("Pancake", getPeerName, arginfo_getnName)
	ZEND_NS_FE("Pancake", getSockName, arginfo_getnName)
	ZEND_NS_FE("Pancake", select, arginfo_select)
	ZEND_NS_FE("Pancake", adjustSendBufferSize, NULL)
	ZEND_NS_FE("Pancake", nonBlockingAccept, NULL)
	ZEND_FE_END
};

const zend_function_entry HTTPRequest_methods[] = {
	ZEND_ME(HTTPRequest, __construct, arginfo_pancake_HTTPRequest_construct, ZEND_ACC_PUBLIC | ZEND_ACC_CTOR)
	ZEND_ME(HTTPRequest, init, arginfo_pancake_HTTPRequest_init, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, buildAnswerHeaders, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, setHeader, arginfo_pancake_HTTPRequest_setHeader, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, getAnswerCodeString, arginfo_pancake_HTTPRequest_getAnswerCodeString, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
	ZEND_ME(HTTPRequest, invalidRequest, arginfo_pancake_HTTPRequest_invalidRequest, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, getGETParams, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, getPOSTParams, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, getCookies, NULL, ZEND_ACC_PUBLIC)
	ZEND_ME(HTTPRequest, createSERVER, NULL, ZEND_ACC_PUBLIC)
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

zend_module_entry Pancake_module_entry = {
	STANDARD_MODULE_HEADER,
	"Pancake",
	Pancake_functions,
	PHP_MINIT(Pancake),
	PHP_MSHUTDOWN(Pancake),
	PHP_RINIT(Pancake),
	PHP_RSHUTDOWN(Pancake),
	NULL,
	PANCAKE_VERSION,
	STANDARD_MODULE_PROPERTIES
};

zend_class_entry *HTTPRequest_ce;
zend_class_entry *invalidHTTPRequestException_ce;
zend_class_entry *MIME_ce;

#ifdef COMPILE_DL_PANCAKE
ZEND_GET_MODULE(Pancake)
#endif

void php_Pancake_init_globals(zend_Pancake_globals *Pancake_globals)
{
	TSRMLS_FETCH();

	Pancake_globals->systemLogStream = NULL;
	Pancake_globals->requestLogStream = NULL;
	Pancake_globals->errorLogStream = NULL;
	Pancake_globals->currentThread = NULL;
	Pancake_globals->dateFormat = "";
	Pancake_globals->allowHEAD = 0;
	Pancake_globals->allowTRACE = 0;
	Pancake_globals->allowOPTIONS = 0;
	Pancake_globals->exposePancake = 0;
	Pancake_globals->JIT_COOKIE = PG(auto_globals_jit);
	Pancake_globals->JIT_GET = PG(auto_globals_jit);
	Pancake_globals->JIT_SERVER = PG(auto_globals_jit);
	Pancake_globals->JIT_REQUEST = PG(auto_globals_jit);
	Pancake_globals->JIT_POST = PG(auto_globals_jit);
	Pancake_globals->JIT_FILES = PG(auto_globals_jit);
	Pancake_globals->JIT_ENV = PG(auto_globals_jit);
	Pancake_globals->disableModuleLoader = 0;
}

PHP_MINIT_FUNCTION(Pancake)
{
	zend_class_entry http, exception, mime;

	/* Init module globals */
	ZEND_INIT_MODULE_GLOBALS(Pancake, php_Pancake_init_globals, NULL);

	REGISTER_NS_LONG_CONSTANT("Pancake", "OUTPUT_SYSTEM", 1, 0);
	REGISTER_NS_LONG_CONSTANT("Pancake", "OUTPUT_REQUEST", 2, 0);
	REGISTER_NS_LONG_CONSTANT("Pancake", "OUTPUT_LOG", 4, 0);
	REGISTER_NS_LONG_CONSTANT("Pancake", "OUTPUT_DEBUG", 8, 0);
	REGISTER_NS_LONG_CONSTANT("Pancake", "ERROR_REPORTING", ERROR_REPORTING, 0);
	REGISTER_NS_STRINGL_CONSTANT("Pancake", "VERSION", PANCAKE_VERSION, strlen(PANCAKE_VERSION), CONST_PERSISTENT);

	if(!zend_hash_exists(&module_registry, "pcntl", sizeof("pcntl"))) {
		REGISTER_LONG_CONSTANT("WNOHANG",  (long) WNOHANG, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGHUP",   (long) SIGHUP,  CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGINT",   (long) SIGINT,  CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGQUIT",  (long) SIGQUIT, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGILL",   (long) SIGILL,  CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGTRAP",  (long) SIGTRAP, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGABRT",  (long) SIGABRT, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGIOT",   (long) SIGIOT,  CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGBUS",   (long) SIGBUS,  CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGFPE",   (long) SIGFPE,  CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGKILL",  (long) SIGKILL, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGUSR1",  (long) SIGUSR1, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGSEGV",  (long) SIGSEGV, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGUSR2",  (long) SIGUSR2, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGPIPE",  (long) SIGPIPE, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGALRM",  (long) SIGALRM, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGTERM",  (long) SIGTERM, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGCLD",   (long) SIGCLD, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGCHLD",  (long) SIGCHLD, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGCONT",  (long) SIGCONT, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGSTOP",  (long) SIGSTOP, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGTSTP",  (long) SIGTSTP, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGTTIN",  (long) SIGTTIN, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGTTOU",  (long) SIGTTOU, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGURG",   (long) SIGURG , CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGXCPU",  (long) SIGXCPU, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGXFSZ",  (long) SIGXFSZ, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGVTALRM",(long) SIGVTALRM, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGPROF",  (long) SIGPROF, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGWINCH", (long) SIGWINCH, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGPOLL",  (long) SIGPOLL, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGIO",    (long) SIGIO, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGSYS",   (long) SIGSYS, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIGBABY",  (long) SIGSYS, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIG_BLOCK",   SIG_BLOCK, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIG_UNBLOCK", SIG_UNBLOCK, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SIG_SETMASK", SIG_SETMASK, CONST_CS | CONST_PERSISTENT);
	}

	if(!zend_hash_exists(&module_registry, "sockets", sizeof("sockets"))) {
		REGISTER_LONG_CONSTANT("AF_UNIX",		AF_UNIX,		CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("AF_INET",		AF_INET,		CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("AF_INET6",		AF_INET6,		CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SOCK_STREAM",	SOCK_STREAM,	CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SOCK_DGRAM",	SOCK_DGRAM,		CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SOCK_RAW",		SOCK_RAW,		CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SOCK_SEQPACKET",SOCK_SEQPACKET, CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SOL_SOCKET",	SOL_SOCKET,		CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SOL_TCP",		IPPROTO_TCP,	CONST_CS | CONST_PERSISTENT);
		REGISTER_LONG_CONSTANT("SOL_UDP",		IPPROTO_UDP,	CONST_CS | CONST_PERSISTENT);
	}

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
	zend_declare_property_stringl(HTTPRequest_ce, "pathInfo", sizeof("pathInfo") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_long(HTTPRequest_ce, "fCGISocket", sizeof("fCGISocket") - 1, 0, ZEND_ACC_PUBLIC TSRMLS_CC);
	zend_declare_property_null(HTTPRequest_ce, "headerDataCompleted", sizeof("headerDataCompleted") - 1, ZEND_ACC_PUBLIC TSRMLS_CC);

	INIT_NS_CLASS_ENTRY(exception, "Pancake", "invalidHTTPRequestException", invalidHTTPRequestException_methods);
	exception.create_object = PancakeCreateObject;
	invalidHTTPRequestException_ce = zend_register_internal_class_ex(&exception, zend_exception_get_default(TSRMLS_C), "Exception" TSRMLS_CC);
	zend_declare_property_stringl(invalidHTTPRequestException_ce, "header", sizeof("header") - 1, "", 0, ZEND_ACC_PUBLIC TSRMLS_CC);

	INIT_NS_CLASS_ENTRY(mime, "Pancake", "MIME", MIME_methods);
	MIME_ce = zend_register_internal_class(&mime TSRMLS_CC);

	//char *getHash = "GLOBALS";
	//printf("#define HASH_OF_%s %luU\n", getHash, zend_inline_hash_func(getHash, strlen(getHash) + 1));

	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(Pancake)
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

PHP_RINIT_FUNCTION(Pancake)
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

PHP_RSHUTDOWN_FUNCTION(Pancake)
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
