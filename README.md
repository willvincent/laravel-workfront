# AtTask APIs Operataion with Laravel 5

Performe all Operataion of [atTaskApis] (https://developers.attask.com/api-docs/) with Laravel5

## Installation

To get the latest version of Laravel AtTask, simply add the following line to the require block of your `composer.json` file:
```
"h4hardik/attask": "dev-master"
```
You'll then need to run `composer install` or `composer update` to download it and have the autoloader updated , and it's DONE!!!

## Usage

Add Lines in your Controller:<br>

``use \Vendor\AtTask\StreamClient as AtTaskClass;`` // Using Namespace

In your function

` $atTaskObj = new AtTaskClass('https://lexisnexis.attasksandbox.com');`<br>
  `$session = $atTaskObj->login('rohit.chopra@lexisnexis.com', 'l3xisn3xis');`

You can find other Opertaions : [Here] (https://developers.attask.com/api-docs/)

## License

Laravel Exceptions is licensed under [The MIT License (MIT)](LICENSE).

## Credits

This is based on and essentially a highly simplified PHP Class file example of AtTask [API] (https://developers.attask.com/api-docs/code-samples/)

