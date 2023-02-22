<div align="right">
  <img src="https://ivao.aero/publrelat/branding/svg_logos/br.svg" width="150">
  <img align="left" src="https://seeklogo.com/images/D/discord-logo-B02E5FBA04-seeklogo.com.png" width="200">
</div>

## About

This system provide a Discord Validation tool based on IVAO API for every IVAO Division. Read the following topics in order to know how install it.

## Requirements

Once that this tool is based at [Laravel Framework](https://laravel.com/docs/7.x) for PHP, the system requirements is the same requirements of the framework. Please visit Laravel Documentation to discover it.

## Instalation

You can transfer the source code to your server using following options:

1. Download it to .zip file and upload it to your server or
2. Use git clone command to clone this repository (remember to get master branch)

After that, follow the procedure below to put your application working.

1. Install dependencies using composer running `composer install` on your terminal (for cPanel you can access to the terminal going to `Advanced` section on the home page)
2. Create a new .env file using example.env for reference setting the following values
    - `APP_KEY`: You can use `php artisan key:generate` command to generate it.
        - **NOTE:** You need to have access to the server terminal to use `php artisan key:generate` command. If you don't have it, you can run the command locally in your machine and copy the key generated to your server.(follow step 1 for how to reach the terminal on cPanel)
    - `APP_URL`: The url of your application
    - `DISCORD_CLIENT_ID`: the **CLIENT ID** of your Discord Application (you need to create it at [Discord Developer Portal](https://discordapp.com/developers/applications))
    - `DISCORD_CLIENT_SECRET`: The **CLIENT SECRET** of your Discord application
    - `DISCORD_BOT_TOKEN`: The **ACCESS TOKEN** for your bot application, remember to register it at your server and give the necessary permission to handle all other roles.
    - `DISCORD_GUILD_ID`: The **ID** of your server (You can get this ID right clicking the name of your discord server and clicking on Copy ID (It requires to have developer mode turned on))
    - `ADMIN_VIDS`: The VID of system admins separated by a colon "**:**". For example: "**999999:999998**"
    - `LANGUAGE`: The language you want to use, see `Language` topic below.
    - `DEFAULT_TITLE`: The default HTML title for pages. The words is separated by `_` instead of normal space. For example: `IVAO_BR_Discord_Auth_System` will result in a title like `IVAO BR Discord Auth System`

## Notes

- You will need to create a Discord Application in order to get the **CLIENT_ID**. Make sure to add your application path `/discord/callback`, for example `discord.br.ivao.aero/discord/callback`, as redirect URL for OAuth2, otherwise, the Discord Auth will not work correctly.
- To add the db.division.ivao.aero if it's not added, you can go to Remote MySQL on cPanel and add the access Host.

## Using

1. First add the bot to your server (you can use this [permission calculator](https://discordapi.com/permissions.html) to generate the URL (it requires the **CLIENT_ID**))
2. On your discord server set the role that the bot created above all the roles that you can set to all the users (if this step is not done it can cause that the bot cannot set the roles when the user tries to join the server)
3. You need to visit the /admin endpoint with account registered as admin according step 3 of installation procedure.
4. Automatically, the system will get all roles available at your server. You can create a new rule by clicking at add button and select the role, at suffix you can use two different options:

   - Set the staff positions that will receive the roles, separated by "**:**". For example: `BR-WM:BR-AWM`;

   - Set `Member` for the users that don't comply with other requirements, if you want to restrict the server just for staffs, just don't create a rule with this suffix.
5. Once that you have seted all rules for roles assignment, just access the root path of application and enjoy it.

## Language

Actually the system has some languages available according the following list:

- English (en);
- Portuguese - Brazil (pt-Br);
- Spanish (es) (Credits to member: **548746** by the help);

The actual language is seted by env variable `LANGUAGE`.

## Inserting new Languages

To insert new languages you need to make a copy from `resources/lang/en` folder to new folder with preffix of the new language, for example: `resoruces/lang/fr` for French. After that just edit the file `text.php` and translate the entries of language array.
