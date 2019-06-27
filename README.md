# dokuwiki-plugin-evesso
Eve Online SSO login plugin for DokuWiki 

### SETUP

1. Put the plugin files in `/lib/plugins/evesso`
1. Open the site in your browser and login on a admin account
1. Go to `admin` > `Configuration Settings`
1. Go to `Authentication` > `authtype` and select `EVESSO`
1. Go to the `Evesso` section and enter your data from your [eve online developer app](https://developers.eveonline.com/applications)
    * `callback` (Callback URL)
    * `eveonline-key` (Client ID)
    * `eveonline-secret` (Secret Key)    
    * :warning: Do not change `singleService` (That will lock you out of the wiki!)
1. Save the settings

#### Disable registration with email (Optional)

1. Check `Evesso` > ` register-on-auth`
1. Uncheck `Authenticatio` > `disableactions` > `Register`
1. Save the settings

#### Disable login with email (Optional)

:warning: This have the potential you permantly lock you out of the wiki, this is a good time to take a backup of the wiki

1. Logout and login via EVESSO on the character you want to be admin
1. Logout and login on a admin account
1. Go to `Admin` > `User Manager`
1. Click the eve character you want for the admin account (It will have `[CharacterID]@eveonline.com` and role `user, eveonline`)
1. Change the groups to `admin,user,eveonline` and click `Save Changes`
1. Logout and login with your new eve online admin account
1. Go to `Admin` > `Configuration Settings`
1. go to `Evesso` > `singleService` select `EveOnline` :warning: This step can lock you out of the wiki. Make sure you're logged in on a eve-online admin account before you continue and have a up-to-date backup of the wiki!
1. Save settings 
