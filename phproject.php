<?php
if (PHP_SAPI != 'cli') {
	throw new Exception("Phproject CLI must be run from the command line.");
}
$home = getenv("HOME");


// Read configuration or prompt for it
if(is_file($home . "/.phproj-cli.ini") && $config = parse_ini_file($home . "/.phproj-cli.ini")) {
	// Configuration loaded successfully
} else {
	$config = array();
	$config["url"] = prompt("Phproject installation URL:");
	if(substr($config["url"], -1, 0) != "/") {
		$config["url"] .= "/";
	}
	$config["key"] = prompt("API Key:");
	$iniData = "url={$config['url']}" . PHP_EOL;
	$iniData .= "key={$config['key']}" . PHP_EOL;
	file_put_contents($home . "/.phproj-cli.ini", $iniData);
}

// Verify user
if($user = callApi("user/me.json")) {
	echo "Logged in as: {$user->name} ({$user->email})" . PHP_EOL;
} else {
	exit("Login failed." . PHP_EOL);
}

// Display available commands
echo 'Type "help" to view available commands.' . PHP_EOL;
while(substr($str = prompt(PHP_EOL . $user->username . "@phproject> ", null, false), 0, 1) != "q") {
	$params = explode(' ', $str);
	$cmd = strtolower(array_shift($params));
	$str = implode(' ', $params);
	switch($cmd) {
		case "help":
		case "h":
		case "?":
			echo "Available commands:" . PHP_EOL;
			echo "me:        view your account information" . PHP_EOL;
			echo "browse:    browse all issues" . PHP_EOL;
			echo "dashboard: list issues assigned to you" . PHP_EOL;
			echo "issue:     view a single issue" . PHP_EOL;
			break;
		case "me":
			echo "ID:       " . $user->id . PHP_EOL;
			echo "Name:     " . $user->name . PHP_EOL;
			echo "Username: " . $user->username . PHP_EOL;
			echo "Email:    " . $user->email . PHP_EOL;
			echo "API Key:  " . $config['key'] . PHP_EOL;
			break;
		case "dashboard":
		case "d":
		case "~":
			$response = callApi("issues.json?owner_id={$user->id}&status_closed=0");
			foreach($response->issues as $issue) {
				echo "#{$issue->id} - {$issue->name}" . PHP_EOL;
				echo "  Created by {$issue->author->name}" . PHP_EOL;
				if(isset($issue->sprint)) {
					echo "  Sprint: {$issue->sprint->name}";
				}
				echo "  Priority: {$issue->priority->name}" . PHP_EOL;
			}
			break;
		case "browse":
		case "b":
			echo "browse is not implemented yet." . PHP_EOL;
			break;
		case "issue":
		case "i":
			if(!empty($params[0]) && intval($params[0])) {
				$issue = callApi("issues/" . intval($params[0]) . ".json");
				print_r($issue);
			} else {
				echo "Usage: issue <id>" . PHP_EOL;
			}
			break;
		default:
			echo "Command not found: $cmd" . PHP_EOL;
	}
}

// Display quit message
exit("Bye!" . PHP_EOL);

////////////////////////////////////////////////////
/// Internal functions for prompts and validaton ///
////////////////////////////////////////////////////

/**
 * Prompt a user to enter a value
 * @param  string   $message
 * @param  callable $validator
 * @return string
 */
function prompt($message, $validator = null, $appendLineBreak = true) {
	// Output prompt message
	echo $message;
	if($appendLineBreak) {
		echo PHP_EOL;
	}

	// Take user input
	$handle = fopen("php://stdin", "r");
	$line = rtrim(fgets($handle), "\r\n");

	// Validate and prompt again if validation fails
	if(is_callable($validator) && ($response = call_user_func($validator, $line)) !== true) {
		echo $response . PHP_EOL;
		return prompt($message, $validator);
	} else {
		return $line;
	}
}

/**
 * Validate a URL
 * @param  string $url
 * @return string|TRUE
 */
function validate_url($url) {
	return filter_var($url, FILTER_VALIDATE_URL) ? true : "Enter a valid URL:";
}

/**
 * Call API
 * @param  string $route
 * @return mixed
 */
function callApi($route, $method = 'GET', $data = null) {
	global $config;

	$options = array('http' => array('method' => $method, 'header' => "Accept: application/json\r\nX-API-Key: " . $config['key']));
	if($data !== null) {
		$options['http']['content'] = $data;
	}
	$context = stream_context_create($options);

	$result = file_get_contents($config['url'] . $route, false, $context);
	return json_decode($result);
}
