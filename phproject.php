<?php
if (PHP_SAPI != 'cli') {
	throw new Exception("Phproject CLI must be run from the command line.");
}
$home = defined('PHP_WINDOWS_VERSION_MAJOR') ? getenv('HOMEDRIVE') . getenv('HOMEPATH') : getenv("HOME");

// Read configuration or prompt for it
if(is_file($home . "/.phproj-cli.ini") && $config = parse_ini_file($home . "/.phproj-cli.ini")) {
	// Configuration loaded successfully
} else {
	$config = array();
	$config["url"] = prompt("Phproject installation URL:", "validate_url");
	if(substr($config["url"], -1) != "/") {
		$config["url"] .= "/";
	}
	$config["key"] = prompt("API Key:");
	$iniData = "url={$config['url']}" . PHP_EOL;
	$iniData .= "key={$config['key']}" . PHP_EOL;
	file_put_contents($home . "/.phproj-cli.ini", $iniData);
}

// Verify user
if($user = callApi("user/me.json")) {
	echo "Logged in as: {$user->name} ({$user->email})", PHP_EOL;
} else {
	exit("Login failed." . PHP_EOL);
}

// Display available commands
echo 'Type "help" to view available commands.', PHP_EOL;
while($str = prompt(PHP_EOL . $user->username . "@phproject> ", null, false)) {
	$params = explode(' ', $str);
	$cmd = strtolower(array_shift($params));
	$str = implode(' ', $params);
	switch($cmd) {
		case "help":
		case "h":
		case "?":
			echo "Available commands:", PHP_EOL;
			echo "me:        view your account information", PHP_EOL;
			echo "browse:    browse all issues", PHP_EOL;
			echo "dashboard: list issues assigned to you", PHP_EOL;
			echo "issue:     view a single issue", PHP_EOL;
			echo "new:       create a new issue", PHP_EOL;
			echo "quit:      exit Phproject CLI", PHP_EOL;
			break;
		case "quit":
		case "q":
		case "exit":
			break 2;
		case "me":
			echo "ID:       " . $user->id, PHP_EOL;
			echo "Name:     " . $user->name, PHP_EOL;
			echo "Username: " . $user->username, PHP_EOL;
			echo "Email:    " . $user->email, PHP_EOL;
			echo "API Key:  " . $config['key'], PHP_EOL;
			break;
		case "dashboard":
		case "d":
		case "~":
			$response = callApi("issues.json?owner_id={$user->id}&status_closed=0");
			foreach($response->issues as $issue) {
				echo "#{$issue->id} - {$issue->name}", PHP_EOL;
				if(!empty($issue->author->name)) {
					echo "  Author: {$issue->author->name}", PHP_EOL;
				}
				if(!empty($issue->sprint)) {
					echo "  Sprint: {$issue->sprint->name}", PHP_EOL;
				}
				if(!empty($issue->priority->value)) {
					echo "  Priority: {$issue->priority->name}", PHP_EOL;
				}
			}
			break;
		case "browse":
		case "b":
			echo "browse is not implemented yet.", PHP_EOL;
			break;
		case "issue":
		case "i":
			if(!empty($params[0]) && intval($params[0])) {
				$response = callApi("issues/" . intval($params[0]) . ".json");
				$issue = $response->issue;
				echo "{$issue->name} (#{$issue->id})", PHP_EOL;
				echo "Author:          {$issue->author->name}", PHP_EOL;
				echo "Created:         " . date("M j, Y \\a\\t g:ia", strtotime($issue->created_date)), PHP_EOL;
				echo "Type:            {$issue->tracker->name}", PHP_EOL;
				echo "Status:          {$issue->status->name}", PHP_EOL;
				echo "Assignee:        {$issue->owner->name}", PHP_EOL;
				echo "Planned Hours:   {$issue->hours_total}", PHP_EOL;
				echo "Remaining Hours: {$issue->hours_remaining}", PHP_EOL;
				if(trim($issue->description)) {
					echo PHP_EOL;
					echo "Description: {$issue->description}", PHP_EOL;
				}
			} else {
				echo "Usage: issue <id>", PHP_EOL;
			}
			break;
		case "new":
		case "n":
		case "create":
			echo 'Creating a new issue:', PHP_EOL;
			$name = prompt('Name: ', null, false);
			$owner = prompt('Assignee ID: ', null, false, true) ?: $user->id;
			$desc = promptMultiline('Enter a description, ending with a line containing only ".": ');
			$response = callApi('issues.json', 'POST', array('name' => $name, 'owner_id' => $owner, 'description' => $desc));
			if(!empty($response->error)) {
				echo "Error {$response->status} creating issue: {$response->error}", PHP_EOL;
			} else {
				echo "Issue created, ID {$response->issue->id}.", PHP_EOL;
			}
			break;
		default:
			echo "Command not found: $cmd", PHP_EOL;
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
function prompt($message, $validator = null, $appendLineBreak = true, $allowEmpty = false) {
	// Output prompt message
	echo $message;
	if($appendLineBreak) {
		echo PHP_EOL;
	}

	// Read user input
	$handle = fopen("php://stdin", "r");
	$line = rtrim(fgets($handle), "\r\n");

	// Check for empty value
	if(!trim($line)) {
		if($allowEmpty) {
			return null;
		} else {
			return prompt("Please enter a value:", $validator, true);
		}
	}

	// Validate and prompt again if validation fails
	if(is_callable($validator) && ($response = call_user_func($validator, $line)) !== true) {
		echo $response, PHP_EOL;
		return prompt($message, $validatorm, true, $allowEmpty);
	} else {
		return $line;
	}
}

/**
 * Prompt for a multiline text value
 * @param  string|bool $message
 * @return string
 */
function promptMultiline($message = null) {
	// Output prompt message
	if($message) {
		echo $message, PHP_EOL;
	} elseif($message === null) {
		echo 'Enter your message, ending it with a line containing ".":', PHP_EOL;
	}
	$lines = array();

	// Read user input until "\n."
	$handle = fopen("php://stdin", "r");
	while($line = rtrim(fgets($handle), "\r\n")) {
		if($line == '.') {
			break;
		} else {
			$lines[] = $line;
		}
	}

	return implode(PHP_EOL, $lines);
}

/**
 * Validate a URL
 * @param  string $val
 * @return string|TRUE
 */
function validate_url($val) {
	return filter_var($val, FILTER_VALIDATE_URL) ? true : "Enter a valid URL:";
}

/**
 * Validate an integer
 * @param  string $val
 * @return string|TRUE
 */
function validate_int($val) {
	return is_int($val) ? true : "Enter an integer value:";
}

/**
 * Validate a number
 * @param  string $val
 * @return string|TRUE
 */
function validate_number($val) {
	return is_numeric($val) ? true : "Enter an valid number:";
}

/**
 * Validate a date
 * @param  string $val
 * @return string|TRUE
 */
function validate_date($val) {
	return strtotime($val) !== false ? true : "Enter an valid date:";
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
		if(is_array($data)) {
			$data = http_build_query($data);
			$options['http']['header'] .= "\r\nContent-type: application/x-www-form-urlencoded";
		}
		$options['http']['content'] = $data;
	}
	$context = stream_context_create($options);

	$result = file_get_contents($config['url'] . $route, false, $context);
	return json_decode($result);
}
