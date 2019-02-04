# sercante-corrections-poc-heroku
Heroku Sercante Corrections Proof of Concept

Following the tenants of https://12factor.net/ the logins are stored in Heroku as Config Vars in the settings tab. They can also be used locally by setting them as ENV vars.
  pardotLogin
  pardotPassword
  pardotUserKey

Reminder, the API user needs to be set to USA EAST COAST timezone - the same as the Pardot offices as there is a bug in the date/time calculations on the API.

In Heroku resources, add the "Heroku Scheduler" add on and add a new job for every 10 minutes "php workers/CountryStateFixer.php".

You can monitor the activity of the script by looking at the logs under the 'More' drop down in the top right.

## Configuration
Configuration is managed by ENVinronment variables. Minimun required configuration is authentication. All other options 'turn on' features of this script.

### Authentication
The following three items are required for Authentication
  pardotLogin
  pardotPassword
  pardotUserKey

### Country Correction
Country correction is enabled by declaring the source file for the corrections. This is a .csv file in bad value, good value pairs which is intrepreted in a case insensitive way.
  countrycorrections=countries_ISOtoEnglish.csv
The code assumes that if the record is synced to the CRM, changing the Country will have no effect as the CRM will overwrite the value. You can force the correction to occur even if the Country field Sync Behaviour is set to be "Use Pardot's Value" or "Use the most recently updated value".
  forcecountrycorrections=true
#### Available correction files
countries_ISOtoEnglish.csv 2 and 3 letter ISO codes mapped to English full names.

### State Correction
State correction is enabled by declaring the source file for the corrections. This is a .csv file in bad value, good value pairs which is intrepreted in a case insensitive way.
  statecorrections=states_ISOtoEnglish.csv
The code assumes that if the record is synced to the CRM, changing the State will have no effect as the CRM will overwrite the value. You can force the correction to occur even if the State field Sync Behaviour is set to be "Use Pardot's Value" or "Use the most recently updated value".
  forcestatecorrections=true
#### Available correction files
states_ISOtoEnglish.csv 2 and 3 letter ISO codes mapped to English full names.
