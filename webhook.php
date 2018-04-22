
<?php
include_once("rcon/CServerRcon.php");
include_once("config.php");

define('API_URL', 'https://api.telegram.org/bot'.$token.'/');
//read incoming info and grab the chatID
Global $chatID;

$content = file_get_contents("php://input");
$update = json_decode($content, true);
$chatID = $update["message"]["chat"]["id"];
$message = $update["message"]["text"];

$db = new mysqli($host, $name, $password, $database);

if ($db->connect_error) {
    sendMessage('Connect Error (' . $db->connect_errno . ') '
            . $db->connect_error);
	die();
}


$sql = "CREATE TABLE IF NOT EXISTS `telegram` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(32) NOT NULL,
                  `ip` varchar(32) NOT NULL,
                  `port` int(11) NOT NULL,
                  `password` varchar(32) NOT NULL,
                  `chatID` int(32) NOT NULL,
                  `connected` int(11) NOT NULL,
                  PRIMARY KEY (`id`)
                )";

$sql = $db->query($sql);

if (!$sql){
	sendMessage("Query failed: " .$db->error);
	die();
}

$sql = "SELECT * FROM `telegram` WHERE `chatID`= $chatID";
$sql = $db->query($sql);

if (!$sql){
	sendMessage("Query failed: " .$db->error);
	die();
}

if (mysqli_num_rows($sql) > 0){
	while($result = mysqli_fetch_array($sql)){
		if (!empty($result['connected'])){
			if (substr($message, 0, 11) === "/disconnect" && strlen($message) == 11){

				$sql = "UPDATE `telegram` SET `connected` = '0' WHERE `chatID` = $chatID";
				$sql = $db->query($sql);

				if (!$sql){
					sendMessage("Failed to disconnect: " .$db->error);
					die();
				}

				sendMessage("Disconnected from " .$result['name']. "(." .$result['ip'] . $result['port']. ".)");
				die();
			}

			sendMessage("Sending command: $message");
			$r = new CServerRcon($result['ip'], $result['port'], $result['password']);
			if (!$r->Auth()) {
				sendMessage("Wrong RCON password, you should delete the server(/delserver <name>) and add it again!");
				die("Wrong RCON password");
			}

			$reply = $r->rconCommand($message);
			sendMessage($reply);
			die();
		}
	}
}

if ((substr($message, 0, 10) === "/start" && strlen($message) ==  6) || (substr($message, 0, 5) === "/help" && strlen($message) ==  5) || $message[0] != "/") {
	sendMessage("Hi! \n\n This bot allows you to connect using RCON to any source server.\n\n
			*Available commands*: \n*1.* /rcon  (ServerName) (IP) (Port) (Password) --> `Adds a server to the database and connects to it.`
					  \n*2.* /rcon	`(ServerName)` --> Connects to an added server.
					  \n*3.* /serverlist --> Displays added servers.
					  \n*4.* /delserver `(ServerName)` --> Removes a server.
					  \n*5.* /disconnect --> Disconnects from a server.
					  \n`Every command that it's sent from after connecting is sent to the Server's RCON. DON'T type /kick but only kick(or sm_kick)\nThe session never expires.`
					  \n`Spaces in the ServerName are not allowed!`
					  \n\n`Type again` /start `to display this message`");
	die();
}

if (substr($message, 0, 10) === "/delserver" && $message[10] == " ")  {
	$message = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $message)));
	$explode = explode(" ", $message, 2);

	$strArr = $explode;

	$name = $strArr[1];

	$sql = "SELECT * FROM `telegram` WHERE `chatID`= '$chatID' AND `name` = '$name'";
	$sql = $db->query($sql);

	if (!$sql){
		sendMessage("Query failed: " .$db->error);
		die();
	}

	if (mysqli_num_rows($sql) > 0){
		$sql = "DELETE FROM `telegram` WHERE `chatID` = '$chatID' AND 'name' = '$name'";

		$sql = $db->query($sql);

		if (!$sql){
			sendMessage("Query failed: " .$db->error);
			die();
		}
		sendMessage("Deleted $name");
	}
	else{
		sendMessage("Couldn't find a server named '$name'");
	}
	$sql->close();
	$db->close();
}
else if (substr($message, 0, 10) === "/delserver" && strlen($message) == 10) {
	sendMessage("Invalid /delserver format: /delserver (ServerName)");
}
else if (substr($message, 0, 11) === "/serverlist" && strlen($message) == 11 || $message[11] == " ")  {

	$sql = "SELECT * FROM `telegram` WHERE `chatID`= '$chatID'";
	$sql = $db->query($sql);

	if (!$sql){
		sendMessage("Query failed: " .$db->error);
		die();
	}

	$i = 1;
	if (mysqli_num_rows($sql) == 0){
		sendMessage("You haven't added any server! Type /rcon (Name) (IP) (Port) (Password) ");
	}
	else{
		while($result = mysqli_fetch_array($sql)){
			sendMessage(sprintf("%s. %s (%s:%s)", $i++, $result['name'], $result['ip'], $result['port']));
		}
	}

}
else if (substr($message, 0, 5) === "/rcon" && $message[5] == " ")  {
	$message = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $message)));
	$explode = explode(" ", $message, 5);

	$strArr = $explode;

	$name = $strArr[1];
	$ip = $strArr[2];
	$port = $strArr[3];
	$password = $strArr[4];
	$connected = "1";

	$sql = "SELECT * FROM `telegram` WHERE `chatID`= '$chatID' AND `name` = '$name'";
	$sql = $db->query($sql);

	if (!$sql){
		sendMessage("Query failed: " .$db->error);
		die();
	}

	if (empty($ip)){
		if (mysqli_num_rows($sql) > 0){
			$result = mysqli_fetch_array($sql);

			sendMessage("Connecting to $name ". $result['ip'] . ":" .$result['port']. " ...");

			$sql = "UPDATE `telegram` SET `connected` = '1' WHERE `chatID`= '$chatID' AND `name` = '$name'";
			$sql = $db->query($sql);

			if (!$sql){
				sendMessage("Query failed: " .$db->error);
				die();
			}

			$r = new CServerRcon($result['ip'], $result['port'], $result['password']);
			if (!$r->Auth()) {
				sendMessage("Wrong RCON password!");
				$sql->close();
				$db->close();
			}

			sendMessage("Connected!");
			$sql->close();
			$db->close();
			die();
		}

		sendMessage("A server called '$name' doesn't exist");
	}
	else if(empty($ip) || empty($port) || empty($password)){
		sendMessage("Invalid RCON format:  /rcon (Name) (IP) (Port) (Password)");
	}
	else{


		if (mysqli_num_rows($sql) > 0){
			sendMessage("A server called '$name' already exists!");
			$sql->close();
			$db->close();
			die();
		}

		sendMessage(sprintf("Connecting to %s:%s (%s) ...", $ip, $port, $name));

		$r = new CServerRcon($result['ip'], $result['port'], $result['password']);
		if (!$r->Auth()) {
			sendMessage("Wrong RCON password!");
			$sql->close();
			$db->close();
			die();
		}

		$sql = $db->prepare("INSERT INTO `telegram`(`chatID`, `name`, `ip`, `port`, `password`, `connected`) VALUES (?,?,?,?,?,?)");
		$sql->bind_param("ssssss", $chatID, $name, $ip, $port, $password, $connected);

		//echo $sql;
		if (!$sql->execute()){
			sendMessage("Error adding record: " . $db->error);
		}

		sendMessage("Connected successfully!");
	}
	$sql->close();
	$db->close();
}
else if (substr($message, 0, 5) === "/rcon" && strlen($message) == 5)  {
	sendMessage("Invalid RCON format:  /rcon (Name) (IP) (Port) (Password)");
}
else{
	sendMessage("Unknown command: `$message`, type /start to display the available commands!");
}

function sendMessage($text){
	global $chatID;
	$sendto = API_URL."sendmessage?chat_id=".$chatID."&text=".urlencode($text)."&parse_mode=markdown";

	file_get_contents($sendto);
}
?>