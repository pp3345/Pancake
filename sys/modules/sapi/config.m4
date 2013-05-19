dnl $Id$
dnl config.m4 for Pancake SAPI Extension

PHP_ARG_ENABLE(Pancake, whether to enable PancakeSAPI,
[  --enable-PancakeSAPI           Enable PancakeSAPI])

if test "$PHP_PANCAKESAPI" != "no"; then
  PHP_NEW_EXTENSION(PancakeSAPI, PancakeSAPI.c PancakeSAPI_Hooks.c PancakeSAPI_PHPDependencies.c PancakeSAPI_Functions.c PancakeSAPI_Globals.c, $ext_shared)
fi
