dnl $Id$
dnl config.m4 for Pancake SAPIClient Extension

PHP_ARG_ENABLE(Pancake, whether to enable PancakeSAPIClient,
[  --enable-PancakeSAPIClient           Enable PancakeSAPIClient])

if test "$PHP_PANCAKESAPICLIENT" != "no"; then
  PHP_NEW_EXTENSION(PancakeSAPIClient, PancakeSAPIClient.c, $ext_shared)
fi
