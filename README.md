# FinAvatar for Filament
![finity-labs-fin-avatar](https://github.com/user-attachments/assets/6b8a879c-afe1-4856-b69d-c76188c1c96e)

[![FILAMENT 4.x](https://img.shields.io/badge/FILAMENT-4.x-EBB304?style=flat-square)](https://filamentphp.com/docs/4.x/panels/installation)
[![FILAMENT 5.x](https://img.shields.io/badge/FILAMENT-5.x-EBB304?style=flat-square)](https://filamentphp.com/docs/5.x/panels/installation)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/finity-labs/fin-avatar.svg?style=flat-square)](https://packagist.org/packages/finity-labs/fin-avatar)
[![Tests](https://github.com/finity-labs/fin-avatar/actions/workflows/tests.yml/badge.svg)](https://github.com/finity-labs/fin-avatar/actions/workflows/tests.yml)
[![Code Style](https://github.com/finity-labs/fin-avatar/actions/workflows/style.yml/badge.svg)](https://github.com/finity-labs/fin-avatar/actions/workflows/style.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/finity-labs/fin-avatar.svg?style=flat-square)](https://packagist.org/packages/finity-labs/fin-avatar)
[![License](https://img.shields.io/packagist/l/finity-labs/fin-avatar.svg?style=flat-square)](https://packagist.org/packages/finity-labs/fin-avatar)

A privacy-focused, high-performance avatar provider for Filament V4 and V5.
It generates SVGs locally using a dedicated route, ensuring **zero external requests** (GDPR compliant) and utilizing **browser caching** for instant loads.


## Features
- Zero External APIs: No data sent to ui-avatars.com or Gravatar.

- Smart Initials: Automatically strips "Mr.", "Dr.", "PhD" etc.

- Theme Aware: Uses your Filament Panel's primary color automatically.

- High Performance: SVGs are cached by the browser for 1 year.


## Installation
You can install the package via composer:
```bash
composer require finity-labs/fin-avatar
```
Running the install command will configure the selected panels automatically:
```bash
php artisan finity-labs:install
```

## Usage

Add in AdminPanelProvider.php:

```php
use FinityLabs\FinAvatar\Providers\UiAvatarsProvider;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->defaultAvatarProvider(UiAvatarsProvider::class);
}
```

## Configuration (Optional)

Publish the config file to customize ignored prefixes (like "Mr", "Dr"), default background color and text color:

```php
php artisan vendor:publish --tag=fin-avatar-config
```
:bulb: `Note: Leave default_bg null to use theme color as background.`

## Credits

- [Finity Labs](https://github.com/finity-labs)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
