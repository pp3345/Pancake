#!/bin/bash

#   /****************************************************************/
#   /* Pancake                                                      */
#   /* pancake.sh                                                   */
#   /* 2012 - 2013 Yussuf Khalil                                    */
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

if test $PHPMAJOR != "5" || (test $PHPMINOR != "4" && test $PHPMINOR != "5")
then
        echo "Incompatible PHP-Version. Please install PHP 5.4.0 or newer." >&2
        if test $PHPMINOR == 3
        then
                echo "Support for PHP 5.3 has been dropped in Pancake 1.3" >&2
        fi
        echo "In case you are using Debian GNU/Linux you may install a newer PHP version from the dotdeb repository (see http://dotdeb.org for more information and instructions)" >&2
        exit 3
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
    if [ -x ./natives/core/$ARCH\_$PHPMAJOR$PHPMINOR.so ];
    then
        $PHPCOMMAND -d zend_extension=./natives/DeepTrace/$ARCH\_$PHPMAJOR$PHPMINOR.so -d extension=./natives/core/$ARCH\_$PHPMAJOR$PHPMINOR.so $DIRNAME/syscore.php $1 $2 $3
        [ $? != 0 ] && exit 2
    else
        echo "No compiled Pancake natives found (looking for ./natives/core/$ARCH""_$PHPMAJOR$PHPMINOR.so) - Please compile Pancake for your system and make sure it is executable" >&2
        exit 4
    fi
else
    echo "No compatible DeepTrace-extension found (looking for ./natives/DeepTrace/$ARCH""_$PHPMAJOR$PHPMINOR.so) - Please compile DeepTrace for your system and make sure it is executable" >&2
    exit 5
fi

if [ -n "$USE_ZEND_ALLOC_SET" ]
then
        export USE_ZEND_ALLOC=1
fi
