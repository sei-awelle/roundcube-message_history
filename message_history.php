<?php

require_once __DIR__ . '/vendor/autoload.php';

use Xabbuh\XApi\Client\XApiClientBuilder;
use Xabbuh\XApi\Model\Agent;
use Xabbuh\XApi\Model\StatementFactory;
use Xabbuh\XApi\Model\InverseFunctionalIdentifier;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Model\Verb;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\LanguageMap;
use Xabbuh\XApi\Model\Context;
use Xabbuh\XApi\Model\ContextActivities;
use Xabbuh\XApi\Model\Definition;
use Xabbuh\XApi\Model\IRL;
use Xabbuh\XApi\Model\Account;
use Xabbuh\XApi\Model\Group;

class message_history extends rcube_plugin
{
	public $rc;
	private $rcube;
	private $xApiClient;
	private $context;
	private $exercise_name;
	private $exercise_id;
	private $message_id;
	private $actor;
	private $team;
	private $user_email;
	private $user_name;
	private $sub;
	private $iss;
	private $domain;

	// Initialization function
	// Define hooks to be used and add history button for permitted users
	public function init()
	{
		$rcmail = rcmail::get_instance();
		rcube::console("message_history: init task=" . $rcmail->task . " action=" . $rcmail->action);


		// Exit tasks and actions that do not require loggin or addition of button
		if (($rcmail->action == 'getunread') || ($rcmail->action == 'list') || ($rcmail->action == 'pagenav') || ($rcmail->action == 'autocomplete')) {
			rcube::console("message_history: exiting for action=" . $rcmail->action);
			return;
		}


		$this->rc = &$rcmail;
		$this->add_texts('localization/', true);
		$this->load_config();
		$this->rcube = rcube::get_instance();
		$this->config = $this->rcube->config->get('message_history');
		$this->domain = $this->rcube->config->get('username_domain');
		$db = rcmail::get_instance()->get_dbh();

		$this->user_email = $rcmail->user->get_username();

		if (($rcmail->task != 'logout') && ($rcmail->task != 'login')) {
			// Setup for adding button
			$result = $db->query("SELECT email FROM contacts INNER JOIN contactgroupmembers ON contacts.contact_id = contactgroupmembers.contact_id INNER JOIN contactgroups ON contactgroups.contactgroup_id = contactgroupmembers.contactgroup_id AND contactgroups.name = '" . $this->config['group'] . "' WHERE email = '" . $this->user_email . "'");

			// if user has the required permissions, the user will be able
			// to view the message history table
			if ($result->rowCount() > 0) {
				$this->register_action('plugin.message_history', array($this, 'message_history_init'));
				$this->register_task('message_history');
				$skin_path = $this->local_skin_path();
				$this->include_stylesheet("$skin_path/message_history.css");
				$this->add_button(
				[
					'type' => 'link',
					'label' => 'button_name',
					'href' => '/?_task=message_history&_action=plugin.message_history',
					'class' => 'logs',
					'classact' => 'button logs',
					'title' => 'button_name',
					'domain' => $this->ID,
					'innerclass' => 'inner',
				],
				'taskbar');
			}
			if ($rcmail->task == 'addressbook' || $rcmail->task == 'settings' || $rcmail->task == 'message_history') {
				rcube::console("message_history: exiting for task=" . $rcmail->task);
				return;
			}
		}





		// Setup for xAPI information
		$this->set_user();

		// Set hooks for login
		if ($rcmail->task == 'login') {
			// Hook to log xapi when a user logins
			$this->add_hook('login_after', [$this, 'log_login']);
			return;
		}

		// Set hook for logout
		if ($rcmail->task == 'logout') {
			// Hook to log xapi when user logs out
			$this->add_hook('logout_after', [$this, 'log_logout']);
		}

		// Hook to log xapi when user tokken is refreshed
		$this->add_hook('oauth_refresh_token', [$this, 'log_refresh']);

		// Hook to log xapi when a user refreshes
		//if (($rcmail->action == 'refresh') || ($rcmail->action == 'check-recent')) {
		if ($rcmail->action == 'check-recent') {
			$this->add_hook('refresh', [$this, 'log_check']);
			return;
		}


		// Hooks for creating and viewing messages
		if ($rcmail->action == 'compose') {
			// Hook to add exercise selection dropdown to compose page
			$this->add_hook('render_page', array($this, 'add_dropdown_to_compose'));
			return;
		} else if (($rcmail->action == 'preview') || ($rcmail->action == 'show')) {
			// Hook to display exercise name when previewing and viewing message
			$this->add_hook('render_page', array($this, 'add_exercise_to_display'));
			$this->add_hook('message_load', array($this, 'message_load_handler'));
			// Hook to mark message as read in database and to sent xapi
			$this->add_hook('message_read', array($this, 'log_read_message'));
			return;
		}

		// Hooks for sending email
		if ($rcmail->action == 'send') {
			// Hook to Add Exercise Selection to Headers and to log sent
			// message to the roundcube message history table
			$this->add_hook('message_ready', array($this, 'log_sent_message'));

			// Hook to log xapi when a message is sent
			$this->add_hook('message_sent', [$this, 'xapilog_sent_message']);
		}
	}

	// Function to register plugin handler and to set the page title for the
	// Roundcube message history table
	public function message_history_init()
	{
		$this->register_handler('plugin.body', array($this, 'logs_form'));
		$this->rc->output->set_pagetitle($this->gettext('plugin_name'));
		$this->rc->output->send('plugin');

	}

	// Function to create a new roundcube section and to create a html table
	// for message history
	public function logs_form()
	{
		//Adding the js script and css file
		$rcmail = rcmail::get_instance();
		$this->include_script('message_history.js');
		$skin_path = $this->local_skin_path();
		$db = rcmail::get_instance()->get_dbh();

		// Get required information from the message_history_v2 database
		//  table
		$result = $db->query("SELECT from_user_name, to_user_name, subject, exercise, time_sent, read_status, roundcube_message_id FROM message_history_v2 ORDER BY time_sent DESC");
		if ($db->is_error($result)) {
                        rcube::raise_error([
                                'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
                                'message' => "message_history: failed to pull records from database."
                                ], true, false);
                } else {
			$records = $result->fetchAll();
		}

		// Create a new html table with specifried id and class
		$table = new html_table(['id' => 'message_history_v2', 'class' => 'message_history_table']);
		// Adding table headers
		$table->add_header('exercise', 'Exercise Name');
		$table->add_header('timestamp','Time Sent');
		$table->add_header('subject','Subject');
		$table->add_header('sender','From');
		$table->add_header('recipient','To');
		$table->add_header('status', 'Read Status');
		$table->add_header('message_id', 'Message ID');

		// Adding information from the database table to the html table
		// based on the added header
		foreach ($records as $record) {
			$to_users = explode(',', $record['to_user_name']);
			$rowspan = 1;
			$table->add(['class' => 'exercise', 'rowspan' => $rowspan], $record['exercise']);
			$table->add(['class' => 'timestamp', 'rowspan' => $rowspan], $record['time_sent']);
			$table->add(['class' => 'subject', 'rowspan' => $rowspan], $record['subject']);
			$table->add(['class' => 'sender' , 'rowspan' => $rowspan], $record['from_user_name']);
			$table->add(['class' => 'recipient', 'rowspan' => $rowspan], htmlspecialchars($record['to_user_name']));
			$read_status = $record['read_status'] == 't' ? 'Read' : 'Unread';
			$table->add(['class' => 'status', 'rowspan' => $rowspan], $read_status);
			$table->add(['class' => 'message_id', 'rowspan' => $rowspan], $record['roundcube_message_id']);
			$table->add_row();
		}

		// Add additional information to the message history page
		$output = html::br();
		$output .= html::p('p', "This table will provide analytical information of emails being sent by providing information such as the sender, recipient, subject of the email, and the time it was sent.");
		// Adding a search input field to the message history page
		$search = new html_inputfield(['type' => 'text', 'name' => '_search', 'placeholder' => 'Search', 'id' => 'table_search', 'class' => 'search']);
		$output .= $search->show();
		$output .= html::br();
		$output .= html::div('table-wrapper', $table->show());
		return $output;
	}

	// Function to add an exercise selection dropdown to the compose page
	// with the views the user is part of
	public function message_load_handler($args)
	{
		rcube::console("message_history: message_load_handler");

		$message = $args['object'];
		$rcmail = rcmail::get_instance();
		$raw_headers = $rcmail->storage->get_raw_headers($message->uid);
		$headers = rcube_mime::parse_headers($raw_headers);
		// handle if exercise does not exist
		if (isset($headers['exercise'])) {
			$this->exercise_name = $headers['exercise'];
		}
	}

	// Function to dispaly exercise header on message previer page
	public function add_exercise_to_display($args)
	{
		rcube::console("message_history: add_exercise_to_display");

		if (($args['template'] == 'message') && ($this->exercise_name != NULL)) {
			$doc = new DOMDocument();
			$doc->loadHTML($args['content']);
			$xpath = new DOMXPath($doc);
			$header_div = $xpath->query('//div[contains(@class, "header-links")]');
			$targetDiv = $header_div->item(0);

			// Create Label
			$label = $doc->createElement('p');
			$label->setAttribute('id', 'exercise-selection');
			$labelText = $doc->createTextNode("Exercise: " . $this->exercise_name);
			$label->appendChild($labelText);

			$targetDiv->appendChild($label);
			$args['content'] = $doc->saveHTML();
		}
		return $args;
	}

	public function add_dropdown_to_compose($args)
	{
		rcube::console("message_history: add_dropdown_to_compose");

		// Information to make an api call to obtain the views the
		// current user is part of
		$url = $this->config['player_api_url'] . '/me/views';
		$headers = array(
			'Cache-Control: no-cache, no-store',
			'Pragma: no-cache',
			'Accept: text/plain',
			'Authorization: ' . $this->rc->decrypt($_SESSION['password'])
		);
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($curl);
		$decoded = json_decode($response, true);

		curl_close($curl);


		// if the current template shown is the compose template, a
		// dropdown will be added with the available user views
		if ($args['template'] == 'compose') {
			$doc = new DOMDocument('1.0', RCUBE_CHARSET);
			@$doc->loadHTML($args['content']);
			$subject_div = $doc->getElementById('compose_subject');

			//Create Parent div
			$parent_div = $doc->createElement('div');
			$parent_div->setAttribute('id', 'compose_exercise');
			$parent_div->setAttribute('class', 'form-group row');

			//Create Label
			$label = $doc->createElement('label');
			$label->setAttribute('for', 'compose_exercise');
			$label->setAttribute('class', 'col-2 col-form-label');
			$labelText = $doc->createTextNode('Exercise');
			$label->appendChild($labelText);
			$parent_div->appendChild($label);

			//Add Child div
			$child_div = $doc->createElement('div');
			$child_div->setAttribute('class', 'col-10');
			$parent_div->appendChild($child_div);

			//Add Dropdown
			$select_element = $doc->createElement('select');
			$select_element->setAttribute('name', '_exercise');
			$select_element->setAttribute('id', 'compose-exercise');
			$select_element->setAttribute('tabindex', '2');
			$select_element->setAttribute('class', 'form-control');
			if (is_array($decoded)) {
				foreach ($decoded as $view) {
					$option_element = $doc->createElement('option', $view['name']);
					$option_element->setAttribute('value', $view['id']);
	   	 			$select_element->appendChild($option_element);
				}
			}

			$child_div->appendChild($select_element);
			$subject_div->parentNode->insertBefore($parent_div, $subject_div->nextSibling);
			$args['content'] = $doc->saveHTML();
		}


		if ($args['template'] == 'message') {
			if ($this->exercise_name != NULL) {
				$doc = new DOMDocument('1.0', RCUBE_CHARSET);
				@$doc->loadHTML($args['content']);
				$xpath = new DOMXPath($doc);
				$header_div = $xpath->query('//div[contains(@class, "header-links")]');
				$targetDiv = $header_div->item(0);

				// Create Label
				$label = $doc->createElement('p');
				$label->setAttribute('id', 'exercise-selection');
				$labelText = $doc->createTextNode("Exercise: " . $this->exercise_name);
				$label->appendChild($labelText);

				$targetDiv->appendChild($label);
				$args['content'] = $doc->saveHTML();

			}
		}

		return $args;
	}

	// Function to add the Exercise header to the message and to log the new
	// message record if it was sent through Roundcube
	public function log_sent_message($args)
	{
		rcube::console("message_history: log_sent_message");

		// Add Exercise Selection to Headers
		if (isset($_POST['_exercise'])) {
			rcube::console("message_history: exercise id: " . $_POST['_exercise']);

			$this->exercise_id = $_POST['_exercise'];
			$url = $this->config['player_api_url'] . '/views/' . $this->exercise_id;
			$headers = array(
				'Cache-Control: no-cache, no-store',
				'Pragma: no-cache',
				'Accept: text/plain',
				'Authorization: ' . $this->rc->decrypt($_SESSION['password'])
			);
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

			$response = curl_exec($curl);
			$decoded = json_decode($response, true);

			curl_close($curl);

			$this->exercise_name = $decoded['name'];
			$args['message']->headers(array('Exercise' => $this->exercise_name, true));
			$args['message']->headers(array('Exercise-ID' => $this->exercise_id, true));

			rcube::console("message_history: exercise name: " . $this->exercise_name);
		}
		$db = rcmail::get_instance()->get_dbh();

		// Obtain the message information from the message headers
		$headers = $args['message']->headers();
		$subject = $headers['Subject'];
		$from_orig = $headers['From'];
		$to_orig = $headers['To'];
		$time_sent = $headers['Date'];
		preg_match('/<(.+?)@.+>/', $headers['Message-ID'], $matches_id);
		$this->message_id = $matches_id[1];
		// get just the to emails
		preg_match_all('/<(.+?)>/', $to_orig, $matches);
		//$to_emails = implode(',', $matches);
		$to_emails = $matches[1];
		// TODO should that be just the first one??

		// get just the to names
		$to_names = preg_replace('/<(.+?)>/', '', $to_orig);
		$to_names = preg_replace('/ , /', ',', $to_names);
		$to_names = trim($to_names);


		// convert from name to email address
		$result = $db->query("SELECT name FROM contacts WHERE email = '$from_orig'");
		if ($db->is_error($result)) {
			rcube::raise_error([
				'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
				'message' => "message_history: failed to pull name from database."
				], true, false);
		}
		$records = $db->fetch_assoc($result);
		$from_name = $records['name'];

		$table = 'message_history_v2';

		// convert to email addresses to names
		$to_names = array();
		foreach ($to_emails as $to_email) {
			$result = $db->query("SELECT name FROM contacts WHERE email = '$to_email'");
   			if ($db->is_error($result)) {
				rcube::raise_error([
					'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
					'message' => "message_history: failed to pull name from database."
					], true, false);
				}
				$records = $db->fetch_assoc($result);
				$to_names[] = $records['name'];
		}

		// Insert the new message record into the database
		// message_history_v2 table
		foreach ($to_names as $to_name) {
			$result = $db->query("INSERT INTO $table (from_user_name, to_user_name,
				subject, time_sent, modified, read_status, roundcube_message_id, exercise) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
				$from_name, $to_name, $subject, $time_sent, $db->now(), 'FALSE', $this->message_id, $this->exercise_name);
		}

		// if the message couldn't be inserted into the database table,
		// error out
		if ($db->is_error($result)) {
			rcube::raise_error([
				'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
				'message' => "message_history_v2: failed to insert record into database."
				], true, false);
		}
		return $args;
	}

	// Function to add a new record if the message was sent through
	// Stackstorm and to mark a message as read
	public function log_read_message($args)
	{
		rcube::console("message_history: log_read_message");

		$db = rcmail::get_instance()->get_dbh();
		$rcmail = rcmail::get_instance();

		// Get the required information from the message headers
		$message = $args['message'];
		preg_match('/<(.+?)@.+>/', $message->get_header('Message-ID'), $matches);
		$this->message_id = $matches[1];

		// Get To User Value
		$to_header = $message->get_header('to');
		$to_names = preg_replace('/<(.+?)>/', '', $to_header);
		$to_names = preg_replace('/ , /', ',', $to_names);
		$to_names = trim($to_names);
		$to_names_array = explode(',', $to_names);

		// Get From User name
		$from_email = $message->get_header('from');
		$from_name = $from_email;
		$result = $db->query("SELECT name FROM contacts WHERE email = '$from_email'");
		if ($db->is_error($result)) {
			rcube::raise_error([
				'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
				'message' => "message_history: failed to pull name from database."
				], true, false);
		}
		$records = $db->fetch_assoc($result);
		// verify one record
		if (!$records || (count($records) != 1)) {
			rcube::console("message_history: cannot find single record for " . $from_email);
		} else {
			$from_name = $records['name'];
		}

		// Get Execise name from header
		$raw_headers = $rcmail->storage->get_raw_headers($message->uid);
		$headers = rcube_mime::parse_headers($raw_headers);
		if (isset($headers['exercise'])) {
			$this->exercise_name = $headers['exercise'];

			rcube::console("message_history: exercise name: " . $this->exercise_name);
		} else if (isset($headers['exercise-id'])) {
			$this->exercise_id = $headers['exercise-id'];
			rcube::console("message_history: exercise-id: " . $this->exercise_id);

			$url = $this->config['player_api_url'] . '/views/' . $this->exercise_id;
			$headers = array(
				'Cache-Control: no-cache, no-store',
				'Pragma: no-cache',
				'Accept: text/plain',
				'Authorization: ' . $this->rc->decrypt($_SESSION['password'])
			);
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

			$response = curl_exec($curl);
			$decoded = json_decode($response, true);

			curl_close($curl);

			$this->exercise_name = $decoded['name'];

			rcube::console("message_history: exercise name: " . $this->exercise_name);
		}
	
		// Get Subject Value
		$subject = $message->get_header('subject');
		$parsed_subject = substr($subject, strpos($subject, "]") + 2);

		// Get Date Value
		$date_str = $message->get_header('Date');
		$timestamp = date('Y-m-d H:i:s', strtotime($date_str)) . '+00';
		$table = 'message_history_v2';

		// add the new message record when the user reads the message,
		// if it was sent through stackstorm
		foreach ($to_names_array as $to_name) {
			$to_name = trim($to_name);
			// first check if the email record exists (if it was
			// sent through Roundcube)
			$result = $db->query("SELECT * FROM $table WHERE roundcube_message_id = '" . $this->message_id . "' AND to_user_name = '$to_name'");
			if ($db->is_error($result)) {
				rcube::raise_error([
					'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
					'message' => "message_history: failed to pull existing record from database."
					], true, false);
			}
			$records = $db->fetch_assoc($result);
			// if the record doesn't exist, insert the new record
			// into the database table
			if ($result->rowCount() > 1) {
				rcube::console("message_history: multiple records exist for message from $from_name to $to_name with message_id" . $this->message_id);
			} else if ($result->rowCount() == 0) {
				rcube::console("message_history: inserting new record");
				$new_record = $db->query("INSERT INTO $table (from_user_name, to_user_name,
					subject, time_sent, modified, read_status, roundcube_message_id, exercise) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
					$from_name, $to_name, $parsed_subject, $timestamp, $db->now(), ($to_name === $this->user_name ? 'TRUE' : 'FALSE'),
					$this->message_id, $this->exercise_name);
				if ($db->is_error($result)) {
					rcube::raise_error([
						'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
						'message' => "message_history: failed to insert record."
		   				], true, false);
				}
			} else if (($to_name === $this->user_name) && ($records['read_status'] == FALSE)) {
				// check if the current recipient matches the logged in user
				rcube::console("message_history: updating record");
				// update is_read column to 1 for this message
				$result = $db->query("UPDATE $table SET read_status = TRUE WHERE roundcube_message_id = '" . $this->message_id . "' AND to_user_name = '$to_name'");
				if ($db->is_error($result)) {
					rcube::raise_error([
						'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
						'message' => "message_history: failed to mark message as read."
		   				], true, false);
				}
			}

		}

		// build xapi client
		$this->build_client();
		$statementsApiClient = $this->xApiClient->getStatementsApiClient();
		$sf = new StatementFactory();

		// set actor
		$this->set_actor($sf);

		// set verb
		$verb_id = 'https://w3id.org/xapi/dod-isd/verbs/read';
		$this->set_verb($verb_id, $sf);

		// set object
		$type = 'http://id.tincanapi.com/activitytype/email';
		$this->set_object($type, $sf);

		// with context
		$this->build_context();
		$sf->withContext($this->context);

		// create and store statement
		$statement = $sf->createStatement();

		// Send statement
		$this->send_statement($statement, $statementsApiClient);

		return $args;
	}

	// Function to build xapi client
	private function build_client()
	{
		// build xapi client
		$builder = new XApiClientBuilder();
		$this->xApiClient = $builder->setBaseUrl($this->config['lrs_endpoint'])
			->setVersion('1.0.0')
			->setAuth($this->config['lrs_username'], $this->config['lrs_password'])
			->build();
	}

	// Function to build xapi context
	private function build_context() {

		rcube::console("message_history: build_context");

		$context = new Context();
		$platformContext = $context->withPlatform($_SERVER['SERVER_NAME']);
		$languageContext = $platformContext->withLanguage('en-US');

		// determine group email domain from config
		$team_email = $this->team . "@" . $this->domain;
		$members = array();
		array_push($members, $this->actor);
		$group = new Group(InverseFunctionalIdentifier::withMbox(IRI::fromString("mailto:$team_email")), $this->team, $members);
		$groupContext = $languageContext->withTeam($group);

		$activities = new ContextActivities();

		// configure parent
		if ($this->exercise_name) {
			$type = IRI::fromString('http://adlnet.gov/expapi/activities/simulation');
			$moreInfo = IRL::fromString($this->config['player_ui_url'] . '/view/' . $this->exercise_id);
			$languageMap = new LanguageMap();
			$mapName = $languageMap->withEntry('en-US', "View");
			$mapDesc = $languageMap->withEntry('en-US', $this->exercise_name);
			$definition = new Definition($mapName, $mapDesc, $type, $moreInfo);
			$id = IRI::fromString($this->config['player_api_url'] . '/views/' . $this->exercise_id);
			$parent = new Activity($id, $definition);
			$activities = $activities->withAddedParentActivity($parent);

			$finalContext = $groupContext->withContextActivities($activities);
		} else {
			$finalContext = $groupContext;
		}

		$this->context = $finalContext;
	}

	//Function to set xapi actor
	private function set_actor($sf)
	{
		rcube::console("message_history: set_actor");

		$mbox = IRI::fromString("mailto:" . $this->user_email);
		$account = new Account($this->sub, IRL::fromString($this->iss));
		$ifi = InverseFunctionalIdentifier::withAccount($account);
		$agent = new Agent($ifi, $this->user_name);
		$this->actor = $agent;
		$sf->withActor($agent);
		return $sf;
	}

	// Function to set xapi verb
	private function set_verb($path, $sf)
	{
		rcube::console("message_historyu: set_verb");

		$languageMap = new LanguageMap();
		$map = $languageMap->withEntry("en-US", basename($path));
		$verb = new Verb(IRI::fromString($path), $map);
		$sf->withVerb($verb);
		return $sf;
	}

	// Function to set xapi object
	private function set_object($path, $sf)
	{
		rcube::console("message_history: set_object");

		$languageMap = new LanguageMap();

		// default to configure for system
		$moreInfo = IRL::fromString('https://' . $_SERVER['SERVER_NAME'] . '?_task=message_history&_action=plugin.message_history&search=' . urlencode($this->user_name));
		$mapName = $languageMap->withEntry('en-US', basename($path));
		$mapDesc = $languageMap->withEntry('en-US', 'Email messaging system used during the exercise event');
		$type = IRI::fromString($path);
		$id = IRI::fromString('https://' . $_SERVER['SERVER_NAME']);

		// configure for email
		if ($this->message_id) {
			$moreInfo = IRL::fromString('https://' . $_SERVER['SERVER_NAME'] . '?_task=message_history&_action=plugin.message_history&search=' . $this->message_id);
			$mapName = $languageMap->withEntry('en-US', basename($path));
			$mapDesc = $languageMap->withEntry('en-US', 'An email message sent or read during the exercise event');
			$type = IRI::fromString($path);
			$id = IRI::fromString('https://' . $_SERVER['SERVER_NAME'] . '/message/' . $this->message_id);
		}
		$definition = new Definition($mapName, $mapDesc, $type, $moreInfo);
		$activity = new Activity($id, $definition);
		$sf->withObject($activity);
		return $sf;
	}

	// Function send xapi statement
	private function send_statement($statement, $statementsApiClient)
	{
		try {
			$statementsApiClient->storeStatement($statement);
		} catch (Exception $e) {
			$this->xapi_error($e);
		}
	}

	// TODO see if this can fit into the original log_sent_message function
	// Function to log xapi when a message is sent
	public function xapilog_sent_message($args)
	{
		rcube::console("message_history: xapilog_sent_message");

		$db = rcmail::get_instance()->get_dbh();

		$headers = $args['message']->headers();
		$subject = $headers['Subject'];
		$from_orig = $headers['From'];
		$to_orig = $headers['To'];
		//preg_match('/<(.+?)@.+>/', $headers['Message-ID'], $matches);
		//$this->message_id = $matches[1];

		// get just the to emails
		preg_match_all('/<(.+?)>/', $to_orig, $matches);
		$to_emails = $matches[1];

		// get just the to names
		$to_names = preg_replace('/<(.+?)>/', '', $to_orig);
		$to_names = preg_replace('/ , /', ',', $to_names);
		$to_names = trim($to_names);

		// convert to email addresses to names
		$to_names = array();
		foreach ($to_emails as $to_email) {
			$result = $db->query("SELECT name FROM contacts WHERE email = '$to_email'");
			if ($db->is_error($result)) {
				rcube::raise_error([
					'code' => 605, 'line' => __LINE__,
					'file' => __FILE__,
					'message' => "xapi: failed to pull name from database."
					], true, false);
			}
			$records = $db->fetch_assoc($result);
			$to_names[] = $records['name'];
		}

		// build xapi client
		$this->build_client();
		$statementsApiClient = $this->xApiClient->getStatementsApiClient();
		$sf = new StatementFactory();

		// set actor
		$this->set_actor($sf);

		// set verb
		$verb_id = 'https://w3id.org/xapi/dod-isd/verbs/sent';
		$this->set_verb($verb_id, $sf);

		// set object
		$type = 'http://id.tincanapi.com/activitytype/email';
		$this->set_object($type, $sf);

		// with context
		$this->build_context();
		$sf->withContext($this->context);

		$statement = $sf->createStatement();

		// Send statement
		$this->send_statement($statement, $statementsApiClient);

		// store a single Statement
		return $args;
	}


	// Function to log xapi when a user refreshes
	public function log_check($args)
	{
		rcube::console("message_history: log_check");

		// Build xapi client
		$this->build_client();
		$statementsApiClient = $this->xApiClient->getStatementsApiClient();

		// Build statement
		$sf = new StatementFactory();

		// Set actor
		$this->set_actor($sf);

		// Set verb
		$verb_id = 'http://id.tincanapi.com/verb/previewed';
		$this->set_verb($verb_id, $sf);

		// Set object
		$type = 'http://id.tincanapi.com/activitytype/resource';

		// Set context
		$this->build_context();
		$sf->withContext($this->context);

		//$action = 'A user refreshed during the exercise event';
		$this->set_object($type, $sf);
		$statement = $sf->createStatement();

		// Send statement
		$this->send_statement($statement, $statementsApiClient);

		return $args;
	}

	// Function to log xapi when a user login gets checked
	public function log_refresh($args)
	{
		rcube::console("message_history: log_refresh");

		if ($this->user_email == NULL) {
			$this->set_user();
		}

		// Build xapi client
		$this->build_client();
		$statementsApiClient = $this->xApiClient->getStatementsApiClient();

		// Build statement
		$sf = new StatementFactory();

		// Set actor
		$this->set_actor($sf);

		// Set verb
		$verb_id = 'https://w3id.org/xapi/adl/verbs/logged-in';
		$this->set_verb($verb_id, $sf);

		// Set object
		$type = 'http://id.tincanapi.com/activitytype/resource';
		$this->set_object($type, $sf);

		// Set context
		$this->build_context();
		$sf->withContext($this->context);

		// Create statement
		$statement = $sf->createStatement();

		// Send statement
		$this->send_statement($statement, $statementsApiClient);

		return $args;
	}



	// Function to log xapi when a user logins
	public function log_login($args)
	{
		rcube::console("message_history: log_login");

		if ($this->user_email == NULL) {
			$this->set_user();
		}

		// Build xapi client
		$this->build_client();
		$statementsApiClient = $this->xApiClient->getStatementsApiClient();

		// Build statement
		$sf = new StatementFactory();

		// Set actor
		$this->set_actor($sf);

		// Set verb
		$verb_id = 'https://w3id.org/xapi/adl/verbs/logged-in';
		$this->set_verb($verb_id, $sf);

		// Set object
		$type = 'http://id.tincanapi.com/activitytype/resource';
		//$moreInfo = IRL::fromString('https://' . $_SERVER['SERVER_NAME'] . "?_task=message_history&_action=plugin.message_history&search=$user");
		//$id = IRI::fromString('https://' . $_SERVER['SERVER_NAME']);
		//$action = 'A user logged in during the exercise event';
		$this->set_object($type, $sf);

		// Set context
		$this->build_context();
		$sf->withContext($this->context);

		// Create statement
		$statement = $sf->createStatement();

		// Send statement
		$this->send_statement($statement, $statementsApiClient);

		return $args;
	}


	// Function to log xapi when a user logs out
	public function log_logout($args)
	{
		rcube::console("message_history: log_logout");

		// Build xapi client
		$this->build_client();
		$statementsApiClient = $this->xApiClient->getStatementsApiClient();

		// Build statement
		$sf = new StatementFactory();

		// Set actor
		$this->set_actor($sf);

		// Set verb
		$verb_id = 'https://w3id.org/xapi/adl/verbs/logged-out';
		$this->set_verb($verb_id, $sf);

		// Set object
		//$mapName = $languageMap->withEntry('en-US', 'Use');
		//$mapDesc = $languageMap->withEntry('en-US', 'A user logged in during the exercise event');
		$type = 'http://id.tincanapi.com/activitytype/resource';
		//$moreInfo = IRL::fromString('https://' . $_SERVER['SERVER_NAME'] . "?_task=message_history&_action=plugin.message_history&search=$user");
		//$id = IRI::fromString('https://' . $_SERVER['SERVER_NAME']);
		//$action = 'A user logged in during the exercise event';
		$this->set_object($type, $sf);

		// Set context
		$this->build_context();
		$sf->withContext($this->context);

		// Create statement
		$statement = $sf->createStatement();

		// Send statement
		$this->send_statement($statement, $statementsApiClient);

		return $args;
	}

	private function xapi_error(Exception $e)
	{
		$m = $e->getMessage();
		rcube::raise_error([
			'line' => __LINE__,
			'file' => __FILE__,
			'message' => "xapi: $m"
			], true, false);
	}

	private function set_user()
	{
		rcube::console("message_history: set_user");

		$rcmail = rcmail::get_instance();

		$this->user_email = $rcmail->user->get_username();
		$db = rcmail::get_instance()->get_dbh();

		// convert from name to email address
		$result = $db->query("SELECT name FROM contacts WHERE email = '" . $this->user_email . "'");
		if ($db->is_error($result)) {
			rcube::raise_error([
				'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
				'message' => "message_history: failed to pull name from database."
				], true, false);
		}
		$records = $db->fetch_assoc($result);
		// verify one entry
		if (!$records || (count($records) != 1)) {
			rcube::console("message_history: cannot find single record for " . $this->user_email);
			return;
		} else {
			$this->user_name = $records['name'];
		}

		// Determine the primary group to be logged with xAPI for this user
		// get groups from global address book
		$groups = $db->query("SELECT contactgroups.name FROM contactgroups INNER JOIN contactgroupmembers ON contactgroups.contactgroup_id = contactgroupmembers.contactgroup_id INNER JOIN contacts ON contacts.contact_id = contactgroupmembers.contact_id WHERE contacts.email = '" . $this->user_email . "'");
		$records = $groups->fetchAll();
		// get primary team from config
		foreach ($records as $record) {
			if (in_array($record['name'], $this->config['teams'])) {
				$this->team = $record['name'];
				break;
			}
		}

		if (!isset($_SESSION['oauth_token']['access_token'])) {
			rcube::console("message_history: no token set for for " . $this->user_email);
			return;
		}

		// get sub and issuer for oauth
		$token = $_SESSION['oauth_token']['access_token'];
		$decoded = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $token)[1]))));
		$this->sub = $decoded->sub;
		$this->iss = $decoded->iss;
		rcube::console("sub: " . $this->sub . ' iss: ' . $this->iss);
	}
}
?>
