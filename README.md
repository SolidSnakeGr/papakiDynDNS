papakiDynDNS
============

Extended the original papakiDynDNS to support multiple host names per domain.

Since papaki.com still does not support auto-updating IP addresses for our domains I found the tool really useful and extended it to fit my needs. I have now set it to run through a bash script on Ubuntu Server 16.04 using a simple command "php papakiDynDNS.php", so that saves me the hassle to have to log in and change each entry separately every time the server's (dynamic) IP changes.

You just need to update the "Setup" section with your server's details:

//Setup
$config['hosts'] = array('', 'www', 'mail'); //Add all your host names here
$config['domain'] = 'example.gr';
$config['new_ip_address'] = file_get_contents('http://icanhazip.com/'); //Update this with the IP of your server if not on it
$config['papaki_username'] = 'username';
$config['papaki_password'] = 'password';

Then you can save, exit, and run the script on your server through the command line using the following command:
"php papakiDynDNS.php"

It could also be further extended to work based on external parameters.
I hope it makes someone's life easier!
