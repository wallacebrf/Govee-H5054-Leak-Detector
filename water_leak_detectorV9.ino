/*
 * By Brian Wallace
 * After connecting the output of a receiver module to a scope, i decoded the bit waveforms myself:
 * 
 * the data sent from the units always starts with a single high bit between 50 and 599 micro seconds followed by a very long low duration of between 8 and 9.5ms (8000 and 9500 micro seconds)
 * 
 * when the transmission is complete, a long low duration of between 900 and 1400 micro seconds is seen
 * 
 * after the 8000 to 9500 micro seconds long LOW start bit, data is encoded into tri-bits. the leak sensors send 6x total bytes of data. each byte of data is encoded into 32 ones and zeros from the receiver module for a total of 96 bits of received data
 * 
 * ***********************************
 * ***********************************
 * tribit #1
 *  _     _
 * | |___| |___  --> one high bit, three low bits, one high bit, and three low bits (each high is 50 and 599 micro seconds in length and the three low bits are between 600 and 1200 micro seconds) 
 * 
 * this encodes to a binary value of "00" or a tribit value of "0"
 * 
 * ***********************************
 * ***********************************
 * tribit #2
 *     _     _
 * ___| |___| |  --> three low bits, one high bit, three low bits, and one high bit (each high is 50 and 599 micro seconds in length and the three low bits are between 600 and 1200 micro seconds) 
 * 
 * this encodes to a binary value of "11" or a tribit value of "1"
 * 
 * ***********************************
 * ***********************************
 * tribit #3
 *  _        _
 * | |______| |  --> one high bit, two consecutive sets of 3 low bits, and one high bit (each high is 50 and 599 micro seconds in length and the three low bits are between 600 and 1200 micro seconds) 
 * 
 * this encodes to a binary value of "01" or a tribit value of "F"
 * 
 * ***********************************
 * ***********************************
 * tribit #4
 *     __
 * ___|  |___  --> three low bit, two consecutive high bits, and three low bis (each high is 50 and 599 micro seconds in length and the three low bits are between 600 and 1200 micro seconds) 
 * 
 * this encodes to a binary value of "10" or a tribit value of "X"
 * 
 * ***********************************
 * ***********************************
 * Byte breakdown
 * Credit for the byte breakdown explanation, and CRC check code goes to user "billgrundmann" at this thread
 * https://github.com/merbanan/rtl_433/issues/1518
 * 
 * {byte1, byte2} = unit number of the sensor; unit = (byte1 << 8) + byte2;

   byte3 = type of message or packet
   0xfa - registration request (aka the button was pushed)
   0xfb - water leak alarm
   0xfc - voltage in {byte4} as follows
   0xfd - heartbeat, or still alive and reporting in; this seems to happen on a regular basis during the day
   a timeout for a unit's heartbeat would indicate something wrong on the sensor

   byte4 = if (byte3 == 0xfc), then voltage of battery, otherwise ignored
   fit value from 8 different sensor units: voltage = 1.7944 + 0.0121 * {byte4}
   range = observed 2 to 3.1 volts

   byte5 - ignored, what it does is unknown

   byte 6 - parity
    
   note: parity calculation updated to use verifications found here RATHER than the code in "billgrundmann" post above
   https://github.com/merbanan/rtl_433/pull/1810/files#diff-a7909b4d30881faf8a9d0f5572de0ce8408b0a7440522a6ac9afb84c2359ae25R47

   Last byte contains the parity bits in index 2-6 (101PPPP1).
   The parity checksum using CRC8 against the first 2 bytes (the ID)

   credit goes to https://www.reddit.com/user/Full_screen for pointing out to me that my code used the old parity calculation method and for showing me where i can find the latest calculaton code
 * credit goes to https://github.com/YiannisBourkelis/Uptime-Library for the uptime calculations library
 * 
 */

#include <avr/io.h>
#include <avr/pgmspace.h>
#include <avr/wdt.h>
#include "uptime.h"
//#include <Ethernet2.h>//needed for 5500 chip-set
#include <Ethernet.h>//needed for 5100 chip-set
byte mac[] = {0x00, 0xAA, 0xBB, 0xCC, 0xDE, 0x06 }; //MAC address assigned to the Ethernet Shield
byte localip[] = {192, 168, 1, 49}; //Static IP assigned to the leak detector Ethernet Shield
const byte serverip[] = {192, 168, 1, 13}; //IP of the server running the required PHP receiving code
EthernetClient client;


const byte interruptPin = 18; //pin 18 is available on a Mega2560, use pin 2 or 3 if using an Uno
byte debug=0; //set to 1 to enable serial output of all verbose data received. 
volatile unsigned int low_start = 0;
volatile unsigned int high_start = 0;
volatile bool mess_start=false;
volatile bool mess_end=false;
volatile unsigned int counter_high; 
volatile unsigned int counter_low; 
volatile byte val=LOW;
unsigned int bit_number=0; 
byte tribit_array[192];
volatile byte interrupt_counter=0;
unsigned int interrupt_array[192];
char binary_data[96];
byte byte1=0;
byte byte2=0;
byte byte3=0;
byte byte4=0;
byte byte5=0;
byte byte6=0;
unsigned long currentMillis = 0;
unsigned long previousMillis_leak = 0; 
unsigned long previousMillis_button = 0; 
unsigned long previousMillis_battery = 0; 
unsigned long previousMillis_heart = 0; 
const long interval = 10000; // time delay brtween when button press, leak detect, or battery status is received and when the data is re-sent to the server. this helps prevent flooding the server with data as the sensor sends data multiple times to ensure proper transmission
unsigned long message_start_Millis = 0;
volatile unsigned long message_start_Millis2 = 0;
unsigned long free_ram_Millis = 0;
byte leak_counter=0;
byte leak_ID[24];
byte button_conter=0;
byte button_ID[24];
byte b[8];
byte x_number=0;
byte tribit_ending_bit=0;
byte counter=0;
byte byte_ending_bit=0;
byte unit_number=0;
int battery_status=0;
byte parity_received=0;
byte parity_calculation=0;

void setup() {
  Serial.begin(230400);
  pinMode(interruptPin, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(interruptPin), blink, CHANGE);
  Ethernet.begin(mac, localip);
  Serial.println(F("Version 10.7 (02/21/2022) Booting....."));
  wdt_enable(WDTO_4S);
}

void loop() {

  wdt_reset();

    //every 15 minutes print over serial the available system RAM and the system uptime in minutes
    currentMillis = millis();
    if (currentMillis - free_ram_Millis >= 900000) { //every 15 mins print the current RAM availability
     free_ram_Millis = currentMillis;
     Serial.println();
     Serial.print(F("Free RAM: "));
     Serial.print(freeRam());
     Serial.println(F(" Bytes"));
     Serial.print(F("System Uptime: "));
     uptime::calculateUptime();
     Serial.print(uptime::getDays());
     Serial.print(F(" Days | "));
     Serial.print(uptime::getHours());
     Serial.print(" Hours | ");
     Serial.print(uptime::getMinutes());
     Serial.print(" Minutes | ");
     Serial.print(uptime::getSeconds());
     Serial.println(" Seconds");
     Serial.println();
    }

  //reset state machine every 10 seconds. this is needed in the event that random noise detected by the RF receiver has triggered the "blink" ISR and is causing the state machine to go into stalled state
  currentMillis = millis();
  if (currentMillis - message_start_Millis2 >= 10000) {
    message_start_Millis2 = currentMillis;
    low_start = 0;
    high_start = 0;
    mess_start=false;
    mess_end=false;
    counter_high=0; 
    counter_low=0; 
    bit_number=0;
    interrupt_counter=0;
    byte1=0;
    byte2=0;
    byte3=0;
    byte4=0;
    byte5=0;
    byte6=0;
    x_number=0;
    tribit_ending_bit=0;
    counter=0;
    byte_ending_bit=0;
    unit_number=0;
    battery_status=0;
    parity_received=0;
    parity_calculation=0;
    button_conter=0;
    memset(tribit_array, 0, sizeof(tribit_array));
    memset(interrupt_array, 0, sizeof(interrupt_array));
    memset(binary_data, 0, sizeof(binary_data));
    memset(leak_ID, 0, sizeof(leak_ID));
    memset(button_ID, 0, sizeof(button_ID));
    memset(b, 0, sizeof(b));
    //counter_low=0;
   // counter_high=0;
   // bit_number=0;
   // interrupt_counter=0;
   // mess_end=false;
   // mess_start=false;
    //interrupts();
  }
  

    //start message begins with a start bit of one pulse wide followed by a low pulse of between 8000 and 9000 us
    //if the message has not started or ended yet, skip this
    if((mess_start == false)&&(counter_high>100)&&(mess_end==false)&&(bit_number==0)){
      if((counter_low >= 8000)&&(counter_low <= 9000)){
          mess_start=true;
          message_start_Millis=millis();
          if(debug==1){
            Serial.print(F("start "));
          }
          //reset all of the variables for the new round of data receiving 
          counter_low=0;
          counter_high=0;
          bit_number=0;
          interrupt_counter=0;
          byte1=0;
          byte2=0;
          byte3=0;
          byte4=0;
          byte5=0;
          byte6=0;
        }
    }

    //message has started, begin receiving data
    if((mess_start == true)&&(mess_end==false)){

      //check to see if the system has been waiting for over 1 second to receive the end of the message. if it is over 1 second, something went wrong and reset 
     // currentMillis = millis();
    //  if (currentMillis - message_start_Millis >= 1000) {
    //    Serial.println(F("1 second reset"));
    //    message_start_Millis = currentMillis;
    //    counter_low=0;
    //    counter_high=0;
    //    bit_number=0;
    //    interrupt_counter=0;
    //    mess_end=false;
    //    mess_start=false;
        //interrupts();
    //  }

      //check if the stop message has been received yet. have we received the required number of bits (96 bits) and did we receive the correct duration end bit
      if((counter_low >= 1350)&&(counter_low <= 1500)&&(interrupt_counter>95)){
          //noInterrupts();//turn off interrupts while we process the data and send it to the server
          mess_end=true;
          if(debug==1){
            Serial.print(F("end "));
          }
          counter_low=0;
          counter_high=0;
          low_start=0;
          high_start=0;
          interrupt_counter=0; 
      }
     }

  //have we received all of the data? if so, let's process the 96 received bit duration to decode the data
  if((mess_start == true)&&(mess_end == true)){

      //process the 96 received bits by how long each one remains high, they are either high for one pulse length, or three consecutive pulse lengths. 
      x_number=0;//incremented between 0 and 3 to count which of the four transmission bits are part of the tri-bit being processed
      if(debug==1){
        Serial.println(F("raw data (pulse duration in microseconds): "));
      }
      for(byte x=0;x<96;x++){
         if(x<95){
            if((interrupt_array[x] >= 600)&&(interrupt_array[x] <= 1200)){ //the duration is long, so this only contain three pulses
              tribit_array[x]=3;
              bit_number++;
            }else if((interrupt_array[x] >= 50)&&(interrupt_array[x] <= 599)){ //the duration is short, so this only contains one pulse
              tribit_array[x]=1;
              bit_number++;
            }
           //if debug is enabled, print out all of the microsecond time durations 
           if(debug==1){
              if((x_number==0)){//first of four bits in the current tri-bit being processed
                Serial.print(interrupt_array[x]);
                Serial.print(F(" | "));
                x_number++;
              }else if((x_number==1)){//second of four bits in the current tri-bit being processed
                Serial.print(interrupt_array[x]);
                Serial.print(F(" | "));
                x_number++;
              }else if((x_number==2)){//third of four bits in the current tri-bit being processed
                Serial.print(interrupt_array[x]);
                Serial.print(F(" | "));
                x_number++;
              }else if((x_number==3)){//fourth of four bits in the current tri-bit being processed
                Serial.print(interrupt_array[x]);
                Serial.println(F(" || "));
                x_number=0;
              }
           }
         }else{
          tribit_array[x]=1;
         }
      }
      
      if(debug==1){
       //print tri-bits duration in number of pulses
        x_number=1;
        Serial.println();
        Serial.print(F("Tribit Data: "));
        for(byte x=0;x<96;x++){
           if((x_number==1)){
            Serial.print(tribit_array[x]);
            x_number++;
           }else if((x_number==2)){
            Serial.print(tribit_array[x]);
            x_number++;
           }else if((x_number==3)){
            Serial.print(tribit_array[x]);
            x_number++;
           }else if((x_number==4)){
            Serial.print(tribit_array[x]);
            Serial.print(F(" || "));
            x_number=1;
           }
        }
      }

      ///convert the tri-bits into their binary equivalents as discussed in the comments at the beginning of this code. 
    //the variable "binary_data" is a char array as the arduino does not support 48 bit numbers. as such we will store the data as a char array now, and convert the contents into individual bytes later
      tribit_ending_bit=0;
      counter=0;
      for(byte x=1;x<25;x++){ //there are a total of 24 received tribits, four tribits per byte, and there are 6 bytes of total data we are after
         tribit_ending_bit=x*4;
         if((tribit_array[tribit_ending_bit-4]==1)&&(tribit_array[tribit_ending_bit-3]==3)&&(tribit_array[tribit_ending_bit-2]==1)&&(tribit_array[tribit_ending_bit-1]==3)){
          //tribit equaled 1313
          //this equates to a binary value of "00"
          binary_data[counter]=0;
          counter++;
          binary_data[counter]=0;
          counter++;
         }else if((tribit_array[tribit_ending_bit-4]==3)&&(tribit_array[tribit_ending_bit-3]==1)&&(tribit_array[tribit_ending_bit-2]==3)&&(tribit_array[tribit_ending_bit-1]==1)){
          //tribit equaled 3131
          //this equates to a binary value of "11"
          binary_data[counter]=1;
          counter++;
          binary_data[counter]=1;
          counter++;
         }else if((tribit_array[tribit_ending_bit-4]==1)&&(tribit_array[tribit_ending_bit-3]==3)&&(tribit_array[tribit_ending_bit-2]==3)&&(tribit_array[tribit_ending_bit-1]==1)){
          //tribit equaled 1331
          //this equates to a binary value of "01"
          binary_data[counter]=0;
          counter++;
          binary_data[counter]=1;
          counter++;
         }else if((tribit_array[tribit_ending_bit-4]==3)&&(tribit_array[tribit_ending_bit-3]==1)&&(tribit_array[tribit_ending_bit-2]==1)&&(tribit_array[tribit_ending_bit-1]==3)){
          //tribit equaled 3113
          //this equates to a binary value of "10"
          binary_data[counter]=1;
          counter++;
          binary_data[counter]=0;
          counter++;
         }
      }
      if(debug==1){
        Serial.println();
        Serial.print(F("Binary Data 0b"));
        Serial.print(binary_data);  //prints sent message in processed binary form
        Serial.print(F("  ")); 
      }


       //we now have the raw binary data (48 bits) in a char array, let's break this into the 6x separate bytes as actual byte designated variables 
       byte_ending_bit=0;
      //go through all six bytes of data
      for(byte x=1;x<7;x++){
         byte_ending_bit=x*8;
         //each byte has 8 bits, so we will process each bit one at a time
         for(byte y=8;y>0;y--){
          if(binary_data[byte_ending_bit-y]==0){ //is the left most (most significant digit) equal to a 1 or 0? save that into the left most (most significant digit) of the first byte
            if(x==1){
              bitWrite(byte1, (y-1), 0);
            }else if(x==2){
              bitWrite(byte2, (y-1), 0);
            }else if(x==3){
              bitWrite(byte3, (y-1), 0);
            }else if(x==4){
              bitWrite(byte4, (y-1), 0);
            }else if(x==5){
              bitWrite(byte5, (y-1), 0);
            }else if(x==6){
              bitWrite(byte6, (y-1), 0);
            }
          }else if(binary_data[byte_ending_bit-y]==1){
            if(x==1){
              bitWrite(byte1, (y-1), 1);
            }else if(x==2){
              bitWrite(byte2, (y-1), 1);
            }else if(x==3){
              bitWrite(byte3, (y-1), 1);
            }else if(x==4){
              bitWrite(byte4, (y-1), 1);
            }else if(x==5){
              bitWrite(byte5, (y-1), 1);
            }else if(x==6){
              bitWrite(byte6, (y-1), 1);
            }
          }
        }
      }

      //print out the 6x received bytes in their 1.) binary form, 2.) decimal form, and 3.) hexadecimal form
      if(debug==1){
        Serial.println();
        Serial.print(F("Byte1 Binary: 0b"));  //prints sent message
        Serial.print(byte1, BIN);
        Serial.print(" Decimal: ");
        Serial.print(byte1, DEC);
        Serial.print(" Hex: ");
        Serial.print(byte1, HEX);
        Serial.print(F(" || Byte2 Binary: 0b"));  //prints sent message
        Serial.print(byte2, BIN);
        Serial.print(" Decimal: ");
        Serial.print(byte2, DEC);
        Serial.print(" Hex: ");
        Serial.print(byte2, HEX);
        Serial.print(F(" || Byte3 Binary: 0b"));  //prints sent message
        Serial.print(byte3, BIN);
        Serial.print(" Decimal: ");
        Serial.print(byte3, DEC);
        Serial.print(" Hex: ");
        Serial.print(byte3, HEX);
        Serial.print(F(" || Byte4 Binary: 0b"));  //prints sent message
        Serial.print(byte4, BIN);
        Serial.print(" Decimal: ");
        Serial.print(byte4, DEC);
        Serial.print(" Hex: ");
        Serial.print(byte4, HEX);
        Serial.print(F(" || Byte5 Binary: 0b"));  //prints sent message
        Serial.print(byte5, BIN);
        Serial.print(" Decimal: ");
        Serial.print(byte5, DEC);
        Serial.print(" Hex: ");
        Serial.print(byte5, HEX);
        Serial.print(F(" || Byte6 Binary: 0b"));  //prints sent message
        Serial.print(byte6, BIN);
        Serial.print(" Decimal: ");
        Serial.print(byte6, DEC);
        Serial.print(" Hex: ");
        Serial.print(byte6, HEX);
      }

      //the first two bytes are the unit ID number, let's calculate the ID number
      unit_number=(byte1 << 8) + byte2;
      if(debug==1){
        Serial.print(F(" Unit# "));
        Serial.print(unit_number, HEX);
      }

      //if byte3 is equal to a HEX value of FC, then we have received a valid battery voltage reading on byte4
      battery_status=0;
      if(byte3==0xfc){
        battery_status=(1.7944 + (0.0121 * byte4))*100;//multiplying the float by 100 and saving into an integer defined variable will give us two decimal places of data as the other decimal points will be dropped. when received by the server, dividing by 100 will give the decimal point back
        if(debug==1){
          Serial.print(F(" Battery Voltage# "));
          Serial.print(battery_status);
        }
      }


      //calculate the parity of the received data. This will eventually be compared to the received parity bits
      parity_received = (byte6 >> 1 & 0x0F); // Shift 101PPPP1 -> 0101PPPP, then and with 0x0F so we're left with 000PPPP

      if(debug==1){
          Serial.println();
          Serial.print(F("Parity Data from Byte6: 0b"));
          Serial.print(parity_received, BIN);
        }

      //load first two bytes into an array to pass to the crc8le() function
      
      b[0]=byte1;
      b[1]=byte2;

      //calculate the CRC value of the first two bytes (the unit ID number data)
      parity_calculation=crc8le(b, 2, 0x10, 0xe0);

      if(debug==1){
          Serial.print(F(" Parity Calculation: 0b"));
          Serial.print(parity_calculation, BIN);
        }

      //confirm calculated parity and received parity data match indicating a valid data transmission was received
      if(parity_calculation == parity_received){
        if(debug==1){
          Serial.println();
        }
        Serial.print(F("Parity Match -> Unit# "));
        Serial.print(unit_number, HEX);

        /////////////////////////////
        //BUTTON PRESS
        /////////////////////////////
        if(byte3==0xfa){
          Serial.println(F(" Button Press Detected "));

          if(button_conter>2){
              button_conter=0;
              memset(button_ID, 0, sizeof(button_ID));
          }
          
          button_ID[button_conter]=unit_number;
          button_conter++;

          if(button_conter==2){ //have we received at least 2x button press messages in a row? the sensor sends the same message 13x times in a burst
            if(button_ID[0]==button_ID[1]){ // were both button presses from the same unit?
              button_conter=0;
              memset(button_ID, 0, sizeof(button_ID));
              //we want to perform a delay as a button press causes the sensor to repeat the same message 13x times. we do not want to send data to the server 13 times, so once we do send data, we will perform a delay so we ignore the other data
              //we cannot use a regular delay(10000); function as we have disabled interrupts. the standard delay function requires them to work. 
              //instead we will use the code from here: https://www.arduino.cc/en/Tutorial/BuiltInExamples/BlinkWithoutDelay
              currentMillis = millis();
              if(previousMillis_button ==0){ //have we never required the delay yet? send the message right away
                previousMillis_button = currentMillis;
                send_to_server(unit_number,1,battery_status);
              }else{ //we have used this delay at least once since booting, so we need to make sure the required delay time has elapsed.
               if (currentMillis - previousMillis_button >= interval) {
                  previousMillis_button = currentMillis;
                  send_to_server(unit_number,1,battery_status);
               }
              }
            }
          }
          

        /////////////////////////////
        //WATER LEAK
        /////////////////////////////
        }else if(byte3==0xfb){
          Serial.println(F(" WARNING WATER LEAK DETECTED "));

          if(leak_counter>2){
              leak_counter=0;
              memset(leak_ID, 0, sizeof(leak_ID));
          }
         
          leak_ID[leak_counter]=unit_number;
          leak_counter++;

          if(leak_counter==2){ //have we received at least 2x leak messages in a row? the sensor sends the same message 13x times in a burst
            if(leak_ID[0]==leak_ID[1]){ // were both messages from the same unit?
              leak_counter=0;
              memset(leak_ID, 0, sizeof(leak_ID));
              //we want to perform a delay as water leak detection causes the sensor to repeat the same message for ever until the leak is gone or the senor is muted. we do not want to send data to the server constantly, so once we do send data, we will perform a delay so we ignore the other data
              currentMillis = millis();
             if(previousMillis_leak==0){
                previousMillis_leak = currentMillis;
                send_to_server(unit_number,2,battery_status);
              }else{
               if (currentMillis - previousMillis_leak >= interval) {
                  previousMillis_leak = currentMillis;
                  send_to_server(unit_number,2,battery_status);
               }
              }
            }
          }

        /////////////////////////////
        //BATTERY UPDATE
        /////////////////////////////
        }else if(byte3==0xfc){
          Serial.print(F(" battery voltage update received -- "));
          Serial.print(battery_status);
          Serial.println(F(" volts "));

          if(battery_status<=310){ //verify the received battery voltage makes sense. if we recieved a battery voltage outside 1.5 to 3.1 volts, the voltage has to be corrupted and should be ignored
            if(battery_status>=150){
              //we want to perform a delay as battery status updates causes the sensor to repeat the same message several times. we do not want to send data to the server constantly, so once we do send data, we will perform a delay so we ignore the other data
              currentMillis = millis();
              if(previousMillis_battery==0){
                previousMillis_battery = currentMillis;
                send_to_server(unit_number,3,battery_status);
              }else{
               if (currentMillis - previousMillis_battery >= interval) {
                  previousMillis_battery = currentMillis;
                  send_to_server(unit_number,3,battery_status);
               }
              }
            }
          }
        /////////////////////////////
        //HEARTBEAT
        /////////////////////////////
        }else if(byte3==0xfd){
          Serial.println(F(" Sensor Heartbeat signal detected "));
          
          //we want to perform a delay as battery status updates causes the sensor to repeat the same message several times. we do not want to send data to the server constantly, so once we do send data, we will perform a delay so we ignore the other data
          currentMillis = millis();
          if(previousMillis_heart==0){
            previousMillis_heart = currentMillis;
            send_to_server(unit_number,4,battery_status);
          }else{
           if (currentMillis - previousMillis_heart >= interval) {
              previousMillis_heart = currentMillis;
              send_to_server(unit_number,4,battery_status);
           }
          }
        }else{
            Serial.print(F(" Unknown Event: "));
            Serial.print(byte3, HEX);
            Serial.print(F(" On Unit: "));
            Serial.println(unit_number, HEX);
        }
      }else{
        Serial.println(F(" PARITY FAILED!!!!! "));
      }

      //////////////////
      //CLEANUP
      /////////////////
          low_start = 0;
          high_start = 0;
          mess_start=false;
          mess_end=false;
          counter_high=0; 
          counter_low=0; 
          bit_number=0;
          interrupt_counter=0;
          byte1=0;
          byte2=0;
          byte3=0;
          byte4=0;
          byte5=0;
          byte6=0;
          x_number=0;
          tribit_ending_bit=0;
          counter=0;
          byte_ending_bit=0;
          unit_number=0;
          battery_status=0;
          parity_received=0;
          parity_calculation=0;
          memset(tribit_array, 0, sizeof(tribit_array));
          memset(interrupt_array, 0, sizeof(interrupt_array));
          memset(binary_data, 0, sizeof(binary_data));
          memset(b, 0, sizeof(b));
     // interrupt_counter=0;
      //bit_number=0;
      if(debug==1){
        Serial.println(F("Received entire message"));
      }
      //mess_end=false;
      //mess_start=false;
      //interrupts();
  }
}

void blink() {
  
    val = digitalRead(interruptPin);   // read the input pin so we can determine if the interrupt was caused by a change from 1 to 0, or from 0 to 1
    if(val == LOW){//current value is low, so we went from a 1 to a 0
      low_start = micros();//save the current time when the interrupt pin when from 1 to 0. 
      counter_high=low_start-high_start; //since we just went from a 1 to 0, we need to calculate how long the previous state of "1" was active for
      if((mess_start == true)&&(mess_end==false)){//as long as we are actively looking for a valid signal 
        if(interrupt_counter<100){ //we are only looking for 96 bytes. to prevent buffer overflows, make sure we do not save more data to the array than there is available space
          interrupt_array[interrupt_counter]=counter_high; //save the time duration of the high bit into the array
          interrupt_counter++;
          //if(interrupt_counter==1){
          //  message_start_Millis2 = millis();
          //}
        }else{
          interrupt_counter=0; //if we are going over 100 samples, and we only want 96 samples, something is wrong and we need to abort. 
          mess_end=false;
          mess_start=false;
          bit_number=0;
          counter_high=0;
          counter_low=0;
          if(debug==1){
            Serial.println(F("LR"));
          }
        }
      }
    }else if(val == HIGH){ //current value is high, so we went from a 0 to a 1
      high_start = micros();//save the current time when the interrupt pin when from 0 to 1.
      counter_low=high_start-low_start;//since we just went from a 0 to 1, we need to calculate how long the previous state of "0" was active for
      if((mess_start == true)&&(mess_end==false)){//as long as we are actively looking for a valid signal  
        if(interrupt_counter<100){ //we are only looking for 96 bytes. to prevent buffer overflows, make sure we do not save more data to the array than there is available space
          interrupt_array[interrupt_counter]=counter_low;
          interrupt_counter++;
          //if(interrupt_counter==1){
          //  message_start_Millis2 = millis();
          //}
        }else{
          interrupt_counter=0; //if we are going over 100 samples, and we only want 96 samples, something is wrong and we need to abort. 
          mess_end=false;
          mess_start=false;
          bit_number=0;
          counter_high=0;
          counter_low=0;
          if(debug==1){
            Serial.println(F("HR"));
          }
       }
    }
  }
}

//function to send the received data to a web server running the required PHP code which will send emails about button presses, leak detection or low battery events
void send_to_server(byte detected_unit, byte message_type, int voltage){
  wdt_reset();
  if (client.connect(serverip,80)) { // REPLACE WITH YOUR SERVER ADDRESS
    Serial.println(F("Client Connected"));
    Serial.println(F("Free RAM: "));
    Serial.println(freeRam());
    client.print(F("GET /admin/water_leak_detector.php?"));
    client.print(F("unit="));
    client.print(detected_unit,HEX); 
    client.print(F("&message="));
    client.print(message_type,HEX); 
    client.print(F("&batt="));
    client.print(voltage); 

    client.println(F(" HTTP/1.1"));
    client.print(F("Host: "));
    client.println(ip_to_str(serverip));
    client.println(F("Content-Type: application/x-www-form-urlencoded" ));
    client.println(F("Connection: close" ));
    client.println();
    client.println();
    client.println(F("Connection: close" ));
    client.println();
    client.println();
    client.println(F( "Connection: close" ));
    client.println();
    client.println();
    client.stop();
    client.stop();
    wdt_reset();
  } else{
    Serial.println(F("could not connect to server"));
    wdt_reset();
  }
}

// Just a utility function to nicely format an IP address.
const char* ip_to_str(const byte* ipAddr)
{
  static char buf[16];
  sprintf(buf, "%d.%d.%d.%d\0", ipAddr[0], ipAddr[1], ipAddr[2], ipAddr[3]);
  return buf;
}

//this function was borrowed from
//https://github.com/merbanan/rtl_433/blob/dc1577bd3fea05f3962b5e2358b5b5932dd2a34f/src/util.c#L328-L335
byte crc8le(byte message[], byte nBytes, byte polynomial, byte init)
{
    byte remainder = reverse8(init);
    byte byte1, bit;
    polynomial = reverse8(polynomial);

    for (byte1 = 0; byte1 < nBytes; byte1++) {
        remainder ^= message[byte1];
        for (bit = 0; bit < 8; bit++) {
            if (remainder & 1) {
                remainder = (remainder >> 1) ^ polynomial;
            } else {
                remainder = (remainder >> 1);
            }
        }
    }
    return remainder;
}

//this function was borrowed from
//https://github.com/merbanan/rtl_433/blob/dc1577bd3fea05f3962b5e2358b5b5932dd2a34f/src/util.c#L328-L335
byte reverse8(byte x)
{
    x = (x & 0xF0) >> 4 | (x & 0x0F) << 4;
    x = (x & 0xCC) >> 2 | (x & 0x33) << 2;
    x = (x & 0xAA) >> 1 | (x & 0x55) << 1;
    return x;
}

int freeRam () {
  extern int __heap_start, *__brkval; 
  int v; 
  return (int) &v - (__brkval == 0 ? (int) &__heap_start : (int) __brkval); 
}
