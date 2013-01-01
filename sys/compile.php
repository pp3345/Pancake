<?php

$arch = php_uname('m');

echo "Compiling PancakeBase for PHP " . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . " on " . $arch . "\n";
echo "Please wait...\n";

switch($arch) {
	case "i386":
	case "i486":
	case "i586":
	case "i686":
		$arch = "x86";
		break;
	case "armv6l":
	case "armv7l":
		$arch = "armhf";
}

$cwd = getcwd();
chdir($cwd . '/modules/base/');

exec('make clean');
exec('phpize');
exec('./configure');
exec('make');

if(!is_dir($cwd . '/natives/base/')) {
	mkdir($cwd . '/natives/base/', 0777, true);
}

copy('modules/PancakeBase.so', $cwd . '/natives/base/' . $arch . '_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.so');

echo "Done.\n";

?>