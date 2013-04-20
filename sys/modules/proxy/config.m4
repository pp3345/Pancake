dnl $Id$
dnl config.m4 for Pancake Proxy Extension

PHP_ARG_ENABLE(Pancake, whether to enable PancakeProxy,
[  --enable-PancakeProxy           Enable PancakeProxy])

if test "$PHP_PANCAKEPROXY" != "no"; then
  PHP_NEW_EXTENSION(PancakeProxy, PancakeProxy.c, $ext_shared)
fi
