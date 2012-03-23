#!/bin/bash

#   /****************************************************************/
#   /* Pancake                                                      */
#   /* pancake.sh                                                   */
#   /* 2012 Yussuf "pp3345" Khalil                                  */
#   /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
#   /****************************************************************/

PHPCOMMAND=php
ARCH=`uname -m`
PHPMAJOR=`$PHPCOMMAND -r "echo PHP_MAJOR_VERSION;"`
PHPMINOR=`$PHPCOMMAND -r "echo PHP_MINOR_VERSION;"`
echo "Found $ARCH architecture"
echo "Found PHP $PHPMAJOR.$PHPMINOR"
if test $PHPMAJOR != "5" || (test $PHPMINOR != "3" && test $PHPMINOR != "4")
then
    echo "Incompatible PHP-Version. Please install PHP 5.3.0 or newer (PHP 5.4 recommended)"
    exit
fi
if test $ARCH == "x86_64" 
then
	$PHPCOMMAND -d zend_extension=./ext/x86_64_$PHPMAJOR$PHPMINOR.so syscore.php
elif test $ARCH == "x86" || test $ARCH == "i386" || test $ARCH == "i486" || test $ARCH == "i586" || test $ARCH == "i686" 
then
	$PHPCOMMAND -d zend_extension=./ext/x86_$PHPMAJOR$PHPMINOR.so syscore.php
else
	echo "Unknown architecture. Please compile the DeepTrace-extension by yourself"
fi
