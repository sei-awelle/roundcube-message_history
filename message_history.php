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
		$user = $rcmail->user->get_username();
		preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $user, $matches);
		$email = $matches[0];	
		$db = rcmail::get_instance()->get_dbh();
		$result = $db->query("SELECT * FROM message_history_users WHERE email = '$email'");
		// get admin user from config
		// if this is the admin user
		// if user has perms
		if ($result->rowCount() > 0) {

			$this->register_action('plugin.message_history', array($this, 'message_history_init'));
			//$this->register_action('message_history.message_history', array($this, 'message_history_init'));
			//$this->register_action('plugin.index', array($this, 'message_history_init'));
			//$this->register_action('message_history.index', array($this, 'message_history_init'));
			//$this->add_hook('template_container', array($this, 'add_button_to_sidebar'));

			$this->add_hook('message_ready', array($this, 'log_message'));
			
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
		//$this->register_handler('message_history.index', array($this, 'logs_form'));
		$this->rc->output->set_pagetitle($this->gettext('logs'));
		$this->rc->output->send('plugin');
	}

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

	public function logs_form()
	{
		$rcmail = rcmail::get_instance();
		//$user = $rcmail->user;
		//$identity = $user->get_identity();
		$this->include_script('message_history.js');
		$skin_path = $this->local_skin_path();
		$this->include_stylesheet("$skin_path/message_history.css");
	
		//$table = new html_table(array('cols' => 4));
		
		//$table->add('title', html::label('title', rcmail::Q($this->gettext('logs'))));
	
		//$table->add_header('table_header', 'from');
		//$table->add_header('table_header', 'to');
		//$table->add_header('table_header', 'subject');
		//$table->add_header('table_header', 'time sent');
		//$output = $this->rc->output->current_username('logs');

		$db = rcmail::get_instance()->get_dbh();

		//$result = $db->query("SELECT * FROM message_history");
		$result = $db->query("SELECT * FROM message_history_v2");
		$records = $result->fetchAll();
				
		$table = new html_table(['id' => 'message_history_v2', 'class' => 'message_history_table']);
		$table->add_header('table_header','From');
		$table->add_header('table_header','To');
		$table->add_header('table_header','Subject');
		$table->add_header('table_header','Time Sent');
		//foreach ($records as $record) {
		//	$table->add_row($record);
		//}
		foreach ($records as $record) {
			$table->add('cell', $record['from_user_name']);
			$table->add('cell', $record['to_user_name']);
			$table->add('cell', $record['subject']);
			$table->add('cell', $record['time_sent']);
			$table->add_row();
		}

		//$output = $this->rc->output->current_username('logs');
		//$output .= html::br();
		//$output .= html::span('logs', 'hello world');
		//$output .= $table->show();
		//$output .= html::br();
		//$output = html::header('title', "Roundcube Logs");
		
		//$output = html::p('h1', 'Roundcube Logs');
		$output = html::br();
		$output .= html::p('p', "This table will provide analytical information of emails being sent by providing information such as the sender, recipient, subject of the email, and the time it was sent.");
		$output .= $table->show();
		//$attrib = ['id' => 'message_history'];
		//$attrib = ['id' => 'message_history_v2', 'class' => 'message_history_table' ];
		//$output .= $rcmail->table_output($attrib, $records, ['from_user_id', 'to_user_id', 'subject', 'time_sent'], 'message_id');
		//$output .= $rcmail->table_output($attrib, $records, ['from_user_name', 'to_user_name', 'subject', 'time_sent'], 'message_id');
		// add new code here:
		// use the other table class and functions to display the data
		//
		//
		return $output;  
	}

	public function log_message($args)
	{
		$this->load_config();
		$rcube = rcube::get_instance();
		$db = rcmail::get_instance()->get_dbh();
	
		$headers = $args['message']->headers();
		$conf['decode_headders'] = true;
		$subject = $headers['Subject'];
		$from = $headers['From'];
		$to = $headers['To'];
		$date = $headers['Date'];


		// ensure to and from are just email
		// regex /(\s+@\s+)/\1/		
		// convert email address to contact id
		//$result = $db->query("SELECT contact_id FROM contacts WHERE email = '$from'");
		$result = $db->query("SELECT email FROM contacts WHERE email = '$from'");
		if ($db->is_error($result))
		{
			rcube::raise_error([
				'code' => 605, 'line' => __LINE__, 'file' => __FILE__, 
				'message' => "message_history: failed to pull contact id for from field."
			], true, false);
		}
		$records = $db->fetch_assoc($result);
		//$from_id = $records['contact_id'];
		$from_name = $records['email'];
		
		//$to_id = "20";
		//$to_name = "20";
		
		//$table = 'message_history';
		$table = 'message_history_v2';
		//$result = $db->query("INSERT INTO $table (from_user_id, to_user_id, subject, time_sent, modified) VALUES (?, ?, ?, ?, ?)",
		//$from_id, $to_id, $subject, $date, $db->now());
		$result = $db->query("INSERT INTO $table (from_user_name, to_user_name, subject, time_sent, modified) VALUES (?, ?, ?, ?, ?)",
			$from_name, $to, $subject, $date, $db->now());
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
