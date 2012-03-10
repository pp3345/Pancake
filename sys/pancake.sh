#!/bin/bash

#   /****************************************************************/
#   /* Pancake                                                      */
#   /* pancake.sh                                                   */
#   /* 2012 Yussuf "pp3345" Khalil                                  */
#   /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
#   /****************************************************************/

ARCH=`uname -m`
echo "Found $ARCH architecture"
if test $ARCH == "x86_64" 
then
	php -d zend_extension=./ext/x86_64.so syscore.php
elif test $ARCH == "x86" || test $ARCH == "i386" || test $ARCH == "i486" || test $ARCH == "i586" || test $ARCH == "i686" 
then
	php -d zend_extension=./ext/x86.so syscore.php
else
	echo "Unknown architecture. Please compile the DeepTrace-extension by yourself"
fi
