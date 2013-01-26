#!/bin/bash

#   /****************************************************************/
#   /* Pancake                                                      */
#   /* pancake.sh                                                   */
#   /* 2012 Yussuf Khalil                                           */
#   /* License: http://pancakehttp.net/license/                     */
#   /****************************************************************/

FILENAME=`readlink -f $0`
DIRNAME=`dirname $FILENAME`
cd $DIRNAME

PHPCOMMAND=php
ARCH=`uname -m`
echo "Found $ARCH processor"

PHPMAJOR=`$PHPCOMMAND -r "echo PHP_MAJOR_VERSION;"`
PHPMINOR=`$PHPCOMMAND -r "echo PHP_MINOR_VERSION;"`
echo "Found PHP $PHPMAJOR.$PHPMINOR"

if test $PHPMAJOR != "5" || (test $PHPMINOR != "3" && test $PHPMINOR != "4" && test $PHPMINOR != "5")
then
    echo "Incompatible PHP-Version. Please install PHP 5.3.0 or newer (PHP 5.4 recommended)"
    exit
fi

if test $ARCH == "i386" || test $ARCH == "i486" || test $ARCH == "i586" || test $ARCH == "i686"
then
    ARCH=x86
elif test $ARCH == "armv6l" || test $ARCH == "armv7l"
then
	ARCH=armhf
fi

if [ -d ./natives ];
then
	chmod -R +x ./natives/*
fi

if [ -n "$1" ] && test $1 == "--use-malloc"
then
	USE_ZEND_ALLOC_SET=1
	export USE_ZEND_ALLOC=0
fi

if [ -x ./natives/DeepTrace/$ARCH\_$PHPMAJOR$PHPMINOR.so ];
then
	if [ -x ./natives/base/$ARCH\_$PHPMAJOR$PHPMINOR.so ];
	then
    	$PHPCOMMAND -d zend_extension=./natives/DeepTrace/$ARCH\_$PHPMAJOR$PHPMINOR.so -d extension=./natives/base/$ARCH\_$PHPMAJOR$PHPMINOR.so $DIRNAME/syscore.php $1
    else
    	echo "No compiled PancakeBase natives found (looking for ./natives/base/$ARCH""_$PHPMAJOR$PHPMINOR.so) - Please compile PancakeBase for your system using compile.php"
    fi
else
    echo "No compatible DeepTrace-extension found (looking for ./natives/DeepTrace/$ARCH""_$PHPMAJOR$PHPMINOR.so) - Please compile DeepTrace for your system and make sure it is executable"
fi

if [ -n "$USE_ZEND_ALLOC_SET" ]
then
	export USE_ZEND_ALLOC=1
fi
