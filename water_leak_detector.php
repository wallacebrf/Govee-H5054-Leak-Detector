<?php
//for sending emails, this requires the following library
//https://github.com/PHPMailer/PHPMailer


//messages: 1=button press, 2=water leak, 3=battery voltage, 4=heartbeat 

/////////////////////////////////////////////
/////////////////////////////////////////////
//USER DEFINED VARIABLES
/////////////////////////////////////////////
/////////////////////////////////////////////
$debug=0; //if set to 1, it will print out a lot more detail when the page is loaded
$leak_detector_config_file="/volume1/web/config/config_files/config_files_local/leak_detector_config.txt";
date_default_timezone_set('Chicago');//set server time zone

/////////////////////////////////////////////
/////////////////////////////////////////////
//CODE START
/////////////////////////////////////////////
/////////////////////////////////////////////

include $_SERVER['DOCUMENT_ROOT']."/functions.php";
$generic_error="";

$current_date = date("Y-m-d H:i:s");//what is the time right now?
echo "current date: ".$current_date."<br>";
$dteEnd   = new DateTime($current_date);

require $_SERVER['DOCUMENT_ROOT'].'/admin/vendor/phpmailer/phpmailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;


//function called to send an alert email
//send_email($emailsubject, $emailmsg, $message_type, $sensor_file_log, $sensor_name, $leak_detector_config_file);
function send_email($emailsubject, $emailmsg, $message_type, $sensor_file_log, $sensor_name, $leak_detector_config_file){
	$send_sucessful=1;
	
	//get the configuration information
	$data = file_get_contents("$leak_detector_config_file");
	$pieces = explode(",", $data);
	$address=$pieces[0];
	$from_email_address=$pieces[1];	
	$smtp_server=$pieces[10];	
	$SMTPAuth_type=$pieces[11];
	$smtp_user=$pieces[12];
	$smtp_pass=$pieces[13];
	$SMTPSecure_type=$pieces[14];
	$smtp_port=$pieces[15];
	
	//email address may have multiple addresses separated by a semicolon, let's split them apart
	$to_email_exploded = explode(";", $address);
	

	print "Sending new notification email"; 

	require $_SERVER['DOCUMENT_ROOT']."/admin/vendor/autoload.php";

	//Create an instance; passing `true` enables exceptions
	$mail = new PHPMailer(true);

	try {
		//Server settings
		$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
		$mail->isSMTP();                                            //Send using SMTP
		$mail->Host       = ''.$smtp_server.'';                     //Set the SMTP server to send through
		if($SMTPAuth_type==1){
			$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
		}else{
			$mail->SMTPAuth   = false;                                   //Disable SMTP authentication
		}
		$mail->Username   = ''.$smtp_user.'';                     //SMTP username
		$mail->Password   = ''.$smtp_pass.'';                               //SMTP password
		if($SMTPSecure_type=="ENCRYPTION_STARTTLS"){
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            //Enable implicit TLS encryption
		}else if($SMTPSecure_type=="ENCRYPTION_SMTPS"){
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable SSL
		}
		$mail->Port       = $smtp_port;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

		//Recipients
		$mail->setFrom(''.$from_email_address.'');
		
		foreach ($to_email_exploded as $to_email_addresses) {
			//echo "".$to_email_addresses."<br>";
			$mail->addAddress(''.$to_email_addresses.'');     //Add a recipient
		}
		
		$mail->addReplyTo(''.$from_email_address.'');

		//Content
		$mail->isHTML(true);                                  //Set email format to HTML
		$mail->Subject = ''.$emailsubject.'';
		$mail->Body    = ''.$emailmsg.'';
		$mail->AltBody = ''.$emailmsg.'';

		$mail->send();
	} catch (Exception $e) {
		echo "<br><br>Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
		$send_sucessful=0;
	}
	
	if($send_sucessful==1){
		
		//if we are sending an email about a button press (message_type=1), then always send the email, no need to update the log files
		if($message_type=="2"){//if we are sending an email on water leak, and the email was successful, update the sensor log file with the current time to reset the 1 hour delay
			$current_date = date("Y-m-d H:i:s");//what is the time right now?
			//get variables from sensor_config_file
			$input_read=file_get_contents("$sensor_file_log/".$sensor_name.".txt");
			#explode the configuration into an array with the semicolon as the delimiter
			$pieces = explode(";", $input_read);
			#save the parameter values into the respective variable and remove the quotes
			$last_leak_email_sent=$pieces[1];
			$leak_detected=$pieces[3];
			$battery_status_received=$pieces[5];
			$battery_status_date_time=$pieces[7];
			$battery_status_voltage=$pieces[9];
			$button_press_received=$pieces[11];
			$button_press_date_time=$pieces[13];
			$sensor_enabled=$pieces[15];
			$heartbeat_received=$pieces[17];
			$heartbeat_date_time=$pieces[19];
			$sensor_physical_name=$pieces[21];
			$sensor_location=$pieces[23];
			file_put_contents("$sensor_file_log/".$sensor_name.".txt","last_leak_email_sent;".$current_date.";\r\nleak_detected;1;\r\nbattery_status_received;".$battery_status_received.";\r\nbattery_status_date_time;".$battery_status_date_time.";\r\nbattery_status_voltage;".$battery_status_voltage.";\r\nbutton_press_received;".$button_press_received.";\r\nbutton_press_date_time;".$button_press_date_time.";\r\nsensor_enabled;".$sensor_enabled.";\r\nheartbeat_received;".$heartbeat_received.";\r\nheartbeat_date_time;".$heartbeat_date_time.";\r\nsensor_physical_name;".$sensor_physical_name.";\r\nsensor_location;".$sensor_location.";");
		}
	}else{
		print "<br>E-Mail could not be sent, check system settings and network connection / status";
	}
}
if (file_exists("$leak_detector_config_file")) {
	//get the configuration information for influxdb. need to get the host IP, port, DB name, and DB access credentials. 
	$data = file_get_contents("$leak_detector_config_file");
	$pieces = explode(",", $data);
	$address=$pieces[0];
	$from_email_address=$pieces[1];	
	$influxdb_host=$pieces[2];
	$influxdb_port=$pieces[3];
	$influxdb_name=$pieces[4];
	$influxdb_user=$pieces[5];
	$influxdb_pass=$pieces[6];
	$measurement=$pieces[7];
	$sensor_config_file_location=$pieces[8];	
	$save_to_influx=$pieces[9];
	

	//get the variables shared from the arduino	
	[$sensor_name, $generic_error] = test_input_processing($_GET['unit'], "", "name", 0, 0);
	print "<br>Sensor_name Detected: ".$sensor_name."<br>";
	
	[$message, $generic_error] = test_input_processing($_GET['message'], "", "numeric", 1, 4);
	print "message is Detected: ".$message."->";
	
	if($message==1){
		print "Button Press<br>";
	}else if($message==2){
		print "Leak Detected<br>";
	}else if($message==3){
		print "Battery Status<br>";
	}else if($message==4){
		print "Heartbeat<br>";
	}
	[$batt, $generic_error] = test_input_processing($_GET['batt'], "", "numeric", 0, 320);
	
	
	if($generic_error==""){
	
		if ($sensor_name=="" OR $message=="" OR $batt==""){//check to make sure there is actually useful data
			echo "<br>bad data received<br>";
		}else{
			if ((file_exists("$sensor_config_file_location/".$sensor_name.".txt"))&&(strlen($sensor_name)==2)) { // is this one of the known configured sensors? also is the sensor name the correct length. the sensor names should always be two characters long
				$battery_voltage=$batt/100;//convert the int voltage from the arduino back into float to get the decimal points back
				print "Battery voltage is: ".$battery_voltage." volts<br>";
				//save data to influx db
				$post_url="".$measurement.",sensor_name=$sensor_name message=$message,batt=$batt";

				$output = shell_exec('curl -XPOST "http://'.$influxdb_host.':'.$influxdb_port.'/api/v2/write?bucket='.$influxdb_name.'&org=home" -H "Authorization: Token '.$influxdb_pass.'" --data-raw "'.$post_url.'"');
				echo "<pre>$output</pre>";
				
				
				//get variables from sensor_config_file
				$input_read=file_get_contents("$sensor_config_file_location/".$sensor_name.".txt");
				#explode the configuration into an array with the semicolon as the delimiter
				$pieces = explode(";", $input_read);
				#save the parameter values into the respective variable
				$last_leak_email_sent=$pieces[1];
				$leak_detected=$pieces[3];
				$battery_status_received=$pieces[5];
				$battery_status_date_time=$pieces[7];
				$battery_status_voltage=$pieces[9];
				$button_press_received=$pieces[11];
				$button_press_date_time=$pieces[13];
				$sensor_enabled=$pieces[15];
				$heartbeat_received=$pieces[17];
				$heartbeat_date_time=$pieces[19];
				$sensor_physical_name=$pieces[21];
				$sensor_location=$pieces[23];
				
				//calculate the difference between when the last water leak email message as sent and the current time. we only want to send water leak emails once per hour to stop the email inbox from being flooded as the leak sensors do not stop transmitting data while the leak is active until told to stop. 
				echo "last email: ".$last_leak_email_sent."<br>";
				$dteStart = new DateTime($last_leak_email_sent); 
				$difference = $dteStart->diff($dteEnd);
				$sensor_last_email=$difference->format("%H");//number of hours. this will be in the format of "00" for zero hours, "01" for one hour etc"
				echo "# of hours since last email: ".$sensor_last_email."<br>";
				
				//if debug is enabled, print out more data to the user
				if($debug==1){
					echo "<br>last_leak_email_sent is ".$last_leak_email_sent."<br>";
					echo "<br>leak_detected is ".$leak_detected."<br>";
					echo "<br>battery_status_received is ".$battery_status_received."<br>";
					echo "<br>battery_status_date_time is ".$battery_status_date_time."<br>";
					echo "<br>battery_status_voltage is ".$battery_status_voltage."<br>";
					echo "<br>button_press_received is ".$button_press_received."<br>";
					echo "<br>button_press_date_time is ".$button_press_date_time."<br>";
					echo "<br>sensor_enabled is ".$sensor_enabled."<br>";
					echo "<br>heartbeat_received is ".$heartbeat_received."<br>";
					echo "<br>heartbeat_date_time is ".$heartbeat_date_time."<br>";
					echo "<br>sensor_physical_name is ".$sensor_physical_name."<br>";
					echo "<br>sensor_location is ".$sensor_location."<br>";
					echo "<br>sensor_name is ".$sensor_name."<br>";
					echo "<br>message is ".$message."<br>";
					echo "<br>batt is ".$batt."<br>";
					echo "<br>sensor_last_email is ".$sensor_last_email."<br>";
				}
				
				/////////////////////////////////////////////
				/////////////////////////////////////////////
				//BUTTON PRESS PROCESSING
				/////////////////////////////////////////////
				/////////////////////////////////////////////
				if ($message=="1"){//button press was detected, send email. this is useful to determine if a sensor is in range, if the sensor's battery is good, or if adding a new sensor to the system	
					# Message Subject 
					$subject="".$sensor_physical_name." Button Press";
					# Message Body
					$msg = ""; 
					$msg .= "".$current_date." -- The <b>\"".$sensor_physical_name."\"</b> Leak Sensor Test Button Has Been Pressed.<br>".$eol; 
					$msg .= " <b><font color=\"green\">This Confirms The Sensor Is Functional</font></b>".$eol.$eol; 
					//send email
					//function send_email($emailsubject, $emailmsg, $message_type, $sensor_file_log, $sensor_name){
					send_email($subject, $msg, $message, $sensor_config_file_location, $sensor_name, $leak_detector_config_file);
					//update the sensor config file that a button press was detected and the date/time it was detected 
					file_put_contents("$sensor_config_file_location/".$sensor_name.".txt","last_leak_email_sent;".$last_leak_email_sent.";\r\nleak_detected;".$leak_detected.";\r\nbattery_status_received;".$battery_status_received.";\r\nbattery_status_date_time;".$battery_status_date_time.";\r\nbattery_status_voltage;".$battery_status_voltage.";\r\nbutton_press_received;1;\r\nbutton_press_date_time;".$current_date.";\r\nsensor_enabled;".$sensor_enabled.";\r\nheartbeat_received;".$heartbeat_received.";\r\nheartbeat_date_time;".$heartbeat_date_time.";\r\nsensor_physical_name;".$sensor_physical_name.";\r\nsensor_location;".$sensor_location.";");
				/////////////////////////////////////////////
				/////////////////////////////////////////////
				//WATER LEAK DETECTED PROCESSING
				/////////////////////////////////////////////
				/////////////////////////////////////////////
				}else if ($message=="2"){//leak has been detected,
					if ($sensor_last_email !="00"){ //has at least one hour passed since the last email was sent?
						# Message Subject 
						$subject="".$sensor_physical_name." LEAK DETECTED";
						# Message Body
						$msg = ""; 
						$msg .= "".$current_date." -- <font color=\"red\">The <b>\"".$sensor_physical_name."\"</b> Leak Sensor has detected A Leak.<br></font>".$eol; 
						$msg .= " <b>Check the ".$sensor_location." as soon as possible.</b>".$eol.$eol; 
						//send email
						send_email($subject, $msg, $message, $sensor_config_file_location, $sensor_name, $leak_detector_config_file);
					}else{
						echo "<br>".$sensor_physical_name." Sensor has already sent an email less than 1 hour ago<br>";
					}
				/////////////////////////////////////////////
				/////////////////////////////////////////////
				//BATTERY VOLOTAGE STATUS PROCESSING
				/////////////////////////////////////////////
				/////////////////////////////////////////////
				}else if ($message=="3"){//BATTERY STATUS has been detected
					//battery_status=(1.7944 + (0.0121 * byte4))*100  --> equation the arduino uses to calculate the voltage based on the battery percentage (byte4) from 0 to 100.
					$battery_percent=round(($battery_voltage-1.7944)/0.0121,0); //reverse the voltage calculation to get the battery percentage 
					# Message Subject 
					$subject="".$sensor_physical_name." Battery Status Update";
					# Message Body
					$msg = ""; 
					$msg .= "".$current_date." -- The <b>\"".$sensor_physical_name."\"</b> Leak Sensor has sent a battery status update. <br>".$eol; 
					$msg .= " The battery is at <b>(".$battery_voltage." volts) or (".$battery_percent."%)</b>".$eol.$eol; 
					//send email
					send_email($subject, $msg, $message, $sensor_config_file_location, $sensor_name, $leak_detector_config_file);
					//update the sensor config file that a battery voltage event has happened, when the battery update happened, and what the voltage was reported as
					file_put_contents("$sensor_config_file_location/".$sensor_name.".txt","last_leak_email_sent;".$last_leak_email_sent.";\r\nleak_detected;".$leak_detected.";\r\nbattery_status_received;1;\r\nbattery_status_date_time;".$current_date.";\r\nbattery_status_voltage;".$battery_voltage.";\r\nbutton_press_received;".$button_press_received.";\r\nbutton_press_date_time;".$button_press_date_time.";\r\nsensor_enabled;".$sensor_enabled.";\r\nheartbeat_received;".$heartbeat_received.";\r\nheartbeat_date_time;".$heartbeat_date_time.";\r\nsensor_physical_name;".$sensor_physical_name.";\r\nsensor_location;".$sensor_location.";");
				/////////////////////////////////////////////
				/////////////////////////////////////////////
				//HEARTBEST STATUS PROCESSING
				/////////////////////////////////////////////
				/////////////////////////////////////////////
				}else if ($message=="4"){//Heartbeat has been detected
					# Message Subject 
					$subject="".$sensor_physical_name." Heartbeat Status Update";
					# Message Body
					$msg = ""; 
					$msg .= "".$current_date." -- The <b>\"".$sensor_physical_name."\"</b> Leak Sensor has sent a Heartbeat status update.".$eol; 
					$msg .= "".$eol.$eol; 
					//send email
					send_email($subject, $msg, $message, $sensor_config_file_location, $sensor_name, $leak_detector_config_file);
					//update the sensor config file that a heartbeat was detected, when it was detected 
					//note, newer leak sensors no longer appear to send heartbeat signals, but this is here for backward compatibility. 
					file_put_contents("$sensor_config_file_location/".$sensor_name.".txt","last_leak_email_sent;".$last_leak_email_sent.";\r\nleak_detected;".$leak_detected.";\r\nbattery_status_received;".$battery_status_received.";\r\nbattery_status_date_time;".$battery_status_date_time.";\r\nbattery_status_voltage;".$battery_status_voltage.";\r\nbutton_press_received;".$button_press_received.";\r\nbutton_press_date_time;".$button_press_date_time.";\r\nsensor_enabled;".$sensor_enabled.";\r\nheartbeat_received;1;\r\nheartbeat_date_time;".$current_date.";\r\nsensor_physical_name;".$sensor_physical_name.";\r\nsensor_location;".$sensor_location.";");
				}
				
			}else if (strlen($sensor_name)>2) { // is this one of the known configured sensors?
				echo "<br>Sensor Name Error, Sensor name should only be two characters in length<br>";
			}else if ((! file_exists("$sensor_config_file_location/".$sensor_name.".txt"))&&(strlen($sensor_name)==2)) { // is this one of the known configured sensors?
				if ($message=="1"){
					echo "<br>Unknown Sensor Button Press Has Been detected<br>";
					echo "<br>Sending Email Notification as This Could be A New Sensor Being Added to the System<br>";
					# Message Subject 
					$subject="UNKNOWN SENSOR DETECTED";
					# Message Body
					$msg = ""; 
					$msg .= "".$current_date." -- A New Leak Sensor Been Detected.<br>".$eol; 
					$msg .= " The Sensor ID is <b>\"".$sensor_name."\"</b>".$eol.$eol; 
					send_email($subject, $msg, $message, $sensor_config_file_location, $sensor_name, $leak_detector_config_file);
				}else{
					echo "<br>Unknown Sensor Water Leak Has Been detected<br>";
				}
			
			}
			}
		}else{
			print "supplied data does not conform to expected format --> ".$generic_error."<br><br>";
		}
}else{
	print "<br>ERROR! -- Configuration file \"".$leak_detector_config_file."\" could not be found.<br>";
}
?>
