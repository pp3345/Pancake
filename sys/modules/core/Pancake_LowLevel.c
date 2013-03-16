
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
	} while(signal == - 1 && ((errno == EAGAIN && seconds == 99999999) || errno == EINTR));

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
	fflush(NULL);
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

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "la|z", &how, &uset) == FAILURE) {
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

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "lz|l", &pid, &z_status, &options) == FAILURE) {
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

PHP_FUNCTION(socket) {
	long domain, type, protocol;
	int fd;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "lll", &domain, &type, &protocol) == FAILURE) {
		RETURN_FALSE;
	}

	fd = socket(domain, type, protocol);

	if(fd == -1) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	RETURN_LONG((long) fd);
}

PHP_FUNCTION(reuseaddress) {
	long fd;
	int val = 1;
	socklen_t len = sizeof(val);

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &fd) == FAILURE) {
		RETURN_FALSE;
	}

	if(setsockopt(fd, SOL_SOCKET, SO_REUSEADDR, &val, len) == -1) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	RETURN_TRUE;
}

PHP_FUNCTION(bind) {
	long fd, domain, port;
	char *address;
	int address_len, retval;
	struct sockaddr_storage sa_storage;
	struct sockaddr	*sock_type = (struct sockaddr*) &sa_storage;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "lls|l", &fd, &domain, &address, &address_len, &port) == FAILURE) {
		RETURN_FALSE;
	}

	// The bind() syscall is stupid. This is mainly copied from ext/socket

	switch(domain) {
		case AF_UNIX: {
			struct sockaddr_un *sa = (struct sockaddr_un *) sock_type;
			memset(sa, 0, sizeof(sa_storage));
			sa->sun_family = AF_UNIX;
			snprintf(sa->sun_path, 108, "%s", address);
			retval = bind(fd, (struct sockaddr *) sa, SUN_LEN(sa));
			break;
		}
		case AF_INET: {
			struct sockaddr_in *sa = (struct sockaddr_in *) sock_type;
			struct in_addr in;

			memset(sa, 0, sizeof(sa_storage));

			sa->sin_family = AF_INET;
			sa->sin_port = htons((unsigned short) port);
			inet_aton(address, &in);
			sa->sin_addr.s_addr = in.s_addr;

			retval = bind(fd, (struct sockaddr *)sa, sizeof(struct sockaddr_in));
			break;
		}
		case AF_INET6: {
			struct sockaddr_in6 *sa = (struct sockaddr_in6 *) sock_type;
			struct in6_addr in6;

			memset(sa, 0, sizeof(sa_storage));

			sa->sin6_family = AF_INET6;
			sa->sin6_port = htons((unsigned short) port);
			inet_pton(AF_INET6, address, &in6);
			memcpy(&(sa->sin6_addr.s6_addr), &(in6.s6_addr), sizeof(struct in6_addr));

			retval = bind(fd, (struct sockaddr *)sa, sizeof(struct sockaddr_in6));
			break;
		}
	}

	if(retval) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	RETURN_TRUE;
}

PHP_FUNCTION(listen) {
	long fd, backlog = 10;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l|l", &fd, &backlog) == FAILURE) {
		RETURN_FALSE;
	}

	if(listen(fd, backlog)) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	RETURN_TRUE;
}

PHP_FUNCTION(setBlocking) {
	long fd;
	zend_bool blocking;
	int flags;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l|b", &fd, &blocking) == FAILURE) {
		RETURN_FALSE;
	}

	flags = fcntl(fd, F_GETFL);

	if(!blocking) {
		flags |= O_NONBLOCK;
	} else {
		flags &= ~O_NONBLOCK;
	}

	if(fcntl(fd, F_SETFL, flags)) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	RETURN_TRUE;
}

PHP_FUNCTION(write) {
	long fd;
	char *buffer;
	int buffer_len, bytes;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ls", &fd, &buffer, &buffer_len) == FAILURE) {
		RETURN_FALSE;
	}

	bytes = write(fd, buffer, buffer_len);

	if(bytes < 0) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	RETURN_LONG(bytes);
}

PHP_FUNCTION(writeBuffer) {
	long fd;
	int bytes;
	zval *buffer;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "lz", &fd, &buffer) == FAILURE) {
		RETURN_FALSE;
	}

	bytes = write(fd, Z_STRVAL_P(buffer), Z_STRLEN_P(buffer));

	if(bytes < 0) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	if(bytes) {
		int newLen = Z_STRLEN_P(buffer) - bytes + 1;
		char *newBuffer = emalloc(newLen);

		memcpy(newBuffer, Z_STRVAL_P(buffer) + bytes, newLen);
		efree(Z_STRVAL_P(buffer));
		Z_STRVAL_P(buffer) = newBuffer;
		Z_STRLEN_P(buffer) = newLen - 1;
	}

	RETURN_TRUE;
}

PHP_FUNCTION(read) {
	long fd, length;
	int bytes;
	char *buffer;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ll", &fd, &length) == FAILURE) {
		RETURN_FALSE;
	}

	buffer = emalloc(length + 1);
	bytes = recv(fd, buffer, length, 0);

	if(bytes == -1) {
		if(errno != EAGAIN) {
			zend_error(E_WARNING, "%s", strerror(errno));
		}

		efree(buffer);
		RETURN_FALSE;
	}

	if(!bytes) {
		efree(buffer);
		RETURN_EMPTY_STRING();
	}

	buffer = erealloc(buffer, bytes + 1);
	buffer[bytes] = '\0';

	RETURN_STRINGL(buffer, bytes, 0);
}

PHP_FUNCTION(accept) {
	long fd, new_fd;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &fd) == FAILURE) {
		RETURN_FALSE;
	}

	new_fd = accept(fd, NULL, NULL);

	if(new_fd == -1) {
		if(errno != EAGAIN) {
			zend_error(E_WARNING, "%s", strerror(errno));
		}

		RETURN_FALSE;
	}

	RETURN_LONG(new_fd);
}

PHP_FUNCTION(nonBlockingAccept) {
	long fd, new_fd;
#ifndef __linux__
	int flags;
#endif

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &fd) == FAILURE) {
		RETURN_FALSE;
	}

#ifdef __linux__
	new_fd = accept4(fd, NULL, NULL, SOCK_NONBLOCK);
#else
	new_fd = accept(fd, NULL, NULL);
#endif

	if(new_fd == -1) {
		if(errno != EAGAIN) {
			zend_error(E_WARNING, "%s", strerror(errno));
		}

		RETURN_FALSE;
	}

#ifndef __linux__
	flags = fcntl(new_fd, F_GETFL);
	flags |= O_NONBLOCK;
	fcntl(new_fd, F_SETFL, flags);
#endif

	if(!PANCAKE_GLOBALS(naglesAlgorithm)) {
		setsockopt(new_fd, IPPROTO_TCP, TCP_NODELAY, &PANCAKE_GLOBALS(naglesAlgorithm), sizeof(PANCAKE_GLOBALS(naglesAlgorithm)));
	}

	RETURN_LONG(new_fd);
}

PHP_FUNCTION(keepAlive) {
	long fd;
	long set = 0;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l|l", &fd, &set) == FAILURE) {
		RETURN_FALSE;
	}

	if(set) {
		setsockopt(fd, SOL_SOCKET, SO_KEEPALIVE, &set, sizeof(long));
		return;
	} else {
		socklen_t size = sizeof(long);
		getsockopt(fd, SOL_SOCKET, SO_KEEPALIVE, &set, &size);
		RETURN_BOOL(set);
	}
}

PHP_FUNCTION(connect) {
	long fd, port = 0, domain;
	char *address;
	int	retval, address_len;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "lls|l", &fd, &domain, &address, &address_len, &port) == FAILURE) {
		RETURN_FALSE;
	}


	switch(domain) {
		case AF_INET6: {
			struct sockaddr_in6 sin6 = {0};
			struct in6_addr in6;

			memset(&sin6, 0, sizeof(struct sockaddr_in6));

			sin6.sin6_family = AF_INET6;
			sin6.sin6_port   = htons((unsigned short int)port);

			inet_pton(AF_INET6, address, &in6);
			memcpy(&(sin6.sin6_addr.s6_addr), &(in6.s6_addr), sizeof(struct in6_addr));

			retval = connect(fd, (struct sockaddr*) &sin6, sizeof(struct sockaddr_in6));
			break;
		}
		case AF_INET: {
			struct sockaddr_in sin = {0};
			struct in_addr in;

			sin.sin_family = AF_INET;
			sin.sin_port   = htons((unsigned short int)port);

			inet_aton(address, &in);
			sin.sin_addr.s_addr = in.s_addr;

			retval = connect(fd, (struct sockaddr *)&sin, sizeof(struct sockaddr_in));
			break;
		}

		case AF_UNIX: {
			struct sockaddr_un s_un = {0};

			s_un.sun_family = AF_UNIX;
			memcpy(&s_un.sun_path, address, address_len);
			retval = connect(fd, (struct sockaddr *) &s_un, (socklen_t)(XtOffsetOf(struct sockaddr_un, sun_path) + address_len));
			break;
		}
	}

	RETURN_LONG(retval != 0 && errno != EINPROGRESS ? errno : 0);
}

PHP_FUNCTION(close) {
	long fd;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &fd) == FAILURE) {
		RETURN_FALSE;
	}

	close(fd);
}

PHP_FUNCTION(getSockName) {
	long fd;
	zval *address, *port;
	struct sockaddr_storage sa_storage;
	struct sockaddr *sa = (struct sockaddr*) &sa_storage;
	socklen_t size = sizeof(struct sockaddr_storage);

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "lz|z", &fd, &address, &port) == FAILURE) {
		RETURN_FALSE;
	}

	if(getsockname(fd, sa, &size)) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	switch(sa->sa_family) {
		case AF_INET6: {
			struct sockaddr_in6 *sin6 = (struct sockaddr_in6*) sa;
			char addr6[INET6_ADDRSTRLEN+1];

			inet_ntop(AF_INET6, &sin6->sin6_addr, addr6, INET6_ADDRSTRLEN);
			zval_dtor(address);
			ZVAL_STRING(address, addr6, 1);

			zval_dtor(port);
			ZVAL_LONG(port, htons(sin6->sin6_port));

			RETURN_TRUE;
			break;
		}
		case AF_INET: {
			struct sockaddr_in *sin = (struct sockaddr_in*) sa;
			char *addr_string = inet_ntoa(sin->sin_addr);

			zval_dtor(address);
			ZVAL_STRING(address, addr_string, 1);

			zval_dtor(port);
			ZVAL_LONG(port, htons(sin->sin_port));
			RETURN_TRUE;
			break;
		}
		case AF_UNIX: {
			struct sockaddr_un *s_un = (struct sockaddr_un*) sa;

			zval_dtor(address);
			ZVAL_STRING(address, s_un->sun_path, 1);
			RETURN_TRUE;
		}
	}

	RETURN_FALSE;
}

PHP_FUNCTION(getPeerName) {
	long fd;
	zval *address, *port;
	struct sockaddr_storage sa_storage;
	struct sockaddr *sa = (struct sockaddr*) &sa_storage;
	socklen_t size = sizeof(struct sockaddr_storage);

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "lzz", &fd, &address, &port) == FAILURE) {
		RETURN_FALSE;
	}

	if(getpeername(fd, sa, &size)) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	// We will only support AF_INET and AF_INET6 here for the moment
	switch(sa->sa_family) {
		case AF_INET6: {
			struct sockaddr_in6 *sin6 = (struct sockaddr_in6*) sa;
			char addr6[INET6_ADDRSTRLEN+1];

			inet_ntop(AF_INET6, &sin6->sin6_addr, addr6, INET6_ADDRSTRLEN);
			zval_dtor(address);
			ZVAL_STRING(address, addr6, 1);

			zval_dtor(port);
			ZVAL_LONG(port, htons(sin6->sin6_port));

			RETURN_TRUE;
			break;
		}
		case AF_INET: {
			struct sockaddr_in *sin = (struct sockaddr_in*) sa;
			char *addr_string;

			addr_string = inet_ntoa(sin->sin_addr);

			zval_dtor(address);
			ZVAL_STRING(address, addr_string, 1);

			zval_dtor(port);
			ZVAL_LONG(port, htons(sin->sin_port));
			RETURN_TRUE;
			break;
		}
	}

	RETURN_FALSE;
}

static inline void PancakeHashTableToFDSet(HashTable *table, fd_set *set, int *max) {
	zval **data;

	for(zend_hash_internal_pointer_reset(table);
		zend_hash_get_current_data(table, (void**) &data) == SUCCESS;
		zend_hash_move_forward(table)) {
		if(Z_LVAL_PP(data) > FD_SETSIZE) // Protect from overflow
			continue;

		FD_SET(Z_LVAL_PP(data), set);

		if(Z_LVAL_PP(data) > *max) {
			*max = Z_LVAL_PP(data);
		}
	}
}

static inline void PancakeFDSetToHashTable(fd_set *set, HashTable **table) {
	zval **data;
	HashTable *newTable;

	ALLOC_HASHTABLE(newTable);
	zend_hash_init(newTable, 1, NULL, ZVAL_PTR_DTOR, 0);

	for(zend_hash_internal_pointer_reset(*table);
		zend_hash_get_current_data(*table, (void**) &data) == SUCCESS;
		zend_hash_move_forward(*table)) {
		if(Z_LVAL_PP(data) > FD_SETSIZE)
			continue;

		if(FD_ISSET(Z_LVAL_PP(data), set)) {
			Z_ADDREF_PP(data);
			zend_hash_index_update(newTable, Z_LVAL_PP(data), (void*) data, sizeof(zval*), NULL);
		}
	}

	zend_hash_destroy(*table);
	efree(*table);
	*table = newTable;
}

PHP_FUNCTION(select) {
	zval *read, *write = NULL;
	fd_set read_set, write_set;
	long microseconds = 0;
	struct timeval time;
	struct timeval *time_p = NULL;
	int retval, max = 0;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z|zl", &read, &write, &microseconds) == FAILURE) {
		RETURN_FALSE;
	}

	FD_ZERO(&read_set);
	FD_ZERO(&write_set);

	PancakeHashTableToFDSet(Z_ARRVAL_P(read), &read_set, &max);
	if(write) {
		PancakeHashTableToFDSet(Z_ARRVAL_P(write), &write_set, &max);
	}

	if(microseconds) {
		time.tv_sec = 0;
		time.tv_usec = microseconds;
		time_p = &time;
	}

	retval = select(max + 1, &read_set, &write_set, NULL, time_p);

	if(retval == -1) {
		zend_error(E_WARNING, "%s", strerror(errno));
		RETURN_FALSE;
	}

	PancakeFDSetToHashTable(&read_set, &Z_ARRVAL_P(read));
	if(write) {
		PancakeFDSetToHashTable(&write_set, &Z_ARRVAL_P(write));
	}

	RETURN_TRUE;
}

PHP_FUNCTION(adjustSendBufferSize) {
	long fd, goalSize, actualSize = 2048;
	socklen_t size = sizeof(long);

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ll", &fd, &goalSize) == FAILURE) {
		RETURN_FALSE;
	}

	goalSize += 768; // 768 byte overhead for protocol headers

	getsockopt(fd, SOL_SOCKET, SO_SNDBUF, &actualSize, &size);

	if(actualSize < goalSize) {
		setsockopt(fd, SOL_SOCKET, SO_SNDBUF, &goalSize, size);
		getsockopt(fd, SOL_SOCKET, SO_SNDBUF, &actualSize, &size);
		if(actualSize < goalSize) {
			RETURN_LONG(actualSize - 768);
		}
	}

	RETURN_FALSE;
}

PHP_FUNCTION(naglesAlgorithm) {
	zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "b", &PANCAKE_GLOBALS(naglesAlgorithm));
}
