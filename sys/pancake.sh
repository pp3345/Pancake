#!/bin/bash

#   /****************************************************************/
#   /* Pancake                                                      */
#   /* pancake.sh                                                   */
#   /* 2012 Yussuf "pp3345" Khalil                                  */
#   /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
#   /****************************************************************/

FILENAME=`readlink -f $0`
DIRNAME=`dirname $FILENAME`
cd $DIRNAME

PHPCOMMAND=php
ARCH=`uname -m`
PHPMAJOR=`$PHPCOMMAND -r "echo PHP_MAJOR_VERSION;"`
PHPMINOR=`$PHPCOMMAND -r "echo PHP_MINOR_VERSION;"`
echo "Found $ARCH processor architecture"
echo "Found PHP $PHPMAJOR.$PHPMINOR"
if test $PHPMAJOR != "5" || (test $PHPMINOR != "3" && test $PHPMINOR != "4")
then
    echo "Incompatible PHP-Version. Please install PHP 5.3.0 or newer (PHP 5.4 recommended)"
    exit
fi

if test $ARCH == "i386" || test $ARCH == "i486" || test $ARCH == "i586" || test $ARCH == "i686"
then
    ARCH=x86
fi

if [ -x ./ext/$ARCH\_$PHPMAJOR$PHPMINOR.so ];
then
    $PHPCOMMAND -d zend_extension=./ext/$ARCH\_$PHPMAJOR$PHPMINOR.so $DIRNAME/syscore.php $1
else
    echo "No compatible DeepTrace-extension found (looking for ./ext/$ARCH""_$PHPMAJOR$PHPMINOR.so) - Please compile DeepTrace for your system and make sure it is executable"
fi
