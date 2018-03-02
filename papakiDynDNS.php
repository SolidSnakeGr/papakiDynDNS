<?php
/*
 * papakiDynDNS.php
 * A script to update a specific dns record at papaki.gr free DNS Hosting service
 *
 *
 * @author: Παραστατίδης Νίκος <paranic@gmail.com>
 * @version: 1.0 (2012-05-25)
 */


define('DEBUG', TRUE);

include('dom/simple_html_dom.php');

//Setup
$config['hosts'] = array('', 'www', 'mail'); //Add all your host names here
$config['domain'] = 'your_papaki_domain';
$config['new_ip_address'] = file_get_contents('http://icanhazip.com/'); //Update this with the IP of your server if not on it
$config['papaki_username'] = 'your_papaki_gr_username';
$config['papaki_password'] = 'your_papaki_gr_password';

//Preparing each host name
$config['full_hosts'] = array();
foreach ($config['hosts'] as $host){
	if ($host == ""){
		array_push($config['full_hosts'], $config['domain']);
	}else{
		array_push($config['full_hosts'], $host . '.' . $config['domain']);
	}
}

// Do the login
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.papaki.gr/cp2/login.aspx?username=' . $config['papaki_username'] . '&password=' . $config['papaki_password']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookieFile.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookieFile.txt');
$response = curl_exec($ch);
curl_close($ch);
echo "Login Response: " . $response . "\n";
if ($response == 'false') die();

// Fetch domain DNS records
if (DEBUG) print_r("Fetching DNS records.\n");
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.papaki.gr/cp2/manageDNS/?domain=' . $config['domain']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookieFile.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookieFile.txt');
$response = curl_exec($ch);
curl_close($ch);

// Search for the A record we need
if (DEBUG) print_r("Searching for the needed A record(s).\n");
$html = new simple_html_dom();
$html->load($response);
$records_updated = 0;
foreach($html->find('span') as $span)
{
	// rpttypes_ctl00 are A records
	if (startsWith($span->id, 'rpttypes_ctl00_rptRecords_ctl') AND endsWith($span->id, '_lbl_name'))
	{
		foreach($config['full_hosts'] as $key => $full_host)
		{
			if ($span->plaintext == $full_host)
			{
				$rptRecord = explode('_', $span->id);
				$rptRecord = $rptRecord[3];

				$current_ip = $html->find('span[id=rpttypes_ctl00_rptRecords_' . $rptRecord . '_lbl_content]');
				if (DEBUG) print_r("Record found! " . $span->plaintext . " -> " . $current_ip[0]->plaintext . "\n");
				if ($config['new_ip_address'] == $current_ip[0]->plaintext)
				{
					if (DEBUG) print_r("No need to update, IP is the same as the one we are trying to update\n");
					die();
				}

				if (DEBUG) print_r("The record has to be updated.!\n");

				$search_id = 'rpttypes_ctl00_rptRecords_' . $rptRecord . '_lnk_edit';
				$edit_button = $html->find('a[id=' . $search_id . ']');
				$did = $edit_button[0]->did;
				$mode = $edit_button[0]->mode;

				// Fetch update form, so we can get VIEWSTATE and EVENTVALIDATION
				if (DEBUG) print_r("Fetching update form.\n");
				$c = curl_init();
				curl_setopt($c, CURLOPT_URL, 'https://www.papaki.gr/cp2/manageDNS/manageDNS.aspx?did=' . $did . '&mode=' . $mode);
				curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($c, CURLOPT_COOKIEFILE, 'cookieFile.txt');
				curl_setopt($c, CURLOPT_COOKIEJAR, 'cookieFile.txt');
				$response = curl_exec($c);
				curl_close($c);
				$update_html = new simple_html_dom();
				$update_html->load($response);
				$view_state = $update_html->find('input[id=__VIEWSTATE]');
				$event_validation = $update_html->find('input[id=__EVENTVALIDATION]');

				// Do the post to update form
				if (DEBUG) print_r("Posting new data.\n");
				$post_fields = array();
				$post_fields['__EVENTTARGET'] = 'btn_add';
				$post_fields['__VIEWSTATE'] = $view_state[0]->value;
				$post_fields['__EVENTVALIDATION'] = $event_validation[0]->value;
				$post_fields['txt_Host_A'] = $config['hosts'][$key];
				$post_fields['txt_IP_A'] = $config['new_ip_address'];
				$post_fields['lst_ttl_A'] = '3600';
				$c = curl_init();
				curl_setopt($c, CURLOPT_URL, 'https://www.papaki.gr/cp2/manageDNS/manageDNS.aspx?did=' . $did . '&mode=' . $mode);
				curl_setopt($c, CURLOPT_POST, true);
				curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post_fields));
				curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($c, CURLOPT_COOKIEFILE, 'cookieFile.txt');
				curl_setopt($c, CURLOPT_COOKIEJAR, 'cookieFile.txt');
				$response = curl_exec($c);
				curl_close($c);

				$records_updated++;
			}
		}
	}
}

if (DEBUG) print_r("Updated ".$records_updated." out of " . count($config['full_hosts']) . " records\n");

if ($records_updated < count($config['full_hosts']))
{
	//Not all hosts found - maybe need to create entries
	if (DEBUG) print_r("WARNING: One or more records were not found. We may have to insert new ones but that has not been implemented yet.\n");
}

if (DEBUG) print_r("I think i thaw a puthyduck.\n");


// Helper functions
function startsWith($haystack, $needle)
{
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
	$length = strlen($needle);
	if ($length == 0)
	{
		return true;
	}

	$start  = $length * -1; //negative
	return (substr($haystack, $start) === $needle);
}

?>
