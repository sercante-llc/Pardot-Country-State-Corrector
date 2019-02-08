<?php
/**
  * Sercante Country State Fixer.
  *
  * This is a tool intended to solve a common Pardot problem. GeoIP values are not aligned with SFDC picklist values
  *
  * This is designed to be ran in Heroku under the Heroku Scheduler. It should run on most any PHP environment.
  *
  * 
  *
  * @author  Mike Creuzer <creuzer@sercante.com>
  *
  */


// Load correction files
// File names are defined as ENV variables
// files are pre-created and stored in the correction_options folder.
// These files are in .csv format in a bad,good data order, one match per line.
// It is acceptable to NOT provide a file, in which case the specific correction will be skipped.
$statecorrectionfilename = trim(getenv('statecorrections'));  // we do a trim as local command line may put a carriage return at the end
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



// First thing, Get the API Key from the server. This is good for 1 hour.
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




// Lets get lists of data to look over.
// We can grab a specific Pardot list (defined with an ENV variable) or recently changed and active prospect records.
$pardotListID = trim(getenv('pardotListID')); // grab the env if it exists
// lets override the list if we pass in a list id variable on the command line
if( isset($argv[1]) && strtolower($argv[1]) == 'pardotlistid' && !empty($argv[2]))
{
	$pardotListID = $argv[2];
}
$recordsToRequest = 200; // We can get up to 200 results at a time from the Pardot API
if(!empty($pardotListID)) // if we want to inspect a list, lets do so
{
	$recordCount = 1; // Start with a value which we will update once we do the first call
	$accumulatedRecords = array();
	for($loopcounter = 0; $loopcounter * $recordsToRequest < $recordCount; $loopcounter++)
	{
		//echo "{$loopcounter} {$recordCount}\n";
		$results =  callPardotApi('https://pi.pardot.com/api/listMembership/version/'.trim(getenv('apiversion')).'/do/query?',
			array(
				'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
				'api_key' => $APIKey, // requested from the server previously
				'list_id' => $pardotListID,
				'limit'	  => $recordsToRequest,
				'offset' => $loopcounter * $recordsToRequest
			),
			'POST'
		);


		//print_r($results);
		if($results['result']['total_results'] != $recordCount)
		{
			$accumulatedRecords = $results;
			$recordCount = $results['result']['total_results'];
		}else{
			foreach($results['result']['list_membership'] AS $listMember)
			{
				array_push($accumulatedRecords['result']['list_membership'], $listMember);
			}
		}
		//echo "{$loopcounter} {$recordCount}\n";
		//print_r($accumulatedRecords);
		
	}
	echo "Inspecting " . (isset($accumulatedRecords['result']['list_membership']) ? sizeof($accumulatedRecords['result']['list_membership']) : 0 ) . " members on the list {$pardotListID}.\n";;
	loop_the_results($accumulatedRecords);

}else{


	// Lets look for recent Prospect record changes
	// This tool assumes it is running every 10 minutes, and that it gets 2 chances to make a correction, so it looks back 21 minutes by default
	// These values can be changed by setting ENV variables.


	$recordCount = 1; // Start with a value which we will update once we do the first call
	$accumulatedRecords = array();
	for($loopcounter = 0; $loopcounter * $recordsToRequest < $recordCount; $loopcounter++)
	{
		//echo "{$loopcounter} {$recordCount}\n";
	$results =  callPardotApi('https://pi.pardot.com/api/prospect/version/'.trim(getenv('apiversion')).'/do/query?',
		array(
			'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
			'api_key' => $APIKey, // requested from the server previously
			'last_activity_after' => '21 minutes ago',
			//'last_activity_after' => '1 days ago',
			'fields' => 'email,country,state,crm_owner_fid', // Optional list for speeding up the process by getting just the data we need.
			'limit'	  => $recordsToRequest,
			'offset' => $loopcounter * $recordsToRequest
		),
		'POST'
	);


		//print_r($results);
		if($results['result']['total_results'] != $recordCount)
		{
			$accumulatedRecords = $results;
			$recordCount = $results['result']['total_results'];
		}else{
			foreach($results['result']['prospect'] AS $listMember)
			{
				array_push($accumulatedRecords['result']['prospect'], $listMember);
			}
		}
		//echo "{$loopcounter} {$recordCount}\n";
		//print_r($accumulatedRecords);
		
	}
	echo "Inspecting " . (isset($accumulatedRecords['result']['prospect']) ? sizeof($accumulatedRecords['result']['prospect']) : 0 ) . " recently active prospects.\n";;
	loop_the_results($accumulatedRecords);



	$recordCount = 1; // Start with a value which we will update once we do the first call
	$accumulatedRecords = array();
	for($loopcounter = 0; $loopcounter * $recordsToRequest < $recordCount; $loopcounter++)
	{
		//echo "{$loopcounter} {$recordCount}\n";
		$results =  callPardotApi('https://pi.pardot.com/api/prospect/version/'.trim(getenv('apiversion')).'/do/query?',
		array(
			'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
			'api_key' => $APIKey, // requested from the server previously
			'updated_after' => '21 minutes ago',
			//'updated_after' => '1 days ago',
			'fields' => 'email,country,state,crm_owner_fid', // Optional list for speeding up the process by getting just the data we need.
			'limit'	  => $recordsToRequest,
			'offset' => $loopcounter * $recordsToRequest
		),
		'POST'
	);


		//print_r($results);
		if($results['result']['total_results'] != $recordCount)
		{
			$accumulatedRecords = $results;
			$recordCount = $results['result']['total_results'];
		}elseif($results['result']['total_results'] == 1){
			//print_r($results['result']['prospect']);
			if(isset($accumulatedRecords['result']['prospect']) && is_array($accumulatedRecords['result']['prospect']))
			{
				array_push($accumulatedRecords['result']['prospect'],$results['result']['prospect']);
			}else{
				$accumulatedRecords['result']['prospect'] = $results['result']['prospect'];			
			}
			
		}else{
			foreach($results['result']['prospect'] AS $listMember)
			{
				array_push($accumulatedRecords['result']['prospect'], $listMember);
			}
		}
		//echo "{$loopcounter} {$recordCount}\n";
		//print_r($accumulatedRecords);
		
	}
	echo "Inspecting " . (isset($accumulatedRecords['result']['prospect']) ? sizeof($accumulatedRecords['result']['prospect']) : 0 ) . " recently updated prospects.\n";;
	loop_the_results($accumulatedRecords);





}



// We have some arrays of prospects to inspect. Lets go over them
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
		}elseif($key == 'list_membership')
		{
			// We have membership list values, we need to get to the actual prospect record data
			//print_r($value);
			foreach ($value AS $prospect)
			{
				GLOBAL $APIKey;
				//print_r($prospect);
				// Lets look for new Prospect record changes
				$subresults =  callPardotApi('https://pi.pardot.com/api/prospect/version/'.trim(getenv('apiversion')).'/do/read?',
					array(
						'user_key' => trim(getenv('pardotUserKey')), //available from https://pi.pardot.com/account
						'api_key' => $APIKey, // requested from the server previously
						'id' => $prospect['prospect_id'],
						'fields' => 'email,country,state,crm_owner_fid' // Optional list for speeding up the process by getting just the data we need.
					),
					'POST'
				);
				//print_r($subresults);

				search_for_errors($subresults['prospect']);
			}


		}else
		{
			print_r($results);
		}


	}
}





// Here we have an assortment of things to check for. If you want to add additional checks, you would do it here.
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
			echo "Skipping update state {$prospect['state']} to {$StateCorrections[strtolower($prospect['state'])]} for {$prospect['id']} as this record is in CRM already\n";
		}elseif(trim(getenv('runmode')) == 'demo')
		{
			echo "Need to update state {$prospect['state']} to {$StateCorrections[strtolower($prospect['state'])]} for {$prospect['id']}\n";
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
			echo "Skipping update country {$prospect['country']} to {$CountryCorrections[strtolower($prospect['country'])]} for {$prospect['id']} as this record is in CRM already\n";
		}elseif(trim(getenv('runmode')) == 'demo')
		{
			echo "Need to update country {$prospect['country']} to {$CountryCorrections[strtolower($prospect['country'])]} for {$prospect['id']}\n";
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
		if(trim(getenv('runmode')) == 'demo')
		{
			echo "Need to add country for {$prospect['state']} to {$CountryAssumptions[strtolower($prospect['state'])]} for {$prospect['id']}\n";
		}else{
			echo "Updating country for {$prospect['state']} to {$CountryAssumptions[strtolower($prospect['state'])]} for {$prospect['id']}\n";			
			$corrections['country'] = $CountryAssumptions[strtolower($prospect['state'])];	
		}	
	}else{
		//echo "Skipping Country checking\n";
	}

	// Add your own checks here if you need



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
function callPardotApi($url, $data, $method = 'GET', $recursed = FALSE)
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
		/*
		// Try to use the headers for non-post methods
		curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array(
			"Pardot api_key: {$data['api_key']}",
			"Pardot user_key: {$data['user_key']}",
		));
		// This doesn't actually do anything here, it's much too late. 
		unset($data['api_key']);
		unset($data['user_key']);
		 */
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
		// Lets look for an API Key Time out.
		if($pardotApiResponseData['err'] == "Invalid API key or user key" && $recursed == FALSE) // make sure we don't dive into a recursion loop
		{
			echo "Attempting to refresh APIKey\n";
			global $APIKey; // Bring this in scope so we can update it from here
			// Try to login again

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

			// Try to make the call a 2nd time now that we ought to have a good API key
			$data['api_key'] = $APIKey; // We need to give the new API Key
			callPardotApi($url, $data, $method, TRUE);

		}else{

			echo $url . "\n\n" . $pardotApiResponse;
			print_r($pardotApiResponseData);
			exit();
		}
	}

	return $pardotApiResponseData;
}


// This re-structures our .csv file bad,good in an array struction of array('bad'->'good',) so we can do quick lookups looking for data to fix.
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
