# Laravel config to env

This package provide an Artisan command that replaces all variables in a 
Laravel config file with calls to env().

## Installation

As this package is currently private, you will first have to add this to the bottom of your project's `composer.json` file.

```json
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:LFSoftware/unleashed-api-client-php.git"
    }
]
```

You can then run the following Composer commadn to install this package.

```bash
composer require langleyfoxall/laravel-config-to-env
```

## Usage

```bash
# Run on a single config file
php artisan config:config-to-env "config/queue.php"

# Run on all config files
php artisan config:config-to-env "config/*.php"
```
