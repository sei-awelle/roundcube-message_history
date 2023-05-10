# Roundcube Logging & Monitoring Plugin
## Overview
The Roundcube Logging & Monitoring Plugin is a powerful tool that enhances the logging and monitoring capabilities of Roundcube, a web-based email client. This plugin provides additional features to track and analyze messages sent and received within Roundcube. It offers three integrated functionalities: Exercise Selection, Message History Table, and xAPI Logging.

## License 
This plugin is released under the GNU General Public License Version 3+.

## Functionalities

### Exercise Selection
The Exercise Selection functionality allows users to select exercises while composing messages. Key features of this functionality include:

- Integration with the compose template: The plugin is only activated when the compose template is rendered. 
- API integration: It makes an API call to obtain the views associated with the user.
- Dropdown menu: When composing a message, a dropdown menu is added, populated with the views obtained from the API call. Users can select the desired exercise from this dropdown menu.
- Message headers: The selected exercise is added to the message headers, providing additional context.

### Message History Table
The Message History Table functionality provides a comprehensive overview of message activities. Its main features include:

- Observer access: This functionality is exclusively available to users in the Observers group.
- Dedicated section: A new section called "History" is added to the Roundcube interface, allowing Observers to access the message history table.
- Table structure: The message history table contains the following columns: Exercise Name, Time Sent, Subject, From, To, Read Status, and Message ID.
- Logging sent messages: When a message is sent, its information is logged in the database table.
- Read status tracking: The table marks the status of a message as "read" when a user accesses the received message.
- Database integration: The information displayed in the Message History Table is obtained from a dedicated database table.
- Stackstorm exception: Initially, messages sent through Stackstorm are not logged. However, a process is implemented to add the message to the table when the user reads it. If the message is not found in the database table, a new record is added with the read status enabled.

### xAPI Logging
The xAPI Logging functionality enables the tracking of user actions and interactions within Roundcube using xAPI. The following actions are logged:

- User login: xAPI statements are generated when a user logs into Roundcube.
- Screen refresh: xAPI statements are generated when a user refreshes their screen within Roundcube.
- Message sent: xAPI statements are generated when a message is sent.
- Message read: xAPI statements are generated when a message is read.

To facilitate the creation of xAPI statements, the plugin provides the following functions:

- xAPI client builder: This function builds the xAPI client required for communication with the xAPI endpoint.
- xAPI context builder: This function constructs the xAPI context, capturing the relevant contextual information.
- xAPI actor setter: This function sets the actor (user) associated with the xAPI statement.
- xAPI object setter: This function sets the object of the xAPI statement.
- xAPI statement sender: This function sends the constructed xAPI statement to the xAPI endpoint for further processing.

## Installation

**1. Download the plugin:**

In your ```roundcube/plugins``` fodler run this command:

``` git clone <add url>```

alternatively, if you don't have ```git``` installed, you can just get the zip instead:

``` 
wget wget -O <add zip> <url>
unzip <add zip>
```

**2. Update Roundcube Config**

For the plugin to work, you will need update the ```roundcube/config/config.inc.php``` with:

```$config['plugins'] = ['message_history'];```

## Configuration

To configure the plugin to your needs, the following configuration are available in the plugin's ```config.inc.php``` file. 

**1. Message History**

To change the group that is allowed to access the Mesage History Table, update the ```roundcube/plugins/message_history/config.inc.php``` with the desired group:

```'group' => 'Allowed Group', ```

**2. xAPI**

To enable xAPI, some configuration settings are needed. These include:

-   lrs-endpoint: URL for your LRS.
-   lrs-username: LRS username, credential ID, or API key from your LRS endpoint.
-   lrs-password: LRS password or API secret for the given username from your LRS endpoint.
-   actor_email: If set to 'true' , users will be identified by their email.
-   actor_username: If set to 'true', users will be identified by the username.

To make any desired changes, update the ```roundcube/plugins/message_history/config.inc.php``` with the desired configurations:

```
    // URL for LRS
    'lrs_endpoint' => 'LRS URL',
    // LRS username, credential ID, or API key
    'lrs_username' => 'Username',
    // LRS password or API secret for given username
    'lrs_password' => 'Password',
    // Identify users by email
    'actor_email' => 'true',
    // Identify users by username
    'actor_username' => 'false',
```



