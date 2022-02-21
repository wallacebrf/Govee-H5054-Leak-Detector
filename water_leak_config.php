<?php

///////////////////////////////////////////////////
//User Defined Variables
///////////////////////////////////////////////////
$leak_detector_config_file="/volume1/web/config/config_files/config_files_local/leak_detector_config.txt";
$use_login_sessions=true; //set to false if not using user login sessions
$form_submittal_destination="index.php?page=1"; //set to the destination the HTML form submital should be directed to

///////////////////////////////////////////////////
//Beginning of configuration page
///////////////////////////////////////////////////
if($use_login_sessions){
	if($_SERVER['HTTPS']!="on") {

	$redirect= "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

	header("Location:$redirect"); } 

	error_reporting(E_ALL ^ E_NOTICE);
	// Initialize the session
	if(session_status() !== PHP_SESSION_ACTIVE) session_start();
	 
	// Check if the user is logged in, if not then redirect him to login page
	if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
		header("location: login.php");
		exit;
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
	$smtp_server=$pieces[10];	
	$SMTPAuth_type=$pieces[11];
	$smtp_user=$pieces[12];
	$smtp_pass=$pieces[13];
	$SMTPSecure_type=$pieces[14];
	$smtp_port=$pieces[15];
	
	include $_SERVER['DOCUMENT_ROOT']."/functions.php";
	$sensor_code_error="";
	$sensor_name_error="";
	$sensor_location_error="";


	if(!empty($_GET["add_sensor"]) and is_numeric($_GET["add_sensor"])){
		[$add_sensor, $generic_error] = test_input_processing($_GET['add_sensor'], 0, "numeric", 0, 1);
	}  else {
		$add_sensor = 0;
	}

	///////////////////////////////////////////////////
	//ADD NEW SENSOR SUB-PAGE
	///////////////////////////////////////////////////
	if($add_sensor==1){
		if(isset($_POST['sensor_add_submit'])){
			///////////////////////////////////////////////////
			//PROCESS SUBMITTED DATA
			///////////////////////////////////////////////////
			//perform data verification of submitted values
			
			if (strlen($_POST['sensor_add_sensor_code'])==2){
				[$sensor_add_sensor_code, $sensor_code_error] = test_input_processing($_POST['sensor_add_sensor_code'], "", "name", 0, 0);
			}else{
				  $sensor_code_error="<font color=\"red\">Sensor Code Length is incorrect. code length should be two characters long</font>";
				  $sensor_add_sensor_code="";
			}
			
			//perform data verification of submitted values
			[$sensor_add_sensor_name, $sensor_name_error] = test_input_processing($_POST['sensor_add_sensor_name'], "", "name", 0, 0);
			
			//perform data verification of submitted values
			[$sensor_add_sensor_location, $sensor_location_error] = test_input_processing($_POST['sensor_add_sensor_location'], "", "name", 0, 0);
			
			///////////////////////////////////////////////////
			//SAVE SUBMITTED DATA FOR NEW SENSOR
			///////////////////////////////////////////////////
			if(($sensor_add_sensor_code!="")&&($sensor_add_sensor_name!="")&&($sensor_add_sensor_location!="")){
				if (!file_exists("$sensor_config_file_location/".$sensor_add_sensor_code.".txt")) {
					file_put_contents("$sensor_config_file_location/".$sensor_add_sensor_code.".txt","last_leak_email_sent;2000-01-01 00:00:01;\r\nleak_detected;0;\r\nbattery_status_received;0;\r\nbattery_status_date_time;2000-01-01 00:00:01;\r\nbattery_status_voltage;0;\r\nbutton_press_received;0;\r\nbutton_press_date_time;2000-01-01 00:00:01;\r\nsensor_enabled;1;\r\nheartbeat_received;0;\r\nheartbeat_date_time;2000-01-01 00:00:01;\r\nsensor_physical_name;".$sensor_add_sensor_name.";\r\nsensor_location;".$sensor_add_sensor_location.";");
					$file_error=0;
				}else{
					$sensor_code_error="<font color=\"red\">Sensor \"".$sensor_add_sensor_code."\" Already Exists</font>";
					$file_error=1;
				}
			}
		}
			
		///////////////////////////////////////////////////
		//BEGIN ACTUAL HTML CODE GENERATION FOR ADDING NEW SENSOR
		///////////////////////////////////////////////////
		print "<font size=\"6\">Add New Leak Sensor</font>";
		if(isset($_POST['sensor_add_submit'])){
			if($file_error==0){
				if (file_exists("$sensor_config_file_location/".$sensor_add_sensor_code.".txt")) {
					echo "<br><font color=\"green\">SENSOR CODE \"".$sensor_add_sensor_code."\" ADDED SUCESSFULLY</font>";
				}else{
					echo "<br><font color=\"red\">SENSOR ADD FAILED</font>";
				}
			}
		}
		//print out form submittal errors if they exist
		if($sensor_code_error!=""){
			echo "<br>Sensor Code: ".$sensor_code_error."";
		}
		if($sensor_name_error!=""){
			echo "<br>Sensor Name: ".$sensor_name_error."";
		}
		if($sensor_location_error!=""){
			echo "<br>Sensor Location: ".$sensor_location_error."";
		}
		print "<br><center><a href = \"index.php?page=1\">CANCEL</a></center><br>
		<table>
			<tr>
				<td>
					<form action=\"".$form_submittal_destination."&add_sensor=1\" method=\"post\">
						<table border=\"1\">
							<tr>
								<td align=\"center\">
									Sensor Code
								</td>
								<td align=\"center\">
									Sensor Name
								</td>
								<td align=\"center\">
									Sensor Location
								</td>
							<tr>
								<td align=\"center\">
									<input type=\"text\" name=\"sensor_add_sensor_code\" value=\"\">
								</td>
								<td align=\"center\">
									<input type=\"text\" name=\"sensor_add_sensor_name\" value=\"\">
								</td>
								<td align=\"center\">
									<input type=\"text\" name=\"sensor_add_sensor_location\" value=\"\">
								</td>
							</tr>
							<tr>
								<td align=\"center\" colspan=\"7\">
									<input type=\"submit\" name=\"sensor_add_submit\" value=\"Submit\" />
								</td>
							</tr>
						</table>
					</form>
				</td>
			</tr>
		</table>";
		
	///////////////////////////////////////////////////
	//NOT ADDING SENSORS - DISPLAY STATUS PAGE
	///////////////////////////////////////////////////
	}else if($add_sensor==0){
		///////////////////////////////////////////////////
		//Tool Tip Support Code
		///////////////////////////////////////////////////
		echo 
		"<style>
		/* Tooltip container */
		.tooltip {
		  position: relative;
		  display: inline-block;
		  border-bottom: 1px dotted black; /* If you want dots under the hoverable text */
		}

		/* Tooltip text */
		.tooltip .tooltiptext {
		  visibility: hidden;
		  width: 120px;
		  background-color: black;
		  color: #fff;
		  text-align: center;
		  padding: 5px 0;
		  border-radius: 6px;
		 
		  /* Position the tooltip text - see examples below! */
		  position: absolute;
		  z-index: 1;
		}

		/* Show the tooltip text when you mouse over the tooltip container */
		.tooltip:hover .tooltiptext {
		  visibility: visible;
		}
		</style>";
		
		///////////////////////////////////////////////////
		//Base configuration table entry generation
		///////////////////////////////////////////////////
		if(isset($_POST['submit_base_config'])){
		
			$address_error="";
			$from_email_address_error="";
			$influxdb_host_error="";
			$influxdb_port_error="";
			$influxdb_name_error="";
			$influxdb_user_error="";
			$influxdb_pass_error="";
			$measurement_error="";
			$sensor_config_file_location_error="";
			$generic_error="";
			$smtp_server_error="";
			$SMTPAuth_type_error="";
			$smtp_user_error="";
			$smtp_pass_error="";
			$SMTPSecure_type_error="";
			$smtp_port_error="";
			
			[$address, $address_error] = test_input_processing($_POST['to_email_address_submitted'], $address, "email", 0, 0);
			[$from_email_address, $from_email_address_error] = test_input_processing($_POST['from_email_address_submitted'], $from_email_address, "email", 0, 0);
			[$influxdb_host, $influxdb_host_error] = test_input_processing($_POST['influxdb_host_submitted'], $influxdb_host, "ip", 0, 0);
			[$influxdb_port, $influxdb_port_error] = test_input_processing($_POST['influxdb_port_submitted'], $influxdb_port, "numeric", 0, 65000);
			[$influxdb_name, $influxdb_name_error] = test_input_processing($_POST['influxdb_db_submitted'], $influxdb_name, "name", 0, 0);
			[$influxdb_user, $influxdb_user_error] = test_input_processing($_POST['influxdb_user_submitted'], $influxdb_user, "name", 0, 0);
			[$influxdb_pass, $influxdb_pass_error] = test_input_processing($_POST['influxdb_pass_submitted'], $influxdb_pass, "password", 0, 0);
			[$measurement, $measurement_error] = test_input_processing($_POST['influxdb_measurement_submitted'], $measurement, "name", 0, 0);
			[$sensor_config_file_location, $sensor_config_file_location_error] = test_input_processing(RemoveSpecialChar_directory($_POST['sensor_config_file_location_submitted']), $sensor_config_file_location, "string", 0, 0);
			[$save_to_influx, $generic_error] = test_input_processing($_POST['save_to_influx_submitted'], "", "checkbox", 0, 0);
			[$smtp_server, $smtp_server_error] = test_input_processing($_POST['smtp_server_submitted'], $smtp_server, "url", 0, 0);
			[$SMTPAuth_type, $SMTPAuth_type_error] = test_input_processing($_POST['SMTPAuth_type_submitted'], "", "checkbox", 0, 0);
			[$smtp_user, $smtp_user_error] = test_input_processing($_POST['smtp_user_submitted'], $smtp_user, "name", 0, 0);
			[$smtp_pass, $smtp_pass_error] = test_input_processing($_POST['smtp_pass_submitted'], $smtp_pass, "password", 0, 0);
			[$SMTPSecure_type, $SMTPSecure_type_error] = test_input_processing($_POST['SMTPSecure_type_submitted'], $SMTPSecure_type, "name", 0, 0);
			[$smtp_port, $smtp_port_error] = test_input_processing($_POST['smtp_port_submitted'], $smtp_port, "numeric", 0, 65000);
		
		
			file_put_contents("$leak_detector_config_file","".$address.",".$from_email_address.",".$influxdb_host.",".$influxdb_port.",".$influxdb_name.",".$influxdb_user.",".$influxdb_pass.",".$measurement.",".$sensor_config_file_location.",".$save_to_influx.",".$smtp_server.",".$SMTPAuth_type.",".$smtp_user.",".$smtp_pass.",".$SMTPSecure_type.",".$smtp_port."");
		}
		
		print "
		<br><form action=\"".$form_submittal_destination."\" method=\"post\">
			<table border=\"1\">
				<tr>
					<th align=\"center\">Leak Sensor Primary Configuration</th>
				<tr>
					<td align=\"left\">
						<p>To Email Address: <input type=\"text\" name=\"to_email_address_submitted\" value=".$address."><font size=\"1\"> Separate addresses by a semi-colon</font> ".$address_error."</p>
						<p>From Email Address: <input type=\"text\" name=\"from_email_address_submitted\" value=".$from_email_address."> ".$from_email_address_error."</p>
						<p>InfluxDB Host IP: <input type=\"text\" name=\"influxdb_host_submitted\" value=".$influxdb_host."> <font size=\"1\">\"localhost\" is allowed</font> ".$influxdb_host_error."</p>
						<p>InfluxDB Port: <input type=\"text\" name=\"influxdb_port_submitted\" value=".$influxdb_port."> ".$influxdb_port_error."</p>
						<p>InfluxDB DB Name: <input type=\"text\" name=\"influxdb_db_submitted\" value=".$influxdb_name."> ".$influxdb_name_error."</p>
						<p>InfluxDB DB User: <input type=\"text\" name=\"influxdb_user_submitted\" value=".$influxdb_user."> ".$influxdb_user_error."</p>
						<p>InfluxDB DB Pass / API Token: <input type=\"text\" name=\"influxdb_pass_submitted\" value=".$influxdb_pass."> ".$influxdb_pass_error."</p>
						<p>InfluxDB Measurement Name: <input type=\"text\" name=\"influxdb_measurement_submitted\" value=".$measurement."> ".$measurement_error."</p>
						<p>Sensor Config File Directory: <input type=\"text\" name=\"sensor_config_file_location_submitted\" value=".$sensor_config_file_location."> ".$sensor_config_file_location_error."</p>
						<p>Log Data to InfluxDB: <input type=\"checkbox\" name=\"save_to_influx_submitted\" value=\"1\" ";

						if ($save_to_influx==1){
							print "checked";
						}
						print"></p>
						<br>
						<p><b>SMTP Server Settings</b></p>
						<p>SMTP Server: <input type=\"text\" name=\"smtp_server_submitted\" value=".$smtp_server."> <font size=\"1\">\"localhost\" is allowed</font> ".$smtp_server_error."</p>
						<p>SMTP User: <input type=\"text\" name=\"smtp_user_submitted\" value=".$smtp_user."> ".$smtp_user_error."</p>
						<p>SMTP Password: <input type=\"text\" name=\"smtp_pass_submitted\" value=".$smtp_pass."> ".$smtp_pass_error."</p>
						<p>SMTP Authorization Required: <input type=\"checkbox\" name=\"SMTPAuth_type_submitted\" value=".$SMTPAuth_type."";
						if ($SMTPAuth_type==1){
							print " checked";
						}
						print"></p>
						<p>Encryption Type: <select name=\"SMTPSecure_type_submitted\">";
							if ($SMTPSecure_type=="ENCRYPTION_STARTTLS"){
								print "<option value=\"ENCRYPTION_STARTTLS\" selected>STARTTLS</option>
								<option value=\"ENCRYPTION_SMTPS\">SMTPS</option>";
							}else if ($SMTPSecure_type=="ENCRYPTION_SMTPS"){
								print "<option value=\"ENCRYPTION_STARTTLS\">STARTTLS</option>
								<option value=\"ENCRYPTION_SMTPS\" selected>SMTPS</option>";
							}
						print "</select></p>
						<p>SMTP Port: <input type=\"text\" name=\"smtp_port_submitted\" value=".$smtp_port."> ".$smtp_port_error."</p>
					</td>
				</tr>
			</table>
			<center><input type=\"submit\" name=\"submit_base_config\" value=\"Submit\" /></center>
		</form>";
		///////////////////////////////////////////////////
		//Individual Sensor Processing
		///////////////////////////////////////////////////
		//reads contents of current folder for all the file names, which will be all of the leak sensor config files

		//opens the directory for reading
		if(is_dir($sensor_config_file_location)){
			$dp = opendir($sensor_config_file_location)
				or die("<br /><font color=\"#FF0000\">Cannot Open The Directory </font><br>");
				
			//add all files in directory to $theFiles array
			while ($currentFile !== false){
				$currentFile = readDir($dp);
				$theFiles[] = $currentFile;
			} // end while
				
			//because we opened the dir, we need to close it
			closedir($dp);
				
			//sorts all the files
			rsort ($theFiles);
				
			$imageFiles = $theFiles;
				
			$last_image = end($imageFiles);
			//begins printing out the gallery
			$output = "";
			$picInRow = 0;
			$num_sensors=count($imageFiles)-3;
			print "<br><center><a href = \"index.php?page=1&add_sensor=1\">ADD NEW SENSOR</a><br>".$num_sensors." Sensors Currently Configured</center><br>";
			print "<table>";
			foreach ($imageFiles as $currentFile){
				$sensor_name_error="";
				$sensor_location_error="";
				$pieces = explode(".", $currentFile);
				$file_name=$pieces[0];
				$file_extension=$pieces[1];	
				$delete_sensor=0;
				$generic_error="";
				
				$urlname=substr($currentFile,0,10);
				if (strlen($urlname)>5){
					if ($picInRow == 0){
					
						$input_read=file_get_contents("".$sensor_config_file_location."/".$currentFile."");
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
						
						$battery_percent=round(($battery_status_voltage-1.7944)/0.0121,0); //reverse the voltage calculation to get the battery percentage 
						
						if(isset($_POST["".$file_name."_submit"])){
							
							//perform data verification of submitted values
							
							[$sensor_physical_name, $sensor_name_error] = test_input_processing($_POST["".$file_name."_sensor_name"], $pieces[21], "string", 0, 0);
							
							//perform data verification of submitted values
							[$sensor_location, $sensor_location_error] = test_input_processing($_POST["".$file_name."_sensor_location"], $pieces[23], "string", 0, 0);
							
							//perform data verification of submitted values
							if (strip_tags(stripslashes(trim(htmlspecialchars($_POST["".$file_name."_clear_leak"])==1)))){
								$leak_detected=0;
								$last_leak_email_sent="2000-01-01 00:00:01";
							}
							
							if (strip_tags(stripslashes(trim(htmlspecialchars($_POST["".$file_name."_clear_heart"])==1)))){
								$heartbeat_received=1;
							}
							
							//perform data verification of submitted values
							if (strip_tags(stripslashes(trim(htmlspecialchars($_POST["".$file_name."_delete"])==1)))){
								$delete_sensor=1;
							}else{
								$delete_sensor=0;
							}
					  
							if($delete_sensor==0){
								file_put_contents("$sensor_config_file_location/".$currentFile."","last_leak_email_sent;".$last_leak_email_sent.";\r\nleak_detected;".$leak_detected.";\r\nbattery_status_received;".$battery_status_received.";\r\nbattery_status_date_time;".$battery_status_date_time.";\r\nbattery_status_voltage;".$battery_status_voltage.";\r\nbutton_press_received;".$button_press_received.";\r\nbutton_press_date_time;".$button_press_date_time.";\r\nsensor_enabled;".$sensor_enabled.";\r\nheartbeat_received;".$heartbeat_received.";\r\nheartbeat_date_time;".$heartbeat_date_time.";\r\nsensor_physical_name;".$sensor_physical_name.";\r\nsensor_location;".$sensor_location.";");
							}else if($delete_sensor==1){
								unlink("$sensor_config_file_location/".$currentFile."");
							}
						}

					
						if($battery_status_received==1){
							$battery_status_text="<div class=\"tooltip\">$battery_percent %<span class=\"tooltiptext\">".$battery_status_voltage." volts<br>Received:<br>".$battery_status_date_time."</span></div>";
						}else{
							$battery_status_text="<div class=\"tooltip\">N/A<span class=\"tooltiptext\">No Battery Status Has Been Received</span></div>";
						}

						if($leak_detected==1){
							$leak_status_text="Leak Detected:<br>".$last_leak_email_sent."";
							$leak_status_image="<img src=\"red.png\" alt=\"red\" width=\"25\" height=\"25\">";
						}else{
							$leak_status_text="No Active Leaks<br><br>Last Leak:<br>".$last_leak_email_sent."";
							$leak_status_image="<img src=\"green.png\" alt=\"green\" width=\"25\" height=\"25\">";
						}

						if($button_press_received==1){
							$button_status_text="Last Sensor Button Press:<br>".$button_press_date_time."";
							$button_status_image="<img src=\"green.png\" alt=\"green\" width=\"25\" height=\"25\">";
						}else{
							$button_status_text="No Button Press Events<br>Have Been Detected";
							$button_status_image="<img src=\"red.png\" alt=\"red\" width=\"25\" height=\"25\">";
						}

						if($heartbeat_received==1){
							$heart_status_text="Last HeartBeat:<br>".$heartbeat_date_time."";
							$heart_status_image="<img src=\"green.png\" alt=\"green\" width=\"25\" height=\"25\">";
						}else{
							$heart_status_text="No Heartbeat<br>Has Been Detected";
							$heart_status_image="<img src=\"red.png\" alt=\"red\" width=\"25\" height=\"25\">";
						}
						print "
					<tr>";
					}
					///////////////////////////////////////////////////
					//BEGIN ACTUAL HTML CODE GENERATION FOR STATUS PAGE
					///////////////////////////////////////////////////
					if($delete_sensor==0){//DONT PRINT THIS LOOP IF WE JUST DELETED THE CONFIG FILE
						echo "
							<td>
								<form action=\"".$form_submittal_destination."\" method=\"post\">
									<table border=\"1\">
										<tr>
											<td align=\"center\">
												ID Code
											</td>
											<td align=\"center\">
												Name
											</td>
											<td align=\"center\">
												Location
											</td>
											<td align=\"center\">
												Battery
											</td>
											<td align=\"center\">
												Leak
											</td>
												<td align=\"center\">
													Button
											</td>
											<td align=\"center\">
												HeartBeat
											</td>
										<tr>
											<td align=\"center\">
												<a href = \"".$home."/admin/leak/".$currentFile."\" target=\"_blank\">".$file_name."</a>
											</td>
											<td align=\"center\">
												<input type=\"text\" name=\"".$file_name."_sensor_name\" value=\"".$sensor_physical_name."\"><br>".$sensor_name_error."
											</td>
											<td align=\"center\">
												<input type=\"text\" name=\"".$file_name."_sensor_location\" value=\"".$sensor_location."\"><br>".$sensor_location_error."
											</td>
											<td align=\"center\">
												".$battery_status_text."
											</td>
											<td align=\"center\">
												<div class=\"tooltip\">".$leak_status_image."
													<span class=\"tooltiptext\">".$leak_status_text."</span>
												</div>
											</td>
											<td align=\"center\">
												<div class=\"tooltip\">".$button_status_image."
													<span class=\"tooltiptext\">".$button_status_text."</span>
												</div>
											</td>
											<td align=\"center\">
												<div class=\"tooltip\">".$heart_status_image."
													<span class=\"tooltiptext\">".$heart_status_text."</span>
												</div>
											</td>
										</tr>
										<tr>
											<td align=\"center\" colspan=\"7\">
												<table border=\"0\"
													<tr>
														<td>
															Clear Active Leak: <input type=\"checkbox\" name=\"".$file_name."_clear_leak\" value=\"1\"> ||
														</td>
														<td>
															Ignore Heartbeat: <input type=\"checkbox\" name=\"".$file_name."_clear_heart\" value=\"1\"> ||
														</td>
														<td>
															DELETE SENSOR: <input type=\"checkbox\" name=\"".$file_name."_delete\" value=\"1\">
														</td>
													</tr>
													<tr>
														<td align=\"center\" colspan=\"3\">
															<input type=\"submit\" name=\"".$file_name."_submit\" value=\"Submit\" />
														</td>
													</tr>
												</table>	
											</td>
										</tr>
									</table>
								</form>
							</td>";
						$picInRow++;
						if ($picInRow == 1){//this controls how many images are in each row, this being set to 5 makes 5 images be in each row
							print "</tr>";
						   $picInRow = 0;
						}else{
							if ($currentFile == $last_image){
								print "</tr>";
							}
						}
					}
				}
			}
			print "</table>";
		}else{
			print "<br>ERROR! -- Configuration directory \"".$sensor_config_file_location."\" could not be found.<br>";
		}
	}
	$_POST = array();
}else{
	print "<font color=\"red\"><center><br>Configuration file:<br><br>\"".$leak_detector_config_file."\" <br><br>has been created with default values. <b>Refresh</b> this page and configure the base settings before adding new leak sensors</center><br></font>";
	file_put_contents("$leak_detector_config_file","to_email_num_1;to_email_num_2,from_email,localhost,8086,db_name,db_user,db_password,influx_measurement_name,/volume1/web/admin/leak,0,smtp_server,1,smtp_user,smtp_pass,ENCRYPTION_STARTTLS,587");
}
	
?>
