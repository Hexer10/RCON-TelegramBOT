
<?php
require("config.php");
require_once 'SourceQuery/bootstrap.php';

use xPaw\SourceQuery\SourceQuery;


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

$sql = "SELECT * FROM `telegram` WHERE `chatID`= $chatID";
$sql = $db->query($sql);

if ($sql->num_rows > 0){
	$result = mysqli_fetch_array($sql);
	if (!empty($result['connected'])){
		if (substr($message, 0, 11) === "/disconnect" && strlen($message) == 11){
			$sql = "UPDATE `telegram` SET `connected` = '0' WHERE `chatID` = $chatID";
			$sql = $db->query($sql);

			if (!$sql){
				sendMessage("Failed to disconnect: " .$db->error);
				die();
			}

				sendMessage(sprintf("Disconnected from: %s (%s:%s)", $result['name'], $result['ip'], $result['port']));
				die();
		}

		sendMessage("Sending command: $message");
        try{
            $rcon = new SourceQuery();

            $rcon->Connect($result['ip'], $result['port']);

            $rcon->SetRconPassword($result['password']);

            sendMessage($rcon->Rcon(sprintf("%s", $message))); //Fix injections?
        }
        catch(Exception $e) {
            sendMessage($e->getMessage());
        }
        finally {
            $rcon->Disconnect();
        }
		die();
	}
}

if ((substr($message, 0, 10) === "/start" && strlen($message) ==  6) || (substr($message, 0, 5) === "/help" && strlen($message) ==  5) || $message[0] != "/") {
	sendMessage("Hi! \n\n This bot allows you to connect using RCON to any source server.\n\n
			<b>Available commands</b>: \n<b>1.</b> /rcon  (ServerName) (IP) (Port) (Password) --> `Adds a server to the database and connects to it.`
					  \n<b>2.</b> /rcon	<code>(ServerName)</code> --> Connects to an added server.
					  \n<b>3.</b> /serverlist --> Displays added servers.
					  \n<b>4.</b> /delserver <code>(ServerName)</code> --> Removes a server.
					  \n<b>5.</b> /disconnect --> Disconnects from a server.
					  \n<code>Every command that it's sent from after connecting is sent to the Server's RCON. DON'T type /kick but only kick(or sm_kick)\nThe session never expires.
					  \nSpaces in the ServerName are not allowed!</code>
					  \n\n<code>Type again</code> /start <code>to display this message</code>");
	die();
}

if (substr($message, 0, 10) === "/delserver" && $message[10] == " ")  {
	$message = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $message)));
	$explode = explode(" ", $message, 2);

	$strArr = $explode;

	$name = $strArr[1];

	$sql = "DELETE FROM `telegram` WHERE `chatID` = '$chatID' AND `name` = '$name'";

	$sql = $db->query($sql);

	if ($db->affected_rows == 0){
	    sendMessage("Unable to find a server called <code>".$name."</code>");
    }
	elseif (!$sql){
		sendMessage("Query failed: " .$db->error);
	}
	else
	    sendMessage("Deleted $name");

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
		if ($sql->num_rows > 0){
			$result = $sql->fetch_array();

			sendMessage("Connecting to $name ". $result['ip'] . ":" .$result['port']. " ...");

            try{
                $rcon = new SourceQuery();
                $rcon->Connect($result['ip'], $result['port']);

                $rcon->SetRconPassword($result['password']);

                $sql = "UPDATE `telegram` SET `connected` = '1' WHERE `chatID`= '$chatID' AND `name` = '$name'";
                $sql = $db->query($sql);

                sendMessage("Connected!");
            }
            catch(Exception $e) {
                sendMessage($e->getMessage());
            }
            finally {
                $rcon->Disconnect();
            }

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
		if ($sql->num_rows > 0){
			sendMessage("A server called '$name' already exists!");
			$sql->close();
			$db->close();
		}
		else {
            sendMessage(sprintf("Connecting to %s:%s (%s) ...", $ip, $port, $name));

            try {
                $rcon = new SourceQuery();
                $rcon->Connect($ip, $port);

                $rcon->SetRconPassword($password);

                $sql = $db->prepare("INSERT INTO `telegram`(`chatID`, `name`, `ip`, `port`, `password`, `connected`) VALUES (?,?,?,?,?,?)");
                $sql->bind_param("ssssss", $chatID, $name, $ip, $port, $password, $connected);

                $sql->execute();
                sendMessage("Connected successfully!");
            } catch (Exception $e) {
                sendMessage($e->getMessage());
            } finally {
                $rcon->Disconnect();
            }
        }
	}
	$sql->close();
	$db->close();
}
else if (substr($message, 0, 5) === "/rcon" && strlen($message) == 5)  {
	sendMessage("Invalid RCON format:  /rcon (Name) (IP) (Port) (Password)");
}
else{
	sendMessage("Unknown command: <code>$message</code>, type /start to display the available commands!");
}

function sendMessage($text){
	global $chatID;
	$sendto = API_URL."sendmessage?chat_id=".$chatID."&text=".urlencode($text)."&parse_mode=html";

	file_get_contents($sendto);
}