# ⚙ Laravel Config To Env

This package provide an Artisan command that replaces all variables in a 
Laravel config file with calls to `env()`.

## Installation

You can then run the following Composer command to install this package.

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
