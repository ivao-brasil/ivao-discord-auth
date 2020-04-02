<p align="right">
<img src="https://ivao.aero/publrelat/branding/svg_logos/br.svg" width="150">
<img align="left" src="https://seeklogo.com/images/D/discord-logo-B02E5FBA04-seeklogo.com.png" width="200">
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
4. Create a .env from example.env and set the following values

## Using

1. You need to visit the /admin endpoint with account registered as admin according step 4 of installation procedure.
2. Automatically, the system will get all roles available at your server. You can create a new rule by clicking at add button and select the role, at sulfix you can use two different options:

3. Set the staff positions that will receive the roles, separated by ":". For example: BR-WM:BR-AWM, or
4. Set "Member" for the users that don't comply with other requirements.