dnl $Id$
dnl config.m4 for Pancake Transport Layer Security Extension

PHP_ARG_WITH(PancakeTLS, whether to enable PancakeTLS,
[  --with-PancakeTLS[=DIR]           Pancake TLS module])

if test "$PHP_PANCAKETLS" != "no"; then
  PHP_NEW_EXTENSION(PancakeTLS, PancakeTLS.c, $ext_shared)
  PHP_SUBST(PANCAKETLS_SHARED_LIBADD)
  
  AC_CHECK_LIB(ssl, DSA_get_default_method, AC_DEFINE(HAVE_DSA_DEFAULT_METHOD, 1, [OpenSSL 0.9.8 or later]), AC_MSG_ERROR([OpenSSL not found or too old version. Please install OpenSSL 0.9.8 or newer.]))

  PHP_OPENSSL=yes

  PHP_SETUP_OPENSSL(PANCAKETLS_SHARED_LIBADD, 
  [
    AC_DEFINE(HAVE_PANCAKETLS_EXT,1,[ ])
  ], [
    AC_MSG_ERROR([OpenSSL check failed. Please check config.log for more information.])
  ])
fi