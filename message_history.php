<?php
class message_history extends rcube_plugin
{
	public $rc;

    	public function init()
	{

		$rcmail = rcmail::get_instance();
		$this->rc = &$rcmail;
		$this->add_texts('localization/', true);
		$this->load_config();

		// install user hooks
		$this->add_hook('message_ready', array($this, 'log_sent_message'));
		//$this->add_hook('message_read', [$this, 'log_read_message']);
		

		$user = $rcmail->user->get_username();
		//preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $user, $matches);
		//$email = $matches[0];	
		$db = rcmail::get_instance()->get_dbh();
		$result = $db->query("SELECT * FROM message_history_users WHERE email = '$user'");
		// get admin user from config
		// if this is the admin user
		// if user has perms
		if ($result->rowCount() > 0) {

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
				'label' => 'logs',
				'href' => '/?_task=message_history&_action=plugin.message_history',
				//'target' => 'self',
				//'command' => 'switch-task',
				'class' => 'logs',
				'classact' => 'button logs',
				'title' => 'logs',
				'domain' => $this->ID,
				'innerclass' => 'inner',
			],
			'taskbar');
		}
	}

	public function message_history_init()
	{
		$this->register_handler('plugin.body', array($this, 'logs_form'));
		$this->rc->output->set_pagetitle($this->gettext('logs'));
		$this->rc->output->send('plugin');
	}
/*
   	public function add_button_to_sidebar($args)
	{	
    	if ($args['name'] == 'taskbar') {
        	$args['content'] .= '
		<a
			class="logs"
			role="button"
			href="?_task=message_history&_action=plugin.message_history"
			onclick="return rcmail.command(\'switch-task\',\'message_history\',this,event)" >
                	<span class="inner">Logs</span>
            	</a>';
		}
		return $args;
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
		$result = $db->query("SELECT from_user_name, to_user_name, subject, time_sent FROM message_history_v2 ORDER BY time_sent DESC");
		$records = $result->fetchAll();
				
		$table = new html_table(['id' => 'message_history_v2', 'class' => 'message_history_table']);
		$table->add_header('timestamp','Time Sent');
		$table->add_header('subject','Subject');
		$table->add_header('sender','From');
		$table->add_header('recipient','To');
		
		foreach ($records as $record) {
			$to_users = explode(',', $record['to_user_name']);
			//$rowspan = count($to_users);
			$rowspan = 1;
			$table->add(['class' => 'timestamp', 'rowspan' => $rowspan], $record['time_sent']);
			$table->add(['class' => 'subject', 'rowspan' => $rowspan], $record['subject']);
			$table->add(['class' => 'sender', 'rowspan' => $rowspan], $record['from_user_name']);
			//if ($rowspan > 1) {
			if (count($to_users) > 1) {
				$user_rows = '';
				foreach ($to_users as $user) {
					$user_rows .= $user . "<br>";
					//$table->add(['class' => 'recipient', 'rowspan' => "1"], $user);
					//$table->add_row();
				}
				$table->add(['class' => 'recipient', 'rowspan' => $rowspan], $user_rows);
			} else {
				$table->add(['class' => 'recipient', 'rowspan' => $rowspan], htmlspecialchars($record['to_user_name']));
			}
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
		$rcube = rcube::get_instance();
		$db = rcmail::get_instance()->get_dbh();
		$headers = $args['message']->headers();
		$subject = $headers['Subject'];
		$from_orig = $headers['From'];
		$to_orig = $headers['To'];
		$time_sent = $headers['Date'];

		// get just the to emails
		preg_match('/<(.+?)>/', $to_orig, $matches);
		$to_emails = implode(',', $matches);

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
		$result = $db->query("INSERT INTO $table (from_user_name, to_user_name, subject, time_sent, modified) VALUES (?, ?, ?, ?, ?)",
			$from_name, $to_names, $subject, $time_sent, $db->now());
		if ($db->is_error($result))
		{
			rcube::raise_error([
				'code' => 605, 'line' => __LINE__, 'file' => __FILE__, 
				'message' => "message_history: failed to insert record into database."
			], true, false);
		}
		
		return $args;
	}



}
?>
