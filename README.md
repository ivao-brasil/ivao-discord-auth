<div align="right">
  <img src="https://ivao.aero/publrelat/branding/svg_logos/br.svg" width="150">
  <img align="left" src="https://seeklogo.com/images/D/discord-logo-B02E5FBA04-seeklogo.com.png" width="200">
</div>

## About

This system provide a Discord Validation tool based on IVAO SSO for every IVAO Division. Members log in with their IVAO account, connect their Discord account and are added to the division server with roles and nickname based on their IVAO staff positions. Read the following topics in order to know how install it.

## Requirements

- PHP 8.4 (with `pdo_mysql`, `mbstring`, `openssl`, `curl` and `json`)
- Composer 2
- MySQL 5.7+ / MariaDB 10.3+

The system is built on [Laravel 12](https://laravel.com/docs/12.x); see its documentation for the full server requirements.

## Installation

1. Clone this repository (or upload the source code) and point the web server document root to the `public` folder.
2. Install dependencies with `composer install --no-dev --optimize-autoloader`.
3. Create a `.env` file using `.env.example` as reference and set the following values:
    - `APP_KEY`: generate it with `php artisan key:generate`. **Keep the same key when upgrading**: it is used to decrypt the saved role rules.
    - `APP_URL`: the URL of your application, e.g. `https://discord.br.ivao.aero`.
    - `IVAO_CLIENT_ID` / `IVAO_CLIENT_SECRET`: the credentials of your IVAO SSO application. Register `APP_URL/ivao/callback` as its redirect URI.
    - `DISCORD_CLIENT_ID` / `DISCORD_CLIENT_SECRET`: the credentials of your [Discord Application](https://discord.com/developers/applications). Register `APP_URL/discord/callback` as its OAuth2 redirect URL.
    - `DISCORD_BOT_TOKEN`: the token of the bot of that application.
    - `DISCORD_GUILD_ID`: the ID of your server (right click the server name with Developer Mode on, then Copy ID).
    - `ADMIN_VIDS`: the VIDs of the system admins separated by "**:**", for example `999999:999998`.
    - `LANGUAGE`: the language you want to use, see `Language` topic below.
    - `DEFAULT_TITLE`: the HTML title for pages. Words are separated by `_`, e.g. `IVAO_BR_Discord_Auth_System`.
    - `LOG_DISCORD_WEBHOOK_URL` (optional): a Discord webhook that receives the application logs.
    - `MIN_HOURS` (optional, default `5`): members need more than this many pilot + ATC hours to join.
4. Run `php artisan migrate --force`.
5. Cache the configuration for production: `php artisan optimize`. Run it again after every change to `.env`.

### Deployment

Every push to `main` runs the tests, installs the production dependencies and uploads the application by FTP (`.github/workflows/main.yml`). Configure the `Production` environment of the repository with:

- Secrets: `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD` and `SERVER_DIR` (the application folder, not `public`, ending with `/`)
- Variable: `IS_DEPLOYMENT_ENABLED` set to `true`

`.env` and `storage/` are never uploaded. Run `php artisan migrate --force` on the server when a new migration is added.

### Upgrading from the Laravel 7 version

1. In `.env`, add `IVAO_CLIENT_ID` and `IVAO_CLIENT_SECRET`. Keep `APP_KEY` and `storage/app/roles`.
2. Deploy, then delete the old caches on the server: `rm -f bootstrap/cache/*.php`.
3. Switch the site to PHP 8.4.

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve        # http://localhost:8000
php artisan test
```

For local testing, register `http://localhost:8000/ivao/callback` and `http://localhost:8000/discord/callback` as redirect URIs.

## Notes

- To add the db.division.ivao.aero if it's not added, you can go to Remote MySQL on cPanel/Plesk and add the access Host.

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

To insert new languages you need to make a copy from `lang/en` folder to new folder with prefix of the new language, for example: `lang/fr` for French. After that just edit the file `text.php` and translate the entries of language array.
