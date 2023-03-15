<?php
class message_history extends rcube_plugin
{
	public $rc;
	private $rcube;
	
	public function init()
	{

		$rcmail = rcmail::get_instance();
		$this->rc = &$rcmail;
		$this->add_texts('localization/', true);
		$this->load_config();
		$this->rcube = rcube::get_instance();

	
		// install user hooks
		$this->add_hook('message_ready', array($this, 'log_sent_message'));
		$this->add_hook('message_read', array($this, 'log_read_message'));
		
		$config = $this->rcube->config->get('message_history');
		$groupname = $config['global']['group'];


		$user = $rcmail->user->get_username();
		$db = rcmail::get_instance()->get_dbh();
		$result = $db->query("SELECT email FROM contacts INNER JOIN contactgroupmembers ON contacts.contact_id = contactgroupmembers.contact_id INNER JOIN contactgroups ON contactgroups.contactgroup_id = contactgroupmembers.contactgroup_id AND contactgroups.name = '$groupname' WHERE email = '$user'");
		
		// if user has perms
		if ($result->rowCount() > 0) {
			//$this->add_hook('settings_actions', array($this, 'admin_settings'));
			//$this->register_action('plugin.message_history_admin', [$this, 'message_history_admin_init']);
			$this->register_action('plugin.message_history', array($this, 'message_history_init'));
			//$this->register_action('message_history.message_history', array($this, 'message_history_init'));
			//$this->register_action('plugin.index', array($this, 'message_history_init'));
			//$this->register_action('message_history.index', array($this, 'message_history_init'));
			//$this->add_hook('template_container', array($this, 'add_button_to_sidebar'));

			$this->register_task('message_history');
			$skin_path = $this->local_skin_path();
			$this->include_stylesheet("$skin_path/message_history.css");
			$this->add_button(
			[
				'type' => 'link',
				'label' => 'button_name',
				'href' => '/?_task=message_history&_action=plugin.message_history',
				//'target' => 'self',
				//'command' => 'switch-task',
				'class' => 'logs',
				'classact' => 'button logs',
				'title' => 'button_name',
				'domain' => $this->ID,
				'innerclass' => 'inner',
			],
			'taskbar');
		}
	}
/*
	public function admin_settings($args)
	{	
		$this->add_texts('localization/');

		$args['actions'][] = [
			'action' => 'plugin.message_history_admin',
			'class' => 'admin logs',
			'label' => 'message_history.adminButton',
			'title' => 'adminButton',
			'domain' => $this->ID,
		];

		return $args;
	}
 */

	public function message_history_init()
	{
		$this->register_handler('plugin.body', array($this, 'logs_form'));
		$this->rc->output->set_pagetitle($this->gettext('plugin_name'));
		$this->rc->output->send('plugin');

	}
/*
	public function message_history_admin_init()
	{
		$this->register_handler('plugin.body', [$this, 'admin_pannel']);
		$rcmail = rcmail::get_instance();
		$rcmail->output->set_pagetitle($this->gettext('admininfo'));
		$rcmail->output->send('plugin');
	}

	public function admin_pannel()
	{
		$rcmail = rcmail::get_instance();
		$output = html::p('p', "Select users that should have access to the messge history logs");

		// required: new table message_history_users with id, contact_id/user_id permission
		//
		// db query username, email, permission from message_history_admin join contacts/users on contacts.id/users.id = message_history_users.contact_id/user_id
		//
		// db query contacts/users
		// display table but add additional column called permission
		// 
		return $output;
	}
 */
	public function logs_form()
	{
		$rcmail = rcmail::get_instance();
		//$user = $rcmail->user;
		$this->include_script('message_history.js');
		$skin_path = $this->local_skin_path();
		$db = rcmail::get_instance()->get_dbh();

		//$result = $db->query("SELECT * FROM message_history");
		//$result = $db->query("SELECT * FROM message_history_v2");
		//$result = $db->query("SELECT from_user_name, STRING_AGG(to_user_name, ', ') as to_user_name, subject, time_sent FROM message_history_v2 GROUP BY from_user_name, subject, time_sent");
		$result = $db->query("SELECT from_user_name, to_user_name, subject, time_sent, read_status FROM message_history_v2 ORDER BY time_sent DESC");
		$records = $result->fetchAll();
				
		$table = new html_table(['id' => 'message_history_v2', 'class' => 'message_history_table']);
		$table->add_header('timestamp','Time Sent');
		$table->add_header('subject','Subject');
		$table->add_header('sender','From');
		$table->add_header('recipient','To');
		$table->add_header('status', 'Read Status');
		
		foreach ($records as $record) {
			$to_users = explode(',', $record['to_user_name']);
			//$rowspan = count($to_users);
			$rowspan = 1;
			$table->add(['class' => 'timestamp', 'rowspan' => $rowspan], $record['time_sent']);
			$table->add(['class' => 'subject', 'rowspan' => $rowspan], $record['subject']);
			$table->add(['class' => 'sender' , 'rowspan' => $rowspan], $record['from_user_name']);
			$table->add(['class' => 'recipient', 'rowspan' => $rowspan], htmlspecialchars($record['to_user_name']));
			$read_status = $record['read_status'] == 't' ? 'Read' : 'Unread';
			$table->add(['class' => 'status', 'rowspan' => $rowspan], $read_status);
			//if ($rowspan > 1) {
			//if (count($to_users) > 1) {
			//	$user_rows = '';
			//	foreach ($to_users as $user) {
			//		$user_rows .= $user . "<br>";
					//$table->add(['class' => 'recipient', 'rowspan' => "1"], $user);
					//$table->add_row();
			//	}
			//	$table->add(['class' => 'recipient', 'rowspan' => $rowspan], $user_rows);
			//	$read_status = $record['read_status'] == 't' ? 'Read' : 'Unread';
			//	$table->add(['class' => 'status', 'rowspan' => $rowspan], $read_status);
			//} else {
			//	$table->add(['class' => 'recipient', 'rowspan' => $rowspan], htmlspecialchars($record['to_user_name']));
			//	$read_status = $record['read_status'] == 't' ? 'Read' : 'Unread';
			//	$table->add(['class' => 'status', 'rowspan' => $rowspan], $read_status);
			//}
			//$read_status = $record['read_status'] == 't' ? 'Read' : 'Unread';
			//$table->add(['class' => 'status', 'rowspan' => $rowspan], $read_status);
			$table->add_row();
		}

		//$output = html::header('title', "Roundcube Logs");
		
		//$output = html::p('h1', 'Roundcube Logs');
		$output = html::br();
		$output .= html::p('p', "This table will provide analytical information of emails being sent by providing information such as the sender, recipient, subject of the email, and the time it was sent.");
		$search = new html_inputfield(['type' => 'text', 'name' => '_search', 'placeholder' => 'Search', 'id' => 'table_search', 'class' => 'search']);
		$output .= $search->show();
		$output .= html::br();
		$output .= html::div('table-wrapper', $table->show());
		//$output .= $table->show();
		return $output;
	}

	public function log_sent_message($args)
	{
		$this->load_config();
		$db = rcmail::get_instance()->get_dbh();
		$headers = $args['message']->headers();
		$subject = $headers['Subject'];
		$from_orig = $headers['From'];
		$to_orig = $headers['To'];
		$time_sent = $headers['Date'];

		// get just the to emails
		preg_match_all('/<(.+?)>/', $to_orig, $matches);
		//$to_emails = implode(',', $matches);
		 $to_emails = $matches[1];

		// get just the to names
		$to_names = preg_replace('/<(.+?)>/', '', $to_orig);
		$to_names = preg_replace('/ , /', ',', $to_names);
		$to_names = trim($to_names);

		
		// convert from name to email address
		$result = $db->query("SELECT name FROM contacts WHERE email = '$from_orig'");
		if ($db->is_error($result))
		{
			rcube::raise_error([
				'code' => 605, 'line' => __LINE__, 'file' => __FILE__, 
				'message' => "message_history: failed to pull name from database."
			], true, false);
		}
		$records = $db->fetch_assoc($result);
		$from_name = $records['name'];

		//$table = 'message_history';
		$table = 'message_history_v2';
		//$result = $db->query("INSERT INTO $table (from_user_id, to_user_id, subject, time_sent, modified) VALUES (?, ?, ?, ?, ?)",
		//$from_id, $to_id, $subject, $date, $db->now());
		//$result = $db->query("INSERT INTO $table (from_user_name, to_user_name, subject, time_sent, modified) VALUES (?, ?, ?, ?, ?)",
		//	$from_name, $to_names, $subject, $time_sent, $db->now());
		
		//$result = $db->query("INSERT INTO $table (from_user_name, to_user_name, subject, time_sent, modified, read_status) VALUES (?, ?, ?, ?, ?, ?)", 
		//	$from_name, $to_names, $subject, $time_sent, $db->now(), 'FALSE');

		// convert to email addresses to names
		$to_names = array();
		foreach ($to_emails as $to_email) {
    			$result = $db->query("SELECT name FROM contacts WHERE email = '$to_email'");
   			if ($db->is_error($result))
    			{
        			rcube::raise_error([
            				'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
            				'message' => "message_history: failed to pull name from database."
        			], true, false);
    			}
    			$records = $db->fetch_assoc($result);
    			$to_names[] = $records['name'];
		}
		
		foreach ($to_names as $to_name) {
        		$result = $db->query("INSERT INTO $table (from_user_name, to_user_name, subject, time_sent, modified, read_status) VALUES (?, ?, ?, ?, ?, ?)",
            			$from_name, $to_name, $subject, $time_sent, $db->now(), 'FALSE');
		}
		if ($db->is_error($result))
		{
			rcube::raise_error([
				'code' => 605, 'line' => __LINE__, 'file' => __FILE__, 
				'message' => "message_history_v2: failed to insert record into database."
			], true, false);
		}
		
		return $args;
	}

	public function log_read_message($args)
{
    $this->load_config();
    $db = rcmail::get_instance()->get_dbh();
    $message = $args['message'];

    //Get user who is actually reading the email
    $rcmail = rcmail::get_instance();
    $user = $rcmail->user->get_username();
    $convert_user = $db->query("SELECT name FROM contacts WHERE email = '$user'");
    $records = $db->fetch_assoc($convert_user);
    $logged_user = $records['name'];

    // Get To User Value
    $to_orig = $message->get_header('to');
    $to_names = preg_replace('/<(.+?)>/', '', $to_orig);
    $to_names = preg_replace('/ , /', ',', $to_names);
    $to_names = trim($to_names);
    $to_array = explode(',', $to_names);

    // Get From User Value
    $from = $message->get_header('from');
    $convert_from = $db->query("SELECT name FROM contacts WHERE email = '$from'");
    $records = $db->fetch_assoc($convert_from);
    $from_name = $records['name'];

    // Get Subject Value
    $subject = $message->get_header('subject');
    $parsed_subject = substr($subject, strpos($subject, "]") + 2);

    // Get Date Value
    $date_str = $message->get_header('Date');
    $timestamp = date('Y-m-d H:i:s', strtotime($date_str)) . '+00';

    $table = 'message_history_v2';

    // Loop through the array of recipients and update the read status for each recipient separately
	
// Loop through the array of recipients and update the read status for each recipient separately
foreach ($to_array as $to) {
    $to = trim($to);
    if ($to === $logged_user) { // check if the current recipient matches the logged in user
        // update is_read column to 1 for this message
        $result = $db->query("UPDATE $table SET read_status = TRUE WHERE subject = '$parsed_subject' AND time_sent = '$timestamp' AND from_user_name = '$from_name' AND to_user_name = '$to'");
	if ($db->is_error($result)) {
		//Attempt to insert email not send from roundcube, if insert fails, raise error
		//definately check for errors both times
            rcube::raise_error([
                'code' => 605, 'line' => __LINE__, 'file' => __FILE__,
                'message' => "message_history: failed to mark message as read."
            ], true, false);
        }
    }
}

	}

}
?>
