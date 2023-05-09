<?php

class exercise_selection extends rcube_plugin
{
	//private $access_token;
	//private $decoded_data;
	

	public function init()
	{
		$rcmail = rcmail::get_instance();

		//$this->add_hook('oauth_login', array($this, 'my_oauth_login_hook'));
		//$this->add_hook('login_after', array($this, 'make_api_call'));
		if ($rcmail->action == 'compose') {
			$this->add_hook('render_page', array($this, 'add_dropdown_to_compose'));
		}
		$this->add_hook('message_ready', array($this, 'add_custom_header'));
	}
/*
	public function my_oauth_login_hook($args) {
    		$this->access_token = $args['access_token'];
	}    
	
	public function make_api_call($args)
    	{
		$url = 'https://player.cwdoe.cmusei.dev/api/me/views';
		$headers = array(
			'Cache-Control: no-cache, no-store',
			'Pragma: no-cache',
			'Accept: text/plain',
			'Authorization: ' . $this->rcmail->decrypt($_SESSION['password']);
		);
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        	curl_setopt($curl, CURLOPT_URL, $url);
        	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        	$response = curl_exec($curl);
		$decoded = json_decode($response, true);
	
		foreach ($decoded as $item) {
			$name = $item['name'];
			$decoded_data[]=$name;
		}

		$this->decoded_data=$decoded_data;
		curl_close($curl);
		
		return $args;
	}
 */

	public function add_dropdown_to_compose($args)
	{
		$rcmail = rcmail::get_instance();
		$this->rc = &$rcmail;
		$url = 'https://player.cwdoe.cmusei.dev/api/me/views';
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

		foreach ($decoded as $item) {
			error_log("view name: " . $item['name']);
		}

		curl_close($curl);


		if ($args['template'] == 'compose')
		{
			$doc = new DOMDocument();
        		$doc->loadHTML($args['content']);
			$subject_div = $doc->getElementById('compose_subject');

			//Create Parent Div
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

			//Add Child dIV
			$child_div = $doc->createElement('div');
			$child_div->setAttribute('class', 'col-10');
			$parent_div->appendChild($child_div);

			//Add Dropdown
			$select_element = $doc->createElement('select');
        		$select_element->setAttribute('name', '_exercise');
        		$select_element->setAttribute('id', 'compose-exercise');
        		$select_element->setAttribute('tabindex', '2');
        		$select_element->setAttribute('class', 'form-control');
			foreach ($decoded as $item) {
				$name = $item['name'];
				$option_element = $doc->createElement('option', $name);
				$option_element->setAttribute('value', $name);
       	 			$select_element->appendChild($option_element);
			}

			$child_div->appendChild($select_element);

			$subject_div->parentNode->insertBefore($parent_div, $subject_div->nextSibling);
			$args['content'] = $doc->saveHTML();
		}

		return $args;
	}

	public function add_custom_header($args)
	{
		// add selected exercise header
       		$exercise = $_POST['_exercise'];
		//$additional_headers['Exercise'] = $exercise;

		$args['message']->headers(array('Exercise' => $exercise), true);

	}


}

?>
