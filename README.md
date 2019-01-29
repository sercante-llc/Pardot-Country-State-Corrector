# sercante-corrections-poc-heroku
Heroku Sercante Corrections Proof of Concept

Following the tenants of https://12factor.net/ the logins are stored in Heroku as Config Vars in the settings tab. They can also be used locally by setting them as ENV vars.
  pardotLogin
  pardotPassword
  pardotUserKey

In Heroku resources, add the "Heroku Scheduler" add on and add a new job for every 10 minutes "php workers/CountryStateFixer.php".

You can monitor the activity of the script by looking at the logs under the 'More' drop down in the top right.
