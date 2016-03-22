# WorkFront API client for Laravel 5.x

Perform all Operations of [WorkFront APIs](https://developers.workfront.com/api-docs/) with Laravel 5.x

## Installation

To get the latest version of `laravel-workfront`, simply add the following line to the require block of your `composer.json` file:
```
"willvincent/laravel-workfront": "dev-master"
```
You'll then need to run `composer install` or `composer update` to download it and have the autoloader updated.

Once added to your laravel project, you will want to add the service provider.
Find the `providers` key in your `config/app.php` file and register the service provider:

```
'providers' => [
    // ...

    willvincent\Workfront\WorkfrontServiceProvider::class,
],
```

Also locate the `Aliases` key in your `config/app.php` file and  register the Facade:

```
'aliases' => [
    // ...

    'Workfront' => willvincent\Workfront\Facades\WorkfrontFacade::class,
],
```

Finally, run `php artisan vendor:publish` to copy the default config into your app's config directory.
Update the config with your proper credentials.

## Usage

```
$client = Workfront::client();
$client->login();  // You can optionally pass username/email and password here, otherwise the values from the config file will be used.

// Fetch all fields for all projects with a status of CUR or PLN, that are less than 100% complete.
$results = $client->search(
  'project',                          // workfront object code
  array(                              // query
    'status' => array('CUR', 'PLN'),
    'status_Mod' => 'in',
    'percentComplete' => 100,
    'percentComplete_Mod' => 'lt'
  ),
  '*'                                 // fields (can also be an array of specific fields)
);

$client->logout();
```

You can find other Operations and API informattion [here](https://developers.attask.com/api-docs/).

## License

Laravel Workfront is licensed under [The MIT License (MIT)](LICENSE.txt).

## Credits

This is based on example code provided by Workfront, as part of their [API Documentation](https://developers.workfront.com/api-docs/code-samples/).



