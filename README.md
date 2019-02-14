# Pardot data corrections by API for Heroku
This project will update the Pardot Country & State values from 2 letter abbreviations to full names. You can now turn on Pardot GeoIP and SFDC Country Picklists and not have all your prospects stuck in the Error Que. Pardot Prospects get the State or Country values updated every 10 minutes if needed.

It is designed to run on Heroku. A 'hobby' level Heroku account is enough to support this code.

This is based on the Pardot API sample code http://developer.pardot.com/#sample-code and the Heroku "Getting Started on Heroku with PHP" https://devcenter.heroku.com/articles/getting-started-with-php guide. 

Following the tenants of https://12factor.net/ the logins are stored in Heroku as Config Vars in the settings tab. They can also be used locally by setting them as ENV vars.
 * pardotLogin
 * pardotPassword
 * pardotUserKey

Reminder, the Pardot API user needs to be set to USA EAST COAST timezone - the same as the Pardot offices as there is a bug in the date/time calculations on the API. It is recomended to create a new 'Marketing' user just for this tool so it's easy to identify when this tool has made changes to the data.

In Heroku resources, add the "Heroku Scheduler" add on and add a new job for every 10 minutes "php workers/CountryStateFixer.php".

You can monitor the activity of the script by looking at the logs under the 'More' drop down in the top right.

## Configuration
Configuration is managed by ENVinronment variables. Minimun required configuration is authentication. All other options 'turn on' features of this script.

### Authentication
The following three items are required for Authentication
 * pardotLogin
 * pardotPassword
 * pardotUserKey

### What to process
We can process either a specific list, or prospects that have had activity or data changes in a given period of time. The tool assumes it's being ran every 10 minutes and will look back 21 minutes to have two chances and applying corrections.
Set one of the folling ENV variables to change this behaviour
 * pardotListID

### Country Correction
Country correction is enabled by declaring the source file for the corrections. This is a .csv file in bad value, good value pairs which is intrepreted in a case insensitive way.
 * countrycorrections=countries_ISOtoEnglish.csv
The code assumes that if the record is synced to the CRM, changing the Country will have no effect as the CRM will overwrite the value. You can force the correction to occur even if the Country field Sync Behaviour is set to be "Use Pardot's Value" or "Use the most recently updated value".
 * forcecountrycorrections=true
#### Available correction files
countries_ISOtoEnglish.csv 2 and 3 letter ISO codes mapped to English full names.

### State Correction
State correction is enabled by declaring the source file for the corrections. This is a .csv file in bad value, good value pairs which is intrepreted in a case insensitive way.
 * statecorrections=states_ISOtoEnglish.csv
The code assumes that if the record is synced to the CRM, changing the State will have no effect as the CRM will overwrite the value. You can force the correction to occur even if the State field Sync Behaviour is set to be "Use Pardot's Value" or "Use the most recently updated value".
 * forcestatecorrections=true
#### Available correction files
states_ISOtoEnglish.csv 2 and 3 letter ISO codes mapped to English full names.

# Heroku Setup
This code is configured for running in a Heroku environment. Other hosting would work fine, including from a local machine or even a raspberryPi.

## Installation
From a Heroku account, create a new app.
![Create new app in upper right dropdown](https://user-images.githubusercontent.com/779440/52234182-acb85100-2886-11e9-86c4-5a778a65e6b5.png "Create new app in upper right dropdown")

Name the app, and select the location you wish to have it exist, America or Europe (for GDPR Export concerns)
![Name the app](https://user-images.githubusercontent.com/779440/52234220-c35ea800-2886-11e9-9e2f-7e7aad1c539b.png "Name the app")

Add a Deployment Method of Github. Connect to the Repository. Your connection user will need to be a member of the repo.
![set up repo and deploy](https://user-images.githubusercontent.com/779440/52234654-c4dca000-2887-11e9-87d8-983003833982.png "set up repo and deploy")

You can choose to Enable Automatic Deploys or not. (security suggests NOT to enable automatic deploys)

Click on Deploy Branch

## Configuration
Configure the app by going to the Settings tab on your Heroku App and click on Reveal Config Vars

![Basic Configuration](https://user-images.githubusercontent.com/779440/52234997-8bf0fb00-2888-11e9-9720-9c06f61719e3.png "Basic Configuration")

Add the configuration varaibles such as the login details and your selected correction files.
![minimum suggestion configuration](https://user-images.githubusercontent.com/779440/52235147-e1c5a300-2888-11e9-9a8f-e551536129cb.png "minimum suggestion configuration")

## Scheduling
We want to set this code up to run every ten minutes. To do that, we need to schedule the code. This means we need to install a scheduler and set up the code to run in it.

### Adding the Heroku Scheduler Add-on
Add the "Heroku Scheduler" add-on and click Provision.
![alt text](https://user-images.githubusercontent.com/779440/52236354-420a1400-288c-11e9-9e50-833804ea0569.png "")
![alt text](https://user-images.githubusercontent.com/779440/52236450-8695af80-288c-11e9-8268-4cc9175217b3.png "")
![alt text](https://user-images.githubusercontent.com/779440/52236492-a036f700-288c-11e9-8a79-c10acdbc5387.png "")

### Configuring Heroku Scheduler
Open the Heroku Scheduler in a new window. Click Add New Job.
![alt text](https://user-images.githubusercontent.com/779440/52236564-cb214b00-288c-11e9-9f73-7751b42fe12f.png "")

Add "php workers/CountryStateFixer.php" to the entry box, and change frequency to "Every 10 minutes".
![alt text](https://user-images.githubusercontent.com/779440/52236630-f73ccc00-288c-11e9-8c47-d851d787d97f.png "")

## Validation
The Heroku Logs are available under the More dropdown in the upper right. This will log various Heroku specific lines that aren't of interest for us, but it will also log an ongoing state of what the app is doing.
![alt text](https://user-images.githubusercontent.com/779440/52235611-3ddcf700-288a-11e9-900c-84cf8988fe3f.png "")

Also available is the 'console' which you can open and use to manually run the code. Type 'bash' (a linux shell) and click run. Next change directories to the workers directory "cd workers/". And then run the code with php "php CountryStateFixer.php"
![alt text](https://user-images.githubusercontent.com/779440/52235824-d1aec300-288a-11e9-882c-323ca85dd592.png "")
 
![alt text]( "")

