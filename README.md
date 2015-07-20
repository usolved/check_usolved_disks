# check_usolved_disks

## Overview

This PHP Nagios plugin automaticly detects all disks/partitions of a Windows or Linux operating system and checks the free space.
You don't need a check for every disk and just can use this check that'll return all available disks on the system.
If a system gets a new partition you don't need to change your check.

The plugin also returns performance data and you can also exclude disks that you don't want to check.

## Authors

Ricardo Klement ([www.usolved.net](http://usolved.net))

## Installation

Just copy the file check_usolved_disks.php into your Nagios plugin directory.
For example into the path /usr/local/nagios/libexec/

Add execution permission for the nagios user on check_usolved_disks.php.
If you have at least PHP 5 this plugin should run out-of-the-box.

Make sure to have the PHP SNMP module installed and enabled in your php.ini.

&gt; apt-get install php5-snmp (Ubuntu, Debian, ...)
or
&gt; yum install php-snmp (RedHat, CentOS, ...)

## Usage

### Test on command line
If you are in the Nagios plugin directory execute this command:

```
./check_usolved_disks.php -H localhost -C public -w 90 -c 95
```

The output should look like this on a Linux machine:

```
Disks OK // / (37%), /boot (30%), /dev/shm (0%)
/ - Size: 50.61 GB / Used: 18.73 GB (37% used)
/boot - Size: 0.1 GB / Used: 0.03 GB (30% used)
/dev/shm - Size: 2.91 GB / Used: 0 GB (0% used)
```


On a Windows machine the output could look something like this:

```
Disks OK // C: (20.5%), D: (87.8%)
C: - Size: 59.66 GB / Used: 12.24 GB (20.5% used)
D: - Size: 80 GB / Used: 70.22 GB (87.8% used)
```

Here are all arguments that can be used with this plugin:

```
-H <host address>
Give the host address with the IP address or FQDN

-C <snmp community>
Give the SNMP Community String

-w <warn>
Warning treshold in percent

-c <crit>
Critical treshold in percent

[-V <snmp version>]
Optional: SNMP version 1 or 2c are supported, if argument not given version 1 is used by default

[-P <perfdata>]
Optional: Give 'yes' as argument if you wish performace data output

[-E '<exclude partitions>']
Optional: Exclude partitions with a comma separated list on Windows like 'D:,E:' (with or without colon) or on Linux '/var,/tmp'
```

### Install in Nagios

Edit your **commands.cfg** and add the following.

Example for basic check:

```
define command {
    command_name    check_usolved_disks
    command_line    $USER1$/check_usolved_disks.php -H $HOSTADDRESS$ -C $_HOSTSNMPCOMMUNITY$ -w $ARG1$ -c $ARG2$
}
```

Example for using performance data, specific snmp version and excluding some partitions:

```
define command {
    command_name    check_usolved_disks
    command_line    $USER1$/check_usolved_disks.php -H $HOSTADDRESS$ -C $_HOSTSNMPCOMMUNITY$ -V $_HOSTSNMPVERSION$ -w $ARG1$ -c $ARG2$ -P $ARG3$ -E $ARG4$
}
```

Edit your **services.cfg** and add the following.

Example for basic check:

```
define service{
	host_name				Test-Server
	service_description		Disks
	use						generic-service
	check_command			check_usolved_disks!90!95
}
```

Example for using performance data and excluding some partitions:

```
define service{
	host_name				Test-Server
	service_description		Disks
	use						generic-service
	check_command			check_usolved_disks!90!95!yes!'D:,E:'
}
```

## What's new

v1.1
Bugfix: If the disk capacity exceeded the 32 bit snmp value, the disk size wasn't calculated and listed correctly

v1.0
Initial release

