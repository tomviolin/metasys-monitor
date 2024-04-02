
School of Freshwater Sciences Metasys Monitor


Requires MySQL/MariaDB running on Synology box, database name "metasys"
Backups can be found at:sfsfiles01:/volume1/DatabaseBackups/mysql_backup/
in "metasys" database.

# The basics.

## Based on the BACNet stack.

BACnet is an open-source protocol for communicating with devices, typically used in commercial/industrial settings
to monitor and control facilities systems, such as HVAC.  

Among other things, SFS uses it to monitor all fish life support system.

This program was created to present a customized dashboard of a subset of devices.  Multiple dashboards may be configured.

### Basic Info on BACnet

The repo for the most current (as of April 2, 2024) version of BACnet is here:

`https://sourceforge.net/projects/bacnet/`

There is a repo on GitHub but it's horribly out of date.





sudo nmap -sU -p 47808 10.7.75.0/24 --open
