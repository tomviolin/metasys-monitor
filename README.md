
# School of Freshwater Sciences Metasys Monitor

Requires:
* MySQL/MariaDB server
* Apache or other web server that can do PHP.
* BACnet stack (almost always vital)
  

## Based on the BACNet stack.

BACnet is an open-source protocol for communicating with devices, typically used in commercial/industrial settings
to monitor and control facilities systems, such as HVAC.  

Among other things, SFS uses it to monitor all fish life support system.

This program was created to present a customized dashboard of a subset of devices.  Multiple dashboards may be configured.

### Basic Info on BACnet

#### Downloading 
The repo for the most current (as of April 2, 2024) version of BACnet for Linux is here:

https://sourceforge.net/projects/bacnet/

While this program doesn't directly call anything in that repo, the BACnet tools are valuable when you need to look for things.

#### building 
Once you have the recommended download file, untar it into a convenient directory and simply use `make` to compile everything.  There are dozens of small executables built, each of which is a command-line utility
that does one specific thing.  Given that, for us, we only use the BACnet stack for this one application, we just left the executables in the `bin/` directory and used them from there. Depending on your workflow
and security arrangements, you may have differentr needs.

### Using the BACnet stack to discover your devices and endpoints
Depending on the quality and quantity of information at your disposal -- ours was deficient in both -- you may need to put in some effort finding where everything is on the network.

If you have a list of all the IP adresses for all the internet-connected BACnet hosts you will need to access, and you are certain of the veracity of this information, you may skip to the next section.

If your information is lacking, or you are curious or just want to verify that your information is correct, then read on.


#### Discovery of the IP addresses of BACnet hosts.
To scan an IP or subnet for the presence of potential BACnet hosts, use the `nmap` utility as follows:
```bash
sudo nmap -sU -p 47808 aa.bb.cc.0/24 --open
```
where you substitute your particular IP numbers and mask for the `aa.bb.cc`, etc.  The use of `.0/24` is probably the most common, but if you don't know the configuration of the subnet to which your BACnet hosts connect, please contact your network administrators to obtain that informatoion.

The results of this scan are not entirely reliable because BACnet operates over UDP, for which there is no concept of a connection nor is there guarateed delivery of packets or packet ordering. It is used because it is much faster and  burdens the network much less than the equivalent data over TCP, however it makes reliable detection of a UDP server harder. So there will be potentially many false positives.  However it does cut down on the number of hosts for the next step.

#### Probing an individual host

If you are in the `bin/` directory of the BACnet stack, the command for each host is of the form:

```bash
./bacwi -1 --mac 10.11.12.13:47808
````

#### Special side note 

For whatever reason, in the parlance of BACnet, a "MAC address" is completely different than in the rest of the known universe.  In BACnet,
a MAC address of `AA:BB:CC:DD:EE:FF` actually means "IP address `AA.BB.CC.DD` at port `EEFF`.  (you need to convert from hex to decimal of course)

#### getting the GOODS

If the output of the above command looks like this, we're on the right track:

```lst
;Device   MAC (hex)            SNET  SADR (hex)           APDU
;-------- -------------------- ----- -------------------- ----
  597     0A:07:4A:0B:BA:C0    0     00                   1024 
;
; Total Devices: 1
```
If it says `Total Devices: 0` at the end, you've found a false positive, so just go on to the next potential BACnet host.  *NOTE:* 
please note that the output above does not explicitly show the IP address (unless you're really good at hex-to-decimal conversions
in your head, which isn't me) so you need to be careful to keep them straight. (and yes, I didn't the first time)

#### Ok now what?

So now you can use the `bacepics` program to find devices behind each host. In our case we had a half-dozen or so hosts,
so I wrote a script to run it:

```bash
#!/bin/bash
export BACNET_APDU_RETRIES=10
export BACNET_APDU_TIMEOUT=9999
export BACNET_BBMD_PORT=47808

BACNET_BBMD_ADDRESS={ip address of host}  ./bin/bacepics {Device} -t {MAC} > epics{Device}.txt
BACNET_BBMD_ADDRESS={ip address of host}  ./bin/bacepics {Device} -t {MAC} > epics{Device}.txt
```
You have to manually replace the `{curly braced }` terms with your local values, of course.

#### Ok, now we're epic! well, EPICS.

There is a program that converts from the EPICS file format to a CSV convenient for importing into the MySQL Database.  However, I'm still working on it. I'll update this as soon as I get it working (again).

