# [Pancake HTTP Server](http://pancakehttp.net)

[![Build Status](http://ci.pp3345.net/job/Pancake/badge/icon)](http://ci.pp3345.net/job/Pancake/)

## What is Pancake?

Pancake is a lightweight and fast web server created by [Yussuf Khalil](https://github.com/pp3345), mainly written in C, PHP and Moody.
The main goal of Pancake is achieving the best possible PHP script execution performance using its own PHP SAPI, however,
you may use nearly any server-side scripting language using the FastCGI and AJP13 interfaces. Thanks to the non-blocking server architecture
Pancake can also handle very high concurrency loads. Try it out!

## System requirements

* Linux >= 2.6.9
* PHP >= 5.4.0 (PHP >= 5.4.10 recommended, PHP 5.5 not supported yet)
* i686 or x86_64 processor or Raspberry Pi (no *official* support for other ARM architectures)
* OpenSSL >= 0.9.8 for HTTPS (optional)

## Installation

Installing Pancake is quite easy. Simply download the current version and extract it anywhere on the target computer.
Then add the executable flag to the Pancake/sys/pancake.sh file and run it. On most systems the following commands should do the job:

    wget -O pancake.tar.gz http://pancakehttp.net/latest
    tar -zxvf pancake.tar.gz
    mv Pancake-* Pancake
    cd Pancake/sys
    chmod +x pancake.sh
    sudo ./pancake.sh

As you can see, `sudo` is used to run `pancake.sh` as root user. In case you're already root or your system uses another command (for example `su`)
please choose the appropriate command. Make sure you are allowed to write in the directory you want to install Pancake in.

## Upgrading from older versions

You can simply overwrite your old Pancake. No configurations or vHosts will go lost. However, if there is an `UPGRADING` file in the root directory
of the new Pancake, please make sure to read it first. It usually contains information about possible incompatibilities when upgrading.

## Documentation

Documentation and information about the configuration of Pancake can be found at the [Pancake Wiki](https://github.com/pp3345/Pancake/wiki).

## Bundled init script

Pancake has an official init script for Debian GNU/Linux (and Debian-derived distributions like Ubuntu or Raspbian) bundled.
The script is located in the main directory of Pancake. Run the following commands to use it:

    cd <PancakeDirectory>
    sudo cp init /etc/init.d/pancake
    sudo chmod +x /etc/init.d/pancake
    sudo update-rc.d pancake defaults enable
    
In case your Pancake is **not** installed in /usr/local/Pancake, please change the `DAEMON` line in the script to match your Pancake installation path.

You can then start your Pancake using

    sudo /etc/init.d/pancake start
    
and stop it again using

    sudo /etc/init.d/pancake stop
    
Pancake will also be automatically run when your computer starts up.

The script is bundled with kind permission of [Jan Erik Petersen](https://github.com/marco01809).

## Contact

If you need help or have a question about Pancake, please feel free to write a mail to [support@pancakehttp.net](mailto:support@pancakehttp.net).
I love to hear from you. :-)

## Donations

If you love your tasty Pancake and my work, how about donating a few bucks via PayPal? :-) [![PayPal Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=89CFQ7SFX3MWY)

## License

All Pancake source files are subject to the GNU Lesser General Public License v3. See LICENSE and LICENSE.GPL for copies of GNU LGPL v3 and GNU GPL v3.
In case you have any questions about the license, please contact [support@pancakehttp.net](mailto:support@pancakehttp.net).
