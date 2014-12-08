#!/usr/bin/php
<?php

/*
Automaticly checks all partitions of a Windows or Linux operating system.
You don't need special libraries. If you have PHP 5 or higher installed it should be working.

Copyright (c) 2014 www.usolved.net 
Published under https://github.com/usolved/check_usolved_disks


This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty
of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/


//---------------------------------------------------------------------------
//------------------------------- Functions ---------------------------------

function show_help($help_for)
{
	if(empty($help_for))
		$help_for = "";
	
	if($help_for == "ERROR_ARGUMENT_H")
	{
		echo "Unknown - argument -H (host address) is required but missing\n";
		exit(3);
	}
	else if($help_for == "ERROR_ARGUMENT_C")
	{
		echo "Unknown - argument -C (snmp community string) is required but missing\n";
		exit(3);
	}
	else if($help_for == "ERROR_ARGUMENT_c")
	{
		echo "Unknown - argument -c (scritical treshold) is required but missing\n";
		exit(3);
	}
	else if($help_for == "ERROR_ARGUMENT_w")
	{
		echo "Unknown - argument -w (warning treshold) is required but missing\n";
		exit(3);
	}
	else if($help_for == "SNMP")
	{
		echo "Unknown - Could't read SNMP information. Properly the host isn't configured correctly for SNMP or wrong SNMP version was given.\n";
		exit(3);
	}
	else if($help_for == "SNMP_MODULE")
	{
		echo "Unknown - PHP SNMP mpdule isn't enabled. Please check if you have installed/enabled the snmp extension for PHP.\n";
		exit(3);
	}
	else if($help_for == "ERROR_ARGUMENT_NUMERIC")
	{	
		echo "Unknown - Warning and Critical have to be numeric.\n";
		exit(3);
	}
	else
	{
		echo "Usage:";
		echo "
	./".basename(__FILE__)." -H <host address> -C <snmp community> -w <warn> -c <crit> [-V <snmp version>] [-P <perfdata>] [-E '<exclude partitions>']\n\n";
		
		echo "Options:";
		echo "
	./".basename(__FILE__)."\n
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
	\n";

		echo "Example:";
		echo "
	./".basename(__FILE__)." -H localhost -C public -V 2 -w 90 -c 95 -P yes -E 'D:,E:'\n\n";
		
		exit(3);
	}
}



function snmp_walk($snmp_host, $snmp_community, $snmp_oid, $snmp_version)
{
	if(extension_loaded("snmp"))
	{

		if($snmp_version == "1")
		{
			if($snmp_return = @snmpwalk($snmp_host, $snmp_community, $snmp_oid))
				return $snmp_return;
			else
				show_help("SNMP");
		}
		else if($snmp_version == "2c" || $snmp_version == "2")
		{
			if($snmp_return = @snmp2_walk($snmp_host, $snmp_community, $snmp_oid))
				return $snmp_return;
			else
				show_help("SNMP");
		}
		else
			show_help("SNMP");
	}
	else
		show_help("SNMP_MODULE");
}


//---------------------------------------------------------------------------
//----------------------------- Check arguments -----------------------------

//get arguments from cli
$arguments 	= array();
$arguments 	= getopt("H:C:V:w:c:E:P:");

if(is_array($arguments) && count($arguments) < 4)
	show_help("");

if((isset($arguments['w']) && !is_numeric($arguments['w'])) || (isset($arguments['c']) && !is_numeric($arguments['c'])))
	show_help("ERROR_ARGUMENT_NUMERIC");
	

//---------------------------------------------------------------------------
//---------------- Get arguments and set variables --------------------------


//get and check host address argument
if(isset($arguments['H']))
	$snmp_host				= $arguments['H'];
else
	show_help("ERROR_ARGUMENT_H");

//get and check snmp community string argument
if(isset($arguments['C']))
	$snmp_community			= $arguments['C'];
else
	show_help("ERROR_ARGUMENT_C");

//get and check warning argument
if(isset($arguments['w']))
	$snmp_warning			= $arguments['w'];
else
	show_help("ERROR_ARGUMENT_w");

//get and check critical argument
if(isset($arguments['c']))
	$snmp_critical			= $arguments['c'];	
else
	show_help("ERROR_ARGUMENT_c");
	
//get and check snmp version argument
if(isset($arguments['V']))
	$snmp_version			= $arguments['V'];
else
	$snmp_version			= 1;

//get and check perfdata argument
if(isset($arguments['P']))
	$perfdata			= $arguments['P'];
else
	$perfdata			= "";

$output_snmp_exclude	= "";
$snmp_exclude			= "";
if(!empty($arguments['E']))
{
	$snmp_exclude			= $arguments['E'];
	$output_snmp_exclude	= $snmp_exclude;
}

$output_perfdata 		= "";		//define string for performance data output
$exit_code				= 0;		//set default exit code to "ok"
$storage_info[][]		= array();	//define array to store disk informations



//----------------------------------------------------------------------------
//------------------------------ Set OID paths -------------------------------

$snmp_oid_hrStorageDesc	= ".1.3.6.1.2.1.25.2.3.1.3";
$snmp_oid_hrStorageUnit	= ".1.3.6.1.2.1.25.2.3.1.4";
$snmp_oid_hrStorageSize	= ".1.3.6.1.2.1.25.2.3.1.5";
$snmp_oid_hrStorageUsed	= ".1.3.6.1.2.1.25.2.3.1.6";
$snmp_oid_hrStorageType	= ".1.3.6.1.2.1.25.2.3.1.2";
$snmp_oid_sysDescr		= ".1.3.6.1.2.1.1.1";



//---------------------------------------------------------------------------
//--------------- Walk through SNMP informations from server ----------------

$i	= 0;
$hrStorageDesc = snmp_walk($snmp_host, $snmp_community, $snmp_oid_hrStorageDesc, $snmp_version);
foreach($hrStorageDesc as $hrStorageDesc_value)
{
	$storage_info[$i]['hrStorageDesc'] = str_replace("STRING: ","",$hrStorageDesc_value);
	$i++;
}


$i 	= 0;
$hrStorageSize = snmp_walk($snmp_host, $snmp_community, $snmp_oid_hrStorageSize, $snmp_version);
foreach($hrStorageSize as $hrStorageSize_value)
{
	$storage_info[$i]['hrStorageSize'] = str_replace("INTEGER: ","",$hrStorageSize_value);
	$i++;
}

$i 	= 0;
$hrStorageUsed = snmp_walk($snmp_host, $snmp_community, $snmp_oid_hrStorageUsed, $snmp_version);
foreach($hrStorageUsed as $hrStorageUsed_value)
{
	$storage_info[$i]['hrStorageUsed'] = str_replace("INTEGER: ","",$hrStorageUsed_value);
	$i++;
}

$i 	= 0;
$hrStorageUnit = snmp_walk($snmp_host, $snmp_community, $snmp_oid_hrStorageUnit, $snmp_version);
foreach($hrStorageUnit as $hrStorageUnit_value)
{
	$hrStorageUnit_value	= str_replace("INTEGER: ","",$hrStorageUnit_value);
	$hrStorageUnit_value	= str_replace(" Bytes","",$hrStorageUnit_value);

	$storage_info[$i]['hrStorageUnit'] = $hrStorageUnit_value;
	$i++;
}

$i 	= 0;
$hrStorageType = snmp_walk($snmp_host, $snmp_community, $snmp_oid_hrStorageType, $snmp_version);
foreach($hrStorageType as $hrStorageType_value)
{
	$hrStorageType_value	= str_replace("INTEGER: ","",$hrStorageType_value);
	$hrStorageType_value	= str_replace(" Bytes","",$hrStorageType_value);

	$storage_info[$i]['hrStorageType'] = $hrStorageType_value;
	$i++;
}

$sysDescr = snmp_walk($snmp_host, $snmp_community, $snmp_oid_sysDescr, $snmp_version);


//---------------------------------------------------------------------------
//--------------------- Loop throught all partitions ------------------------

$i 							= 0;
$output_string_head_error	= "";
$output_string_head			= "";
$output_string				= "";

if(!empty($snmp_exclude))
	$snmp_exclude			= explode(",",$snmp_exclude);

foreach($storage_info as $storage_info_value)
{
	if(empty($storage_info_value['hrStorageDesc']))
	{
		echo "Error - No SNMP response\n";
		exit(3);
	}

	$output_storage = true;

	$hrStorageDesc_output	= $storage_info_value['hrStorageDesc'];
	$hrStorageType_output	= $storage_info_value['hrStorageType'];
	
	//if the disk size is bigger than the 32 bit snmp value could store it, then add value to correct the value
	$hrStorageSize = $storage_info_value['hrStorageSize'] < 0 ? $storage_info_value['hrStorageSize']  + 4294967296 : $storage_info_value['hrStorageSize'];
	$hrStorageUsed = $storage_info_value['hrStorageUsed'] < 0 ? $storage_info_value['hrStorageUsed']  + 4294967296 : $storage_info_value['hrStorageUsed'];

	$hrStorageSize_output	= round( (($hrStorageSize * $storage_info_value['hrStorageUnit']) / 1024 / 1024 / 1024), 2); //byte to gb
	$hrStorageUsed_output	= round( (($hrStorageUsed * $storage_info_value['hrStorageUnit']) / 1024 / 1024 / 1024), 2);


	//---------------------------------------------------------------------------
	//-------------------------- Check Linux Partitions -------------------------

	if(strstr($sysDescr[0], "Linux"))
	{
		//show only harddisks
		if(!strstr($hrStorageType_output, "hrStorageFixedDisk") && !strstr($hrStorageType_output, "hrStorageNetworkDisk"))
			$output_storage = false;

		//don't show excluded partitions
		if(is_array($snmp_exclude) && in_array($hrStorageDesc_output, $snmp_exclude))
			$output_storage = false;

		//if current disk meets the criteria
		if($output_storage == true)
		{
			if($hrStorageSize_output > 0)
			{
				$percentage_used	= round( (100 / $hrStorageSize_output) * $hrStorageUsed_output, 1);

				$used_gb_warning	= round( ($hrStorageSize_output / 100) * $snmp_warning, 0);
				$used_gb_critical	= round( ($hrStorageSize_output / 100) * $snmp_critical, 0);
	
				if($percentage_used > $snmp_critical)
				{
					$output_string .= "Critical - ";	
					$exit_code	= 2;
					$output_string_head_error .= $hrStorageDesc_output." (".$hrStorageUsed_output." GB of ".$hrStorageSize_output." GB), ";
				}
				else if($percentage_used > $snmp_warning)
				{
					$output_string .= "Warning - ";	
					if($exit_code != 2)	//if there was already a critical, don't overwrite with a warning
						$exit_code	= 1;

					$output_string_head_error .= $hrStorageDesc_output." (".$hrStorageUsed_output." GB of ".$hrStorageSize_output." GB), ";
				}

			}

			$output_string_head .= $hrStorageDesc_output." (".$percentage_used."%), ";
			$output_string .= $hrStorageDesc_output." - ";
			$output_string .= "Size: ".$hrStorageSize_output. " GB / ";
			$output_string .= "Used: ".$hrStorageUsed_output. " GB (".$percentage_used."% used)";


			$output_string .= "\n";

			if($perfdata == "yes")
			{
				$output_perfdata .= "Disk-".$hrStorageDesc_output."=".$hrStorageUsed_output."GB;".$used_gb_warning.";".$used_gb_critical.";0;".$hrStorageSize_output." ";
			}

		}
		
	}

	//---------------------------------------------------------------------------
	//------------------------- Check Windows Partitions ------------------------

	else if(strstr($sysDescr[0], "Windows"))
	{
		
		//show only harddisks
		if(!strstr($hrStorageType_output, "hrStorageFixedDisk") && !strstr($hrStorageType_output, "hrStorageNetworkDisk"))
			$output_storage = false;

		//if not <name>:\, don't show it
		if(!strstr($hrStorageDesc_output,":\\"))
			$output_storage = false;

		//don't show parition, if size is zero
		if($hrStorageSize_output == 0)
			$output_storage = false;

		//$hrStorageDesc_output could be "C:\ Label:  Serial Number a4d5d99d" so just use the first two chars
		$hrStorageDesc_output	= substr($hrStorageDesc_output, 0, 2);
		
		//don't show excluded partitions
		if(is_array($snmp_exclude) && (in_array($hrStorageDesc_output, $snmp_exclude) || in_array(str_replace(":","",$hrStorageDesc_output), $snmp_exclude)))
			$output_storage = false;

		//if current disk meets the criteria
		if($output_storage == true)
		{
			if($hrStorageSize_output > 0)
			{
				$percentage_used	= round( (100 / $hrStorageSize_output) * $hrStorageUsed_output, 1);

				$used_gb_warning	= round( ($hrStorageSize_output / 100) * $snmp_warning, 0);
				$used_gb_critical	= round( ($hrStorageSize_output / 100) * $snmp_critical, 0);

				if($percentage_used > $snmp_critical)
				{
					$output_string .= "Critical - ";
					$exit_code	= 2;
					$output_string_head_error .= $hrStorageDesc_output." (".$hrStorageUsed_output." GB of ".$hrStorageSize_output." GB), ";
				}
				else if($percentage_used > $snmp_warning)
				{
					$output_string .= "Warning - ";
					if($exit_code != 2)	//if there was already a critical, don't overwrite with a warning
						$exit_code	= 1;

					$output_string_head_error .= $hrStorageDesc_output." (".$hrStorageUsed_output." GB of ".$hrStorageSize_output." GB), ";
				}

			

				$output_string_head .= $hrStorageDesc_output." (".$percentage_used."%), ";
				$output_string 		.= $hrStorageDesc_output." - ";
				$output_string 		.= "Size: ".$hrStorageSize_output. " GB / ";
				$output_string 		.= "Used: ".$hrStorageUsed_output. " GB (".$percentage_used."% used)";


				$output_string .= "\n";

				if($perfdata == "yes")
				{
					$output_perfdata .= "Disk-".$hrStorageDesc_output."=".$hrStorageUsed_output."GB;".$used_gb_warning.";".$used_gb_critical.";0;".$hrStorageSize_output." ";
				}
			}
		}
	}
	

$i++;
}




//---------------------------------------------------------------------------
//----------------------------- Output to stdout ----------------------------

//Output status information
if(!empty($output_string_head_error))
	$output_string_head_error = substr($output_string_head_error, 0, -2);

echo "Disks ";

if($exit_code == 0)
	echo "OK";
else if($exit_code == 1)
	echo "Warning {$output_string_head_error}";
else if($exit_code == 2)
	echo "Critical {$output_string_head_error}";

echo " // ".substr($output_string_head, 0, -2);


//Output excluded disks if argument "E" was given
if(!empty($output_snmp_exclude))
	echo " Excluded: ".$output_snmp_exclude;

//Output extended host information (for Nagios version 3 or higher)
echo "\n".$output_string;

//Output performance data if argument "P" was given
if($perfdata == "yes")
	echo "|".$output_perfdata;

//exit with specific code for ok, warning or critical
exit($exit_code);


?>