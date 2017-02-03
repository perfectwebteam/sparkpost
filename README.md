# SparkPost Joomla! Mail Plugin
A Joomla! plugin to send mails via the SparkPost API

# Usage
1. Download the zip package
1. Unpack the zip package to a temporary folder
3. Zip the contents of the folder sparkpost-master/plugins/system/sparkpost into a new zip file
2. In your Joomla website go to Extensions -> Manage -> Install
3. Select the new zip file on the Upload Package File tab
4. Click on Upload & Install
5. The plugin is now installed
6. Go to Extensions -> Plugins
7. Filter the list on **sparkpost**
8. Edit the System - SparkPost plugin
9. Enter your API Key (You may need to generate one first on your SparkPost Settings Page)
10. Set the Status to Enabled
11. Save the plugin
12. SparkPost API is now being used

# Logging
The plugin will log any issues to a logfile. The logfile can be found in the folder logs/ and the filename of the log is plg_system_sparkpost.log.Y_m.php. Where Y is the current year and m is the current month.

Whenever an error occurs the following details are logged:
 - Subject
 - Sender
 - Recipients
 - Error message

# Fallback
If the email cannot be send through SparkPost the configured email server in the Joomla Global Configuration will be used.

# Credits
This plugin is made possible by the generous contribution of ROC Nijmegen.
