# [Pancake HTTP Server](http://pancakehttp.net)

## What is Pancake?

Pancake is a lightweight and fast web server created by [Yussuf Khalil](https://github.com/pp3345), mainly written in C, PHP and Moody.
The main goal of Pancake is achieving the best possible PHP script execution performance using its own PHP SAPI, however,
you may use nearly any server-side scripting language using the FastCGI and AJP13 interfaces. Thanks to the non-blocking server architecture
Pancake can also handle very high concurrency loads. Try it out!

## System requirements

* Linux
* PHP 5.4.0 or newer
* i686 or x86_64 processor or Raspberry Pi (no *official* support for other ARM architectures)

## Installation

Installing Pancake is quite easy. Simply download the current version and extract it anywhere on the target computer.
Then add the executable flag to the Pancake/sys/pancake.sh file and run it. On most systems the following commands should do the job:

    wget http://github.com/pp3345/Pancake/tarball/<version>
    tar -zxvf <version>
    mv pp3345-Pancake-* Pancake
    cd pp3345-Pancake/sys
    chmod +x pancake.sh
    sudo ./pancake.sh

As you can see, `sudo` is used to run `pancake.sh` as root user. In case you're already root or your system uses another command (for example `su`)
please choose the appropriate command. Make sure you are allowed to write in the directory you want to install Pancake in.

## Upgrading from older versions

You can simply overwrite your old Pancake. No configurations or vHosts will go lost. However, if there is an `UPGRADING` file in the root directory
of the new Pancake, please make sure to read it first. It usually contains information about possible incompatibilities when upgrading.

## Documentation

Documentation and information about the configuration of Pancake can be found at the [Pancake Wiki](https://github.com/pp3345/Pancake/wiki).

## Contact

If you need help or have a question about Pancake, please feel free to write a mail to [support@pancakehttp.net](mailto:support@pancakehttp.net).
I love to hear from you. :-)

## Donations

If you love your tasty Pancake and my work, how about donating a few bucks via PayPal? :-) [![PayPal Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=89CFQ7SFX3MWY)

## License

Pancake is currently licensed under its own [license](http://pancakehttp.net/license/).
However, the license will probably change soon. In case you have any questions about the license, please contact [support@pancakehttp.net](mailto:support@pancakehttp.net).