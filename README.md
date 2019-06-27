# dokuwiki-plugin-evesso
Eve Online SSO login plugin for DokuWiki 

## Lisence
[GNU General Public License v2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

Original code from: [OAuth](https://github.com/cosmocode/dokuwiki-plugin-oauth) and [PHPoAuthLib](https://github.com/Lusitanian/PHPoAuthLib)

## Setup

#### Required Setup Steps
1. Put the plugin files in `/lib/plugins/evesso`
1. Open the wiki in your browser
1. Login on a admin account
1. Go to `admin` > `Configuration Settings`
1. Go to `Authentication` > `authtype` and select `EVESSO`
1. Go to the `Evesso` section and enter your data from your [eve online developer app](https://developers.eveonline.com/applications)
    * `callback` (Callback URL)
    * `eveonline-key` (Client ID)
    * `eveonline-secret` (Secret Key)    
    * :warning: Do **not** change `singleService` (That will lock you out of the wiki)
1. Save the settings

#### Disable registration with email (Optional)

1. Open the wiki in your browser
1. Login on a admin account
1. Check `Evesso` > ` register-on-auth`
1. Go to `Authenticatio` > `disableactions`
1. Check `Register` and `Update Profile`
1. Save the settings

#### Disable login with email (Optional)

:warning: This have the potential you permantly lock you out of the wiki. It is a good idea [backup the wiki](https://www.dokuwiki.org/faq:backup) before you continue. 

1. Open the wiki in your browser
1. Logout *(if you're currently logged in)*
1. Login via EVESSO on the character you want to be admin
1. Logout
1. Login on a admin account
1. Go to `Admin` > `User Manager`
1. Click the eve character you want for the admin account
1. Add the group `admin` and click `Save Changes`
1. Logout and login with your new eve online admin account
1. Go to `Admin` > `Configuration Settings`
1. go to `Evesso` > `singleService` and select `EveOnline`  
:warning: This step can lock you out of the wiki. Make sure you're logged in on a EVESSO admin account and have a up-to-date backup of the wiki before you continue
1. Save settings 
