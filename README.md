# Arduino Based Govee-H5054-Leak-Detector
<a href="https://github.com/wallacebrf/Govee-H5054-Leak-Detector/releases"><img src="https://img.shields.io/github/v/release/wallacebrf/Govee-H5054-Leak-Detector.svg"></a>
<a href="https://hits.seeyoufarm.com"><img src="https://hits.seeyoufarm.com/api/count/incr/badge.svg?url=https%3A%2F%2Fgithub.com%2Fwallacebrf%2FGovee-H5054-Leak-Detector&count_bg=%2379C83D&title_bg=%23555555&icon=&icon_color=%23E7E7E7&title=hits&edge_flat=false"/></a>

<div id="top"></div>
<!--
*** comments....
-->



<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/wallacebrf/Govee-H5054-Leak-Detector">
    <img src="https://raw.githubusercontent.com/wallacebrf/Govee-H5054-Leak-Detector/main/Drawing1.png" alt="Logo" width="80" height="180">
  </a>

<h3 align="center">Arduino Based Govee-H5054-Leak-Detector Base Station + Email Notifications</h3>

  <p align="center">
    This project allows the use of Govee h5054 water leak sensors without the need to use the Govee WIFI base station. 
    The base station requires the use of Govee cloud servers to process email notifications. This project allows all of the notifications and configuration to be handled entirely onsite utilizing a locally hosted PHP web server
    <br />
    <a href="https://github.com/wallacebrf/Govee-H5054-Leak-Detector"><strong>Explore the docs »</strong></a>
    <br />
    <br />
    <a href="https://github.com/wallacebrf/Govee-H5054-Leak-Detector/issues">Report Bug</a>
    ·
    <a href="https://github.com/wallacebrf/Govee-H5054-Leak-Detector/issues">Request Feature</a>
  </p>
</div>



<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#Govee-Leak-Detection-Transmission-Details">About The Project</a>
      <ul>
        <li><a href="#built-with">Built With</a></li>
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#roadmap">Road map</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#contact">Contact</a></li>
    <li><a href="#acknowledgments">Acknowledgments</a></li>
  </ol>
</details>



<!-- ABOUT THE PROJECT -->
### Govee-Leak-Detection-Transmission-Details

 The data sent from the units always starts with a single high bit between 50 and 599 micro seconds followed by a very long low duration of between 8 and 9.5ms (8000 and 9500 micro seconds)
 
 when the transmission is complete, a long low duration of between 900 and 1400 micro seconds is seen
 
 after the 8000 to 9500 micro seconds long LOW start bit, data is encoded into tri-bits. the leak sensors send 6x total bytes of data. each byte of data is encoded into 32 ones and zeros from the receiver module for a total of 96 bits of received data
 

 ## Tri-bit #1

<img src="https://raw.githubusercontent.com/wallacebrf/Govee-H5054-Leak-Detector/main/1313.png" alt="1313" width="252" height="38">  --> one high bit, three low bits, one high bit, and three low bits (each high is 50 and 599 micro seconds in length and the three low bits are between 600 and 1200 micro seconds) 
 
 this encodes to a binary value of "00" or a tribit value of "0"
 
## tribit #2
<img src="https://raw.githubusercontent.com/wallacebrf/Govee-H5054-Leak-Detector/main/3131.png" alt="3131" width="252" height="38">  --> three low bits, one high bit, three low bits, and one high bit (each high is 50 and 599 micro seconds in length and the three low bits are between 600 and 1200 micro seconds) 
 
 this encodes to a binary value of "11" or a tribit value of "1"
 
 ## tribit #3

<img src="https://raw.githubusercontent.com/wallacebrf/Govee-H5054-Leak-Detector/main/1331.png" alt="1331" width="252" height="38">  --> one high bit, two consecutive sets of 3 low bits, and one high bit (each high is 50 and 599 micro seconds in length and the three low bits are between 600 and 1200 micro seconds) 
 
 this encodes to a binary value of "01" or a tribit value of "F"
 
## tribit #4

<img src="https://raw.githubusercontent.com/wallacebrf/Govee-H5054-Leak-Detector/main/3113.png" alt="3113" width="252" height="38">  --> three low bit, two consecutive high bits, and three low bis (each high is 50 and 599 micro seconds in length and the three low bits are between 600 and 1200 micro seconds) 
 
 this encodes to a binary value of "10" or a tribit value of "X"
 

## Byte breakdown
 Credit for the byte breakdown explanation, and CRC check code goes to user "billgrundmann" at this thread
 https://github.com/merbanan/rtl_433/issues/1518
 
### {byte1, byte2} = unit number of the sensor; unit = (byte1 << 8) + byte2;

###   byte3 = type of message or packet
   0xfa - registration request (aka the button was pushed)
   0xfb - water leak alarm
   0xfc - voltage in {byte4} as follows
   0xfd - heartbeat, or still alive and reporting in; this seems to happen on a regular basis during the day
   a timeout for a unit's heartbeat would indicate something wrong on the sensor

###   byte4 = if (byte3 == 0xfc), then voltage of battery, otherwise ignored
   fit value from 8 different sensor units: voltage = 1.7944 + 0.0121 {byte4}
   range = observed 2 to 3.1 volts

###   byte5 - ignored, what it does is unknown

###   byte 6 - parity
    
   note: parity calculation updated to use verification found here RATHER than the code in "billgrundmann" post above
   https://github.com/merbanan/rtl_433/pull/1810/files#diff-a7909b4d30881faf8a9d0f5572de0ce8408b0a7440522a6ac9afb84c2359ae25R47

   Last byte contains the parity bits in index 2-6 (101PPPP1).
   The parity checksum using CRC8 against the first 2 bytes (the ID)

   credit goes to https://www.reddit.com/user/Full_screen for pointing out to me that my code used the old parity calculation method and for showing me where i can find the latest calculation code
   
   credit goes to https://github.com/YiannisBourkelis/Uptime-Library for the uptime calculations library
 


<p align="right">(<a href="#top">back to top</a>)</p>



### Built With

* [Arduino C](https://www.arduino.cc/)
* [PHP](https://www.php.net/)

<p align="right">(<a href="#top">back to top</a>)</p>



<!-- GETTING STARTED -->
## Getting Started

This project requires the use of an arduino Uno or equivalent. Current Code is written around an arduino Mega. 
This project requires the use of an arduino Ethernet shield
This project requires a 433MHz RF receiver and proper antenna

### Prerequisites

The Arduino IDE contains all required libraries needed for this project. 

The PHP server must have the following library installed for sending emails
https://github.com/PHPMailer/PHPMailer

the arduino library https://github.com/YiannisBourkelis/Uptime-Library is required for the uptime calculations. the library (version 1.0.0 as of 2/8/2022) is included in this repository. 

This system supports logging data to InfluxDB version 2.0 and higher. If logging is desired, InfluxDB must be installed and properly configured. Create a database / bucket as desired, take note of the API access key as these will be needed for later configuration. Logging to InfluxDB is disabled by default. 

to install the PHPMailer library, PHP composer is required

### Installation

### -> Arduino

1. download the `water_leak_detectorV9.ino` file, the `uptime.cpp` file, and the `uptime.h` file and using the Arduino IDE open the `water_leak_detectorV9.ino` file for editing.
2. edit the following lines as desired

-->```const byte mac[] = {0x00, 0xAA, 0xBB, 0xCC, 0xDE, 0x06 }; //MAC address assigned to the Ethernet Shield```

-->```const byte localip[] = {192, 168, 1, 49}; //Static IP assigned to the leak detector Ethernet Shield```

-->```const byte serverip[] = {192, 168, 1, 13}; //IP of the server running the required PHP receiving code```

-->```const byte interruptPin = 18; //pin 18 is available on a Mega2560, use pin 2 or 3 if using an Uno```

-->```byte debug=0; //set to 1 to enable serial output of all verbose data received.```

3. Upload to the Arduino

note: if the following message is displayed by the compiler
```
In file included from C:\Program Files (x86)\Arduino\libraries\Ethernet\src\Dns.cpp:8:0:

C:\Program Files (x86)\Arduino\libraries\Ethernet\src\Dns.cpp: In member function ‘uint16_t DNSClient::BuildRequest(const char*)’:

C:\Program Files (x86)\Arduino\libraries\Ethernet\src\utility/w5100.h:457:25: warning: result of ‘(256 << 8)’ requires 18 bits to represent, but ‘int’ only has 16 bits [-Wshift-overflow=]

#define htons(x) ( (((x)<<8)&0xFF00) | (((x)>>8)&0xFF) )
```
then correct the w5100.h file in `\libraries\Ethernet\src\utility`:

at the end of the file you have

`#define htons(x) ( (((x)<<8)&0xFF00) | (((x)>>8)&0xFF) )`

comment out that line and add under it

`#define htons(x) ( ((((x)&0xFF)<<8)&0xFF00) | (((x)>>8)&0xFF) )`

This should give you the same result without the warning.

4. with the serial monitor window set to a baud rate of 230400, ensure the "booting" message is received. 

### -> PHP Web server

1. Create a dedicated directory of your choosing within the web server directory for the scripts to save the individual configuration files used for each configured sensor. ensure no other files or folders are located within this directory. Keep note of this directory as it will need to be entered into the web-page configuration settings later on.

2. Create a directory in the base directory of the web server called `admin`. PHPMailer will be installed here as well as two files from this repository. 

3. download the latest version of PHPMailer from https://github.com/PHPMailer/PHPMailer/releases and extract the contents into the `admin` folder that was just created

4. Ensure "phar" is enabled on the web server's PHP configuration

5. SSH your NAS, navigate to the `admin` directory, and issue the install commands as outlined here: https://getcomposer.org/download/

after successful installation you should see
```
All settings correct for using Composer
Downloading...

Composer (version xxx) successfully installed to: xxxx
Use it: php composer.phar
```

6. While still using SSH in the `admin` directory issue the install command per https://github.com/PHPMailer/PHPMailer/blob/master/README.md

```
php composer.phar require phpmailer/phpmailer
```

If all goes well, PHPMailer will now be installed into the `admin` directory

7. Copy `water_leak_config.php` file, the `water_leak_detector.php` file, the `red.png` file, and `green.png` to the `admin` directory. Copy the `functions.php` file to the base directory of the web server. 

8. within the `/admin/water_leak_config.php` file edit the following lines as desired

-->`$leak_detector_config_file="/volume1/web/config/config_files/config_files_local/leak_detector_config.txt";` this is where the overall system configuration data will be stored 
Ensure this value matches what is used in the `water_leak_detector.php` file. 

-->`$use_login_sessions=true; //set to false if not using user login sessions` should the PHP script enforce PHP sessions?

-->`$form_submittal_destination="index.php?page=1"; //set to the destination the HTML form submission should be directed to` what PHP page should the submitted form data be sent to*

*note: the `water_leak_config` file has no formatting. If formatting is desired, it is recommended to call the config file within another PHP file using the include_once command, or edit the config file directly with header and footer details as desired. 

9. Within the `/admin/water_leak_detector.php` file edit the following lines as desired

-->`$debug=0; //if set to 1, it will print out a lot more detail when the page is loaded`

-->`$leak_detector_config_file="/volume1/web/config/config_files/config_files_local/leak_detector_config.txt";` this is where the overall system configuration data will be stored. Ensure this value matches what is used in the `water_leak_config.php` file. 

10. Once all of the files are installed correctly, load the `water_leak_config.php` in a browser. 

upon initial loading, the script will create a configuration file in the location `$leak_detector_config_file` defined in the `water_leak_config.php` file. The configuration file will be loaded with default values that must be changed for the system to operate. 
<img src="https://raw.githubusercontent.com/wallacebrf/Govee-H5054-Leak-Detector/main/1-%20initial%20page%20load.png" alt="config">

after refreshing the page, configure all of the settings as desired. ensure the `Sensor Config File Directory` field is configured with the web server directory created previously. This will be where all of the individual leak sensor configuration files will be stored. 
<img src="https://raw.githubusercontent.com/wallacebrf/Govee-H5054-Leak-Detector/main/2-%20page%20with%20default%20setting.png" alt="config">

After all settings are configured correctly, proceed to the <a href="#usage">Usage</a> section to begin configuring sensors.

<p align="right">(<a href="#top">back to top</a>)</p>
### -> Arduino + RF Receiver wiring
The receiver (depending on the model used) will need at minimum +5V, GND, and data line connections. The data line from the transmitter must be connected to the same pin as defined by the variable `interruptPin` within the arduino code. The pin must be a hardware interrupt pin. Details on the interrupt pins for different arduino models can be found here: https://www.arduino.cc/reference/en/language/functions/external-interrupts/attachinterrupt/

<!-- USAGE EXAMPLES -->
## Usage

Once the hardware and software are working leak sensors can be added to the system. 

To add a new new sensor, press the "test" button on one sensor. press it 2-3 times while waiting at least 10 seconds between presses. If everything works as expected, three new emails titled `UNKNOWN SENSOR DETECTED` should be received at the configured email address. The contents of the emails will indicate:

`current_date -- A New Leak Sensor Been Detected`<br>
`The Sensor ID is XX`

verify that all three emails have the same ID number. 

On the locally hosted PHP web server navigate to the `water_leak_config.php` page and click on the "ADD NEW SENSOR" link at the top. On the sensor add page, enter the two character ID number received in the test emails. Give the sensor a name and location details and add the sensor. If the sensor was added correctly the page will display `SENSOR CODE "XX" ADDED SUCESSFULLY`. repeat the steps for all remaining sensors. 

<img src="https://raw.githubusercontent.com/wallacebrf/Govee-H5054-Leak-Detector/main/2022_01_10_12_59_34_Water_Leak_Config.png" alt="config">

<img src="https://raw.githubusercontent.com/wallacebrf/Govee-H5054-Leak-Detector/main/2022_01_10_12_59_34_Water_Leak_Config2.png" alt="add">

Once all sensors are installed, the battery, test button status, leak status, and heartbeat status can be seen per sensor. 

At the beginning battery status will be "N/A" until a battery status is received from the sensor. 

The "Button" indicator will be red as no button test has been detected while the sensor has been configured. 

The "leak" indicator will indicate green as no leaks have yet to be detected. 

The "HeartBeat" indicator will be red until a heartbeat has been detected from the sensor. Note on the heartbeat, older models of the sensors sent heartbeat signals to the base station on regular intervals. However later models stopped sending this signal. To disable the heartbeat monitoring, clicking the `Clear Active Leak" checkbox and submitting the form for the particular sensor. 


Now that the sensors are configured, press the "test" button on each of the now configured sensors. An email should be received titled `sensor_physical_name Button Press` with the following message:

`current_date -- The "sensor_physical_name" Leak Sensor Test Button Has Been Pressed.`
`This Confirms The Sensor Is Functional`

Within the PHP configuration page, the "Button" indicator will turn green. 

If a sensor detects a leak, the "Leak" indicator will turn red and an email will be sent titled `sensor_physical_name LEAK DETECTED` with the following message:

`current_date -- The "sensor_physical_name" Leak Sensor has detected A Leak.`
`Check the sensor_location as soon as possible`

while the sensor is detecting a leak, a new email will be sent every 60 minutes until the sensor contacts are dry or the battery dies. 

After a leak, to clear the leak on the PHP configuration page click the "Clear Active Leak" box and submit the form for that sensor. 

<p align="right">(<a href="#top">back to top</a>)</p>



<!-- CONTRIBUTING -->
## Contributing

<p align="right">(<a href="#top">back to top</a>)</p>



<!-- LICENSE -->
## License

This is free to use code, use as you wish

<p align="right">(<a href="#top">back to top</a>)</p>



<!-- CONTACT -->
## Contact

Your Name - Brian Wallace - wallacebrf@hotmail.com

Project Link: [https://github.com/wallacebrf/Govee-H5054-Leak-Detector)

<p align="right">(<a href="#top">back to top</a>)</p>



<!-- ACKNOWLEDGMENTS -->
## Acknowledgments

* billgrundmann" post at https://github.com/merbanan/rtl_433/issues/1518
* https://www.reddit.com/user/Full_screen for pointing out older code used older version of parity calculations
* parity calculation code from https://github.com/merbanan/rtl_433/pull/1810/files#diff-a7909b4d30881faf8a9d0f5572de0ce8408b0a7440522a6ac9afb84c2359ae25R47
* credit goes to https://github.com/YiannisBourkelis/Uptime-Library for the up time calculations library
* credit goes to user "ToneArt" at post: https://forum.arduino.cc/t/compiler-message-need-interpretation/620468/7 for correcting the compiler warning

<p align="right">(<a href="#top">back to top</a>)</p>
