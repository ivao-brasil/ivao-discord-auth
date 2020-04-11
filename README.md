<p align="right">
<img src="https://ivao.aero/publrelat/branding/svg_logos/br.svg" width="150"> <img align="left" src="https://seeklogo.com/images/D/discord-logo-B02E5FBA04-seeklogo.com.png" width="200">
</p>

## About

This system provide a Discord Validation tool based on IVAO API for every IVAO Division. Read the following topics in order to know how install it.

## Requirements

Once that this tool is based at [Laravel Framework](https://laravel.com/docs/7.x) for PHP, the system requirements is the same requirements of the framework. Please visit Laravel Documentation to discover it.

## Installation

You can transfer the source code to your server using following options:

1. Download it to .zip file and upload it to your server or
2. Use git clone command to clone this repository (remember to get master branch);

After that, follow the procedure below to put your application working.

1. Install depedencies using composer.
2. Create a .env from example.env and set the following values:

    * `APP_KEY`: You can use  `php artisan key:generate` command to generate it.

        <b>NOTE:</b> You need to have access to the server terminal to use `php artisan key:generate` command. If you don't have it, you can run the command locally in your machine and copy the key generated to your server. 
		
    * `APP_URL`: The url of your application

    * `DISCORD_CLIENT_ID`: The <b>CLIENT ID</b> of your Discord Application (you need to create it at [Discord Developer Portal](https://discordapp.com/developers/applications))

    * `DISCORD_CLIENT_SECRET`: The <b>CLIENT SECRET</b> of your Discord application

    * `DISCORD_BOT_TOKEN`: The <b>ACCESS TOKEN</b> for your bot application, remember to register it at your server and give the necessary permission to handle all other roles.

    * `DISCORD_GUILD_ID`: The <b>ID</b> of your server

    * `ADMIN_VIDS`: The VID of system admins separated by a colon "<b>:</b>". For example: "<b>999999:999998</b>"

    * `LANGUAGE`: The language you want to use, see `Language` topic below.

## Using

1. You need to visit the /admin endpoint with account registered as admin according step 3 of installation procedure.
2. Automatically, the system will get all roles available at your server. You can create a new rule by clicking at add button and select the role, at suffix you can use two different options:

    * Set the staff positions that will receive the roles, separated by "<b>:</b>". For example: `BR-WM:BR-AWM`;
    
	* Set `Member` for the users that don't comply with other requirements, if you want to restrict the server just for staffs, just don't create a rule with this suffix.
3. Once that you have seted all rules for roles assignment, just access the root path of application and enjoy it.

## Language

Actually the system has some languages available according the following list:

* English (en);
* Portuguese - Brazil (pt-Br);
* Spanish (es);

The actual language is seted by env variable `LANGUAGE`.

## Inserting new Languages

To insert new languages you need to make a copy from `resources/lang/en` folder to new folder with preffix of the new language, for example: `resoruces/lang/fr` for French. After that just edit the file `text.php` and translate the entries of language array.
