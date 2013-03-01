dnl $Id$
dnl config.m4 for Pancake Transport Layer Security Extension

PHP_ARG_ENABLE(PancakeTLS, whether to enable PancakeTLS,
[  --enable-PancakeTLS           Enable PancakeTLS])

if test "$PHP_PANCAKETLS" != "no"; then
  PHP_NEW_EXTENSION(PancakeTLS, PancakeTLS.c, $ext_shared)
fi