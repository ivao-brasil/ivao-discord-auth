<p align="right">
<img src="https://ivao.aero/publrelat/branding/svg_logos/br.svg" width="150"> <img align="left" src="https://seeklogo.com/images/D/discord-logo-B02E5FBA04-seeklogo.com.png" width="200">
</p>

## About

This system provide a Discord Validation tool based on IVAO API for every IVAO Division. Read the following topics in order to know how install it.

## Requirements

Once that this tool is based at [Laravel Framework](https://laravel.com/docs/7.x) for PHP, the system requirements is the same requirements of the framework. Please visit Laravel Documentation to discover it.

## Installation

You can transfer the source code to your server using following options:

1. Download it to .zip file and upload it to your server;
2. Use your host tools to clone this repository (remember to get master branch);
3. Install depedencies using composer.
4. Create a .env from example.env and set the following values:
	APP_KEY: You can use  php artisan key:generate for it.
	APP_URL: The url of your application
	DISCORD_CLIENT_ID: The CLIENT ID of your Discord Application (you need to create it at [Discord Developer Portal](https://discordapp.com/developers/applications))
	DISCORD_CLIENT_SECRET: The CLIENT SECRET of your Discord application
	DISCORD_BOT_TOKEN: The ACESS TOKEN for your bot application, remember to register it at your server and give the necessary permission to handle all other roles.
	DISCORD_GUILD_ID: The ID of your server
	ADMIN_VIDS: The VID of system admins separated by ":". For example: "999999:999998"

## Using

1. You need to visit the /admin endpoint with account registered as admin according step 4 of installation procedure.
2. Automatically, the system will get all roles available at your server. You can create a new rule by clicking at add button and select the role, at sulfix you can use two different options:
		Set the staff positions that will receive the roles, separated by ":". For example: BR-WM:BR-AWM, or
		Set "Member" for the users that don't comply with other requirements.