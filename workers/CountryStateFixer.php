<?php

// Load correction files
// TODO make this load out of an ENV variable to provide the specific file and to opt into that specific function
$StateCorrections = csv_to_array($filename='correction_options/states_ISOtoEnglish.csv');
$CountryCorrections = csv_to_array($filename='correction_options/countries_ISOtoEnglish.csv');




//Get the API Key from the server. This is good for 1 hour.
$getAPIKey =  callPardotApi('https://pi.pardot.com/api/login/version/4',
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

$results =  callPardotApi('https://pi.pardot.com/api/prospect/version/4/do/query?',
	array(
		'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
		'api_key' => $APIKey, // requested from the server previously
		'last_activity_after' => '21 minutes ago',
		//'last_activity_after' => '1 days ago',
		'fields' => 'email,country,state' // Optional list for speeding up the process by getting just the data we need.
	),
	'POST'
);
//print_r($results);
loop_the_results($results);

// Lets look for new Prospect record changes
$results =  callPardotApi('https://pi.pardot.com/api/prospect/version/4/do/query?',
	array(
		'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
		'api_key' => $APIKey, // requested from the server previously
		'updated_after' => '21 minutes ago',
		//'updated_after' => '1 days ago',
		'fields' => 'email,country,state' // Optional list for speeding up the process by getting just the data we need.
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
	global $StateCorrections, $CountryCorrections, $APIKey; // Lets pull in some data sets

	$corrections = array();

	// Country error
	//
	if(isset($prospect['state']) && !empty($prospect['state']) && isset($StateCorrections[strtolower($prospect['state'])]))
	{
		if(getenv('runmode') == 'demo')
		{
			echo "Need to update state {$prospect['state']} to {$StateCorrections[strtolower($prospect['state'])]} for {$prospect['email']}\n";
		}else{
			$corrections['state'] = $StateCorrections[strtolower($prospect['state'])];
		}
	}

	// State error
	//
	if(isset($prospect['country']) && !empty($prospect['country']) && isset($StateCorrections[strtolower($prospect['country'])]))
	{
		if(getenv('runmode') == 'demo')
		{
			echo "Need to update country {$prospect['country']} to {$CountryCorrections[strtolower($prospect['country'])]} for {$prospect['email']}\n";
		}else{
			$corrections['country'] = $StateCorrections[strtolower($prospect['country'])];	
		}	
	}

	// lets process the corrections if we have them.
	if(!empty($corrections))
	{

		$results =  callPardotApi("https://pi.pardot.com/api/prospect/version/4/do/update/id/{$prospect['id']}?",
			array_merge(array(
				'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
				'api_key' => $APIKey, // requested from the server previously
			),$corrections ),
			'POST'
		);
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
