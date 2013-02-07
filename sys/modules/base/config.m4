dnl $Id$
dnl config.m4 for extension PancakeBase

PHP_ARG_ENABLE(PancakeBase, whether to enable PancakeBase support,
[  --enable-PancakeBase           Enable PancakeBase support])

if test "$PHP_PANCAKEBASE" != "no"; then
  PHP_NEW_EXTENSION(PancakeBase, PancakeBase.c Pancake_coreFunctions.c Pancake_HTTPRequest.c Pancake_invalidHTTPRequestException.c Pancake_MIME.c Pancake_ObjectHandlers.c, $ext_shared)
fi
