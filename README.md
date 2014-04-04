# check_usolved_disks

## Overview

This PHP Nagios plugin automaticly detects all disks/partitions of a Windows or Linux operating system and checks the free space.
You don't need a check for every disk and just use this one check that'll return all available disks on the system.
If a system gets a new partition you need need to change your check.

The plugin also returns performance data and you can exclude disks that you don't want to check.

## Installation

Just copy the file check_usolved_disks.php into your Nagios plugin directory.
For example into the path /usr/local/nagios/libexec/

Give check_usolved_disks.php the permission for execution for the nagios user.
If you have at least PHP 5 this plugin should run out-of-the-box.

## Usage

### Test on command line
If you are in the Nagios plugin directory execute this command:

<pre><code>
./check_usolved_disks -H localhost -C public -w 90 -c 95
</code></pre>

This should output something like this on a Linux machine:

<pre><code>
Disks OK // / (37%), /boot (30%), /dev/shm (0%)
/ - Size: 50.61 GB / Used: 18.73 GB (37% used)
/boot - Size: 0.1 GB / Used: 0.03 GB (30% used)
/dev/shm - Size: 2.91 GB / Used: 0 GB (0% used)
</code></pre>


On a Windows machine the output could look something like this:

<pre><code>
Disks OK // C: (20.5%), D: (87.8%)
C: - Size: 59.66 GB / Used: 12.24 GB (20.5% used)
D: - Size: 80 GB / Used: 70.22 GB (87.8% used)
</code></pre>

Here are all arguments that can be used with this plugin:

<pre><code>
-H &lt;host address&gt;
Give the host address with the IP address or FQDN

-C &lt;snmp community&gt;
Give the SNMP Community String

-w &lt;warn&gt;
Warning treshold in percent

-c &lt;crit&gt;
Critical treshold in percent

[-V &lt;snmp version&gt;]
Optional: SNMP version 1 or 2c are supported, if argument not given version 1 is used by default

[-P &lt;perfdata&gt;]
Optional: Give 'yes' as argument if you wish performace data output

[-E '&lt;exclude partitions&gt;']
Optional: Exclude partitions with a comma separated list on Windows like 'D:,E:' (with or without colon) or on Linux '/var,/tmp'
</code></pre>

### Install in Nagios

Edit your **commands.cfg** and add the following.

Example for basic check:

<pre><code>
define command {
    command_name    check_usolved_disks
    command_line    $USER1$/check_usolved_disks.php -H $HOSTADDRESS$ -C $_HOSTSNMPCOMMUNITY$ -w $ARG1$ -c $ARG2$
}
</code></pre>

Example for using performance data, specific snmp version and excluding some partitions:

<pre><code>
define command {
    command_name    check_usolved_disks
    command_line    $USER1$/check_usolved_disks.php -H $HOSTADDRESS$ -C $_HOSTSNMPCOMMUNITY$ -V $_HOSTSNMPVERSION$ -w $ARG1$ -c $ARG2$ -P $ARG3$ -E $ARG4$
}
</code></pre>

Edit your **services.cfg** and add the following.

Example for basic check:

<pre><code>
define service{
	host_name			Test-Server
	use					generic-service
	check_command		check_usolved_disks!90!95
}
</code></pre>

Example for using performance data and excluding some partitions:

<pre><code>
define service{
	host_name			Test-Server
	use					generic-service
	check_command		check_usolved_disks!90!95!yes!'D:,E:'
}
</code></pre>

