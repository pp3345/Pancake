
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake_LowLevel.c                                      		*/
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "Pancake.h"

PHP_FUNCTION(sigwaitinfo) {
	zval *uset, *usiginfo, **data;
	sigset_t set;
	siginfo_t siginfo;
	struct timespec timeout;
	int signal;
	long seconds = 99999999;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "a|zl", &uset, &usiginfo, &seconds) == FAILURE) {
		RETURN_FALSE;
	}

	if(sigemptyset(&set)) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(uset));
		zend_hash_get_current_data(Z_ARRVAL_P(uset), (void**) &data) == SUCCESS;
		zend_hash_move_forward(Z_ARRVAL_P(uset))) {
		if (sigaddset(&set, Z_LVAL_PP(data)) != 0) {
			zend_error(E_WARNING, "%s", strerror(errno));
			RETURN_FALSE;
		}
	}

	do {
		// We user sigtimedwait() rather than sigwaitinfo() for BSD and Darwin compatibility
		timeout.tv_sec = seconds;
		timeout.tv_nsec = 0;
		signal = sigtimedwait(&set, &siginfo, &timeout);
	} while(signal == - 1 && (errno == EAGAIN || errno == EINTR));

	if(signal == -1) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	} else {
		if (!signal && siginfo.si_signo) {
			signal = siginfo.si_signo;
		}

		if (Z_TYPE_P(usiginfo) != IS_ARRAY) {
			zval_dtor(usiginfo);
			array_init(usiginfo);
		} else {
			zend_hash_clean(Z_ARRVAL_P(usiginfo));
		}

		add_assoc_long_ex(usiginfo, "signo", sizeof("signo"), siginfo.si_signo);
		add_assoc_long_ex(usiginfo, "errno", sizeof("errno"), siginfo.si_errno);
		add_assoc_long_ex(usiginfo, "code",  sizeof("code"),  siginfo.si_code);
		switch(signal) {
			case SIGCHLD:
				add_assoc_long_ex(usiginfo,   "status", sizeof("status"), siginfo.si_status);
				add_assoc_long_ex(usiginfo,   "pid",    sizeof("pid"),    siginfo.si_pid);
				add_assoc_long_ex(usiginfo,   "uid",    sizeof("uid"),    siginfo.si_uid);
				break;
			case SIGILL:
			case SIGFPE:
			case SIGSEGV:
			case SIGBUS:
				add_assoc_double_ex(usiginfo, "addr", sizeof("addr"), (long)siginfo.si_addr);
				break;
		}
	}

	RETURN_LONG(signal);
}

PHP_FUNCTION(fork) {
	pid_t id = fork();

	if(id == -1) {
		zend_error(E_WARNING, "%s", strerror(errno));
	}

	RETURN_LONG((long) id);
}

PHP_FUNCTION(wait) {
	long options = 0;
	zval *z_status = NULL;
	int status;
	pid_t child_id;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z|l", &z_status, &options) == FAILURE) {
		RETURN_FALSE;
	}

	if(options) {
		child_id = wait3(&status, options, NULL);
	} else {
		child_id = wait(&status);
	}

	zval_dtor(z_status);
	Z_TYPE_P(z_status) = IS_LONG;
	Z_LVAL_P(z_status) = status;

	RETURN_LONG((long) child_id);
}

PHP_FUNCTION(sigprocmask) {
	long how;
	zval *uset, **data;
	sigset_t set;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "la|z", &how, &uset) == FAILURE) {
		RETURN_FALSE;
	}

	if(sigemptyset(&set)) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	for(zend_hash_internal_pointer_reset(Z_ARRVAL_P(uset));
		zend_hash_get_current_data(Z_ARRVAL_P(uset), (void**) &data) == SUCCESS;
		zend_hash_move_forward(Z_ARRVAL_P(uset))) {
		if (sigaddset(&set, Z_LVAL_PP(data)) != 0) {
			zend_error(E_WARNING, "%s", strerror(errno));
			RETURN_FALSE;
		}
	}

	if(sigprocmask(how, &set, NULL)) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	RETURN_TRUE;
}

PHP_FUNCTION(waitpid) {
	long pid, options = 0;
	zval *z_status = NULL;
	int status;
	pid_t child_id;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "lz|l", &pid, &z_status, &options) == FAILURE) {
		RETURN_FALSE;
	}

	child_id = waitpid((pid_t) pid, &status, options);

	if (child_id < 0 && errno != ECHILD) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	zval_dtor(z_status);
	Z_TYPE_P(z_status) = IS_LONG;
	Z_LVAL_P(z_status) = status;

	RETURN_LONG((long) child_id);
}
