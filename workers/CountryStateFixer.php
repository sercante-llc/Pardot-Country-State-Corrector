<?php

// Load correction files
$statecorrectionfilename = trim(getenv('statecorrections'));
if(!empty($statecorrectionfilename) && file_exists(dirname(__FILE__). '/correction_options/' . $statecorrectionfilename))
{
	echo "Loading State correction file: $statecorrectionfilename\n";
	$StateCorrections = csv_to_array($filename=dirname(__FILE__). "/correction_options/{$statecorrectionfilename}");
	//print_r($StateCorrections);
}else
{
	echo "No state file\n";
	$StateCorrections = NULL;
}

$countrycorrectionsfilename = trim(getenv('countrycorrections'));
if(!empty($countrycorrectionsfilename) && file_exists(dirname(__FILE__). '/correction_options/' . $countrycorrectionsfilename))
{
	echo "Loading Country correction file: $countrycorrectionsfilename\n";
	$CountryCorrections = csv_to_array($filename=dirname(__FILE__). "/correction_options/{$countrycorrectionsfilename}");
}else
{
	echo "No Country file\n";	
	$CountryCorrections = NULL;
}

$countryassumptioncorrectionsfilename = trim(getenv('countryassumptions'));
if(!empty($countryassumptioncorrectionsfilename) && file_exists(dirname(__FILE__). '/correction_options/' . $countryassumptioncorrectionsfilename))
{
	echo "Loading Country assumption file: $countryassumptioncorrectionsfilename\n";
	$CountryAssumptions = csv_to_array($filename=dirname(__FILE__). "/correction_options/{$countryassumptioncorrectionsfilename}");
}else
{
	echo "No State->Country file\n";	
	$CountryAssumptions = NULL;
}



//Get the API Key from the server. This is good for 1 hour.
$getAPIKey =  callPardotApi('https://pi.pardot.com/api/login/version/' . trim(getenv('apiversion')),
	array(
		'email' => trim(getenv('pardotLogin')),
		'password' => trim(getenv('pardotPassword')),
		'user_key' => trim(getenv('pardotUserKey')) //available from https://pi.pardot.com/account
	),
	'POST'
);
//print_r($getAPIKey);
$APIKey = $getAPIKey['api_key'];







// Lets look for recent Prospect record changes

$results =  callPardotApi('https://pi.pardot.com/api/prospect/version/'.trim(getenv('apiversion')).'/do/query?',
	array(
		'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
		'api_key' => $APIKey, // requested from the server previously
		'last_activity_after' => '21 minutes ago',
		//'last_activity_after' => '1 days ago',
		'fields' => 'email,country,state,crm_owner_fid' // Optional list for speeding up the process by getting just the data we need.
	),
	'POST'
);
//print_r($results);
loop_the_results($results);

// Lets look for new Prospect record changes
$results =  callPardotApi('https://pi.pardot.com/api/prospect/version/'.trim(getenv('apiversion')).'/do/query?',
	array(
		'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
		'api_key' => $APIKey, // requested from the server previously
		'updated_after' => '21 minutes ago',
		//'updated_after' => '1 days ago',
		'fields' => 'email,country,state,crm_owner_fid' // Optional list for speeding up the process by getting just the data we need.
	),
	'POST'
);
//print_r($results);
loop_the_results($results);





function loop_the_results($results)
{
	// Lets process the data

	foreach($results['result'] as $key => $value)
	{
		
		if($key == 'total_results')
		{
			if($value == 0 )
			{
				//echo "No data to process\n";
				break; // nothing to do here
			}
		}elseif($key == 'prospect')
		{
			if(isset($value['id']) ) // A single result, lets use it
			{
				search_for_errors($value);
			}elseif($value[0]['id']) // we are looking at an array of data, lets loop over it.
			{
				//print_r($value);
				foreach ($value AS $prospect)
				{
					search_for_errors($prospect);
				}

			}else
			{
				print_r($results);
			}
		}else
		{
			print_r($results);
		}


	}
}






function search_for_errors($prospect)
{
	//print_r($prospect);
	global $StateCorrections, $CountryCorrections, $CountryAssumptions, $APIKey; // Lets pull in some data sets

	$corrections = array();

	// State error
	//
	if(!empty($StateCorrections) && isset($prospect['state']) && !empty($prospect['state']) && isset($StateCorrections[strtolower($prospect['state'])]))
	{
		if(!empty($prospect['crm_owner_fid']) && trim(getenv('forcestatecorrections')) != 'true') // This is in the CRM and thus probably not persistant if written OR we overwrite this because of field sync settings
		{
			echo "Skipping update state {$prospect['state']} to {$StateCorrections[strtolower($prospect['state'])]} for {$prospect['email']} as this record is in CRM already\n";
		}elseif(trim(getenv('runmode')) == 'demo')
		{
			echo "Need to update state {$prospect['state']} to {$StateCorrections[strtolower($prospect['state'])]} for {$prospect['email']}\n";
		}else{
			echo "Updating state {$prospect['state']} to {$StateCorrections[strtolower($prospect['state'])]} for {$prospect['id']}\n";
			$corrections['state'] = $StateCorrections[strtolower($prospect['state'])];
		}
	}else{
		//echo "Skipping State checking\n";
	}

	// Country error
	//
	if(!empty($CountryCorrections) && isset($prospect['country']) && !empty($prospect['country']) && isset($CountryCorrections[strtolower($prospect['country'])]))
	{
		if(!empty($prospect['crm_owner_fid']) && trim(getenv('forcecountrycorrections')) != 'true')// This is in the CRM and thus probably not persistant if written OR we overwrite this because of field sync settings
		{
			echo "Skipping update country {$prospect['country']} to {$CountryCorrections[strtolower($prospect['country'])]} for {$prospect['email']} as this record is in CRM already\n";
		}elseif(trim(getenv('runmode')) == 'demo')
		{
			echo "Need to update country {$prospect['country']} to {$CountryCorrections[strtolower($prospect['country'])]} for {$prospect['email']}\n";
		}else{
			echo "Updating country {$prospect['country']} to {$CountryCorrections[strtolower($prospect['country'])]} for {$prospect['id']}\n";			
			$corrections['country'] = $CountryCorrections[strtolower($prospect['country'])];	
		}	
	}else{
		//echo "Skipping Country checking\n";
	}



	// Missing Country but existing state error
	//
	if(!empty($CountryAssumptions) && isset($prospect['state']) && !empty($prospect['state'])  && empty($prospect['country']) && isset($CountryAssumptions[strtolower($prospect['state'])]))
	{
		if(!empty($prospect['crm_owner_fid']) && trim(getenv('forcecountrycorrections')) != 'true')// This is in the CRM and thus probably not persistant if written OR we overwrite this because of field sync settings
		{
			echo "Skipping update missing country {$prospect['state']} to {$CountryAssumptions[strtolower($prospect['state'])]} for {$prospect['email']} as this record is in CRM already\n";
		}elseif(trim(getenv('runmode')) == 'demo')
		{
			echo "Need to add country for {$prospect['state']} to {$CountryAssumptions[strtolower($prospect['state'])]} for {$prospect['email']}\n";
		}else{
			echo "Updating country for {$prospect['state']} to {$CountryAssumptions[strtolower($prospect['state'])]} for {$prospect['id']}\n";			
			$corrections['country'] = $CountryAssumptions[strtolower($prospect['state'])];	
		}	
	}else{
		//echo "Skipping Country checking\n";
	}



	// lets process the corrections if we have them.
	if(!empty($corrections))
	{
		if(trim(getenv('apiversion')) == 4 )
		{
			$results =  callPardotApi("https://pi.pardot.com/api/prospect/version/".trim(getenv('apiversion'))."/do/update/id/{$prospect['id']}?",
				array_merge(array(
					'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
					'api_key' => $APIKey, // requested from the server previously
				),$corrections ),
				'POST'
			);
		}elseif(trim(getenv('apiversion')) == 3)
		{
			$results =  callPardotApi("https://pi.pardot.com/api/prospect/version/".trim(getenv('apiversion'))."/do/update/email/{$prospect['email']}?",
				array_merge(array(
					'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
					'api_key' => $APIKey, // requested from the server previously
				),$corrections ),
				'POST'
			);
		}
	}
}













/**
 * Adapted from Pardot Reference API call http://developer.pardot.com/#sample-code 
 *
 * Call the Pardot API and get the --raw-XML-- associative array response back
 *
 * @param string $url the full Pardot API URL to call, e.g. "https://pi.pardot.com/api/prospect/version/3/do/query"
 * @param array $data the data to send to the API - make sure to include your api_key and user_key for authentication
 * @param string $method the HTTP method, one of "GET", "POST", "DELETE"
 * @return string the --raw-XML-- associative array response from the Pardot API
 * @throws Exception if we were unable to contact the Pardot API or something went wrong
 */
function callPardotApi($url, $data, $method = 'GET')
{
	// build out the full url, with the query string attached.
	$queryString = http_build_query($data, null, '&');
	if (strpos($url, '?') !== false) {
		$url = $url . '&' . $queryString;
	} else {
		$url = $url . '?' . $queryString;
	}
	//echo $url . "\n\n";
	$curl_handle = curl_init($url);

	// wait 5 seconds to connect to the Pardot API, and 30
	// total seconds for everything to complete
	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($curl_handle, CURLOPT_TIMEOUT, 30);

	// https only, please!
	curl_setopt($curl_handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);

	// ALWAYS verify SSL - this should NEVER be changed. 2 = strict verify
	curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 2);

	// return the result from the server as the return value of curl_exec instead of echoing it
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);

	if (strcasecmp($method, 'POST') === 0) {
		curl_setopt($curl_handle, CURLOPT_POST, true);
	} elseif (strcasecmp($method, 'GET') !== 0) {
		// perhaps a DELETE?
		curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, strtoupper($method));
	}

	$pardotApiResponse = curl_exec($curl_handle);
	if ($pardotApiResponse === false) {
		// failure - a timeout or other problem. depending on how you want to handle failures,
		// you may want to modify this code. Some folks might throw an exception here. Some might
		// log the error. May you want to return a value that signifies an error. The choice is yours!

		// let's see what went wrong -- first look at curl
		$humanReadableError = curl_error($curl_handle);

		// you can also get the HTTP response code
		$httpResponseCode = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);

		// make sure to close your handle before you bug out!
		curl_close($curl_handle);

		throw new Exception("Unable to successfully complete Pardot API call to $url -- curl error: \"".
			"$humanReadableError\", HTTP response code was: $httpResponseCode");
	}

	// make sure to close your handle before you bug out!
	curl_close($curl_handle);


	// Quick and dirty way of XML -> array

	$xml = simplexml_load_string($pardotApiResponse);
	$json  = json_encode($xml);
	$pardotApiResponseData = json_decode($json, true);

	if(isset($pardotApiResponseData['@attributes']['stat']) && $pardotApiResponseData['@attributes']['stat'] == 'fail')
	{
		echo $url . "\n\n" . $pardotApiResponse;
		print_r($pardotApiResponseData);
		exit();
	}

	return $pardotApiResponseData;
}



function csv_to_array($filename='', $delimiter=',')
{
	if(!file_exists($filename) || !is_readable($filename))
		return FALSE;

	$header = NULL;
	$data = array();
	if (($handle = fopen($filename, 'r')) !== FALSE)
	{
		while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
		{ 
			$data[strtolower(trim($row[0]))] = trim($row[1]);
		}
		fclose($handle);
	}
	return $data;
}

?>
