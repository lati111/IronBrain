# IronBrain
The publically available repository for the website ironbrain.io, hosting a variety of useful webtools.

## Current modules
- PKSanc

## Running local installation
IronBrain is fully capable of running locally for both development and personal use. Below follows the instruction on how to run it.

### Installation
*Deployment*

1. Download and extract the IronBrain zip file.
2. Run `composer install --no-dev` in a terminal. (make sure [Composer](https://getcomposer.org) is properly installed). 
3. Configure the generated .env to your liking. (make sure to configure the `APP_URL` and database logins properly).
4. Run the `npm install` command in a terminal. (ensure [NPM](https://www.npmjs.com) is properly installed)
5. Run `php artisan migrate` in a terminal. (make sure that your database is running properly). 
6. Run `php artisan db:seed --class=CoreSeeder` in a terminal to create default values.
7. Run `php artisan import:all` in a terminal to populate the database.
8. Run `npm run build` to compile the javascript and css files.
9. Run `php artisan optimize` to cache all the configs. 

*Development*
1. Download and extract the IronBrain zip file.
2. Run `composer install` in a terminal. (make sure [Composer](https://getcomposer.org) is properly installed).
3. Configure the generated .env to your liking. (make sure to configure the `APP_URL` and database logins properly).
4. Make a copy of the .env called `.env.testing`. This should have a seperate database.
5. Run the `npm install` command in a terminal. (ensure [NPM](https://www.npmjs.com) is properly installed)
6. Run `php artisan migrate` in a terminal. (make sure that your database is running properly).
7. Run `php artisan db:seed --class=CoreSeeder` in a terminal to create default values.
8. Run `php artisan import:all` in a terminal to populate the database.
9. Open a terminal and run `php artisan serve`. (keep this terminal open).
10. Open a terminal and run `npm run dev`. (also keep this terminal).

### Testing
After editing the base code of IronBrain, we recommend running the tests to insure everything keeps running smoothly.
- Browser tests: `php artisan dusk`. (a valid installation of [Google Chrome](https://www.google.com/chrome/) must be installed).
- Feature tests: `php artisan test --testsuite=Feature`.
- Unit tests: `php artisan test --testsuite=Unit`. 
