# Roundcube Logging & Monitoring Plugin
## Overview
The Roundcube Logging & Monitoring Plugin is a powerful tool that enhances the logging and monitoring capabilities of Roundcube, a web-based email client. This plugin provides additional features to track and analyze messages sent and received within Roundcube. It offers three integrated functionalities: Exercise Selection, Message History Table, and xAPI Logging.

This plugin integrates with the Crucible Cyber Simulation Framework.

## License 
This plugin is released under the GNU General Public License Version 3+ and is Copyright 2023 Carnegie Mellon University.

## Functionalities

### Exercise Selection
The Exercise Selection functionality allows users to select exercises while composing messages. Key features of this functionality include:

- Integration with the compose template: This feature is only activated when the compose template is rendered. 
- Player API integration: It makes an API call to obtain the Player views associated with the user.
- Dropdown menu: When composing a message, a dropdown menu is added, populated with the views obtained from the Player API. Users can select the desired exercise from this dropdown menu.
- Message headers: The selected exercise is added to the message headers, providing additional context.

Note that 'exercise' in this context is synonymous with 'player view'.

### Message History Table
The Message History Table functionality provides a comprehensive overview of message activities. Its main features include:

- Observer access: This functionality is exclusively available to users in the gloval address book group configured in the plugin settings. This defaults to 'Observers'.
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

## Installation

**1. Download the plugin:**

In your ```roundcube/plugins``` fodler run this command:

``` git clone https://github.com/sei-awelle/roundcube-message_history.git message_history```

alternatively, if you don't have ```git``` installed, you can just get the zip instead:

``` 
wget wget -O roundcube-message_history-main.zip https://github.com/sei-awelle/roundcube-message_history.git
unzip roundcube-message_history-main.zip 
mv roundcube-message_history-main message_history
```

**2. Update Roundcube Config**

For the plugin to work, you will need to update the ```roundcube/config/config.inc.php``` with:

```$config['plugins'] = ['message_history'];```

Additionally, to obtain the user token to be used when requesting the views the user is a part of, you will need to update the following line on the ```roundcube/config/config.inc.php``` to match the scopes assigned on the OAuth server:

```$config['oauth_scope'] = 'openid profile email auth-imap offline_access player_api';```

Note: Make sure that the scopes added on your OAuth server match the scopes added on the ```roundcube/config/config.inc.php``` file, they don't necessary need to match the example provided above.

**3. Install All Dependencies**

For the plugin to work, you will need to install all required dependencies.
For this, run the following command:

```composer install```

**4. Install Database**

For the plugin to work, you will need to have a database table that will match the columns used on the Message History Table. For this, you can use the ```plugins\message_history\SQL\postgresql.sql``` file.

To do this from your Roundcube pod, follow these steps:

1. Install pgsql on your pod.

```
apt-get update
apt-get install -y postgresql-client
```

2. Create the database table.
```
psql -h your-postgresql-host -U your-username -d your-database
```
Note: Replace *your-postgresql-host*, *your-username*, and *your-database* with the appropriate values for your PostgreSQL connection.

3. Update the owner of the table with a user who has the required permissions to make changes.
```
ALTER TABLE your_table OWNER TO new_owner;
```
Note: Replace *your_table* with the name of the table for which you want to update the owner, and *new_owner* with the new owner.

## Configuration

To configure the plugin to your needs, the following configuration are available in the plugin's ```config.inc.php``` file. 

**1. Message History**

To change the group that is allowed to access the Mesage History Table, update the ```roundcube/plugins/message_history/config.inc.php``` with the desired group:

```'group' => 'Allowed Group Name', ```

**2. xAPI**

To enable xAPI, some configuration settings are needed. These include:

-   lrs_endpoint: URL for your LRS.
-   lrs_username: LRS username, credential ID, or API key from your LRS endpoint.
-   lrs_password: LRS password or API secret for the given username from your LRS endpoint.
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



