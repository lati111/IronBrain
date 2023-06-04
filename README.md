# IronBrain
The publically available repository for the website ironbrain.io, hosting a variety of useful webtools.

## Current modules
- None so far

## Running local installation
IronBrain is fully capable of running locally for both development and personal use. Below follows the instruction on how to run it.

### Installation
*first time only:*

- Download and extract the IronBrain zip file.
- Run `composer install` in a terminal. (make sure [Composer](https://getcomposer.org) is properly installed).
- Configure the generated .env to your liking. (make sure to configure the `APP_URL` and database logins properly)
- Run `php artisan migrate` in a terminal. (make sure that your database is running properly).

*every start up:*
- Open a terminal and run `php artisan serve`. (keep this terminal open).
- Open a terminal and run `npm run dev` if you want to develop, or `npm build` for regular use. (also keep this terminal open).

### Testing
After editing the base code of IronBrain, we recommend running the tests to insure everything keeps running smoothly.
- Browser tests: `php artisan dusk`. (a valid installation of [Google Chrome](https://www.google.com/chrome/) must be installed).
- Unit tests: `php artisan test`. 
