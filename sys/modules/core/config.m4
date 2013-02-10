dnl $Id$
dnl config.m4 for Pancake Core Extension

PHP_ARG_ENABLE(Pancake, whether to enable Pancake,
[  --enable-Pancake           Enable Pancake])

if test "$PHP_PANCAKE" != "no"; then
  PHP_NEW_EXTENSION(Pancake, Pancake.c Pancake_coreFunctions.c Pancake_HTTPRequest.c Pancake_invalidHTTPRequestException.c Pancake_MIME.c Pancake_ObjectHandlers.c, $ext_shared)
fi
