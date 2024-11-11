# Composer Smart Autoloader

## Overview

Composer Smart Autoloader is an autoloading solution designed to handle PHP project structures with multiple Composer dependencies. It's useful when multiple `composer.json` files are include the same codebase and the same repo is included with different versions (like a WordPress site with multiple plugins that have a composer.json and vendor folder).

### Purpose

The primary goal of Composer Smart Autoloader is to enhance the reliability and consistency of autoloading in complex PHP applications. It addresses the common issue of "first loaded, first served" in traditional autoloading mechanisms, which can lead to missing features or inconsistent behavior when older versions of packages are loaded instead of newer ones.

## Usage

To use Composer Smart Autoloader in your project, you can simply get the instance and run it:

```php
\Render\Autoload\ClassLoader::getInstance();
```

If you're using this in a WordPress plugin, you can run after all the plugins have loaded

```php
add_action( 'plugins_loaded', function () {
	\Render\Autoload\ClassLoader::getInstance();
}, PHP_INT_MIN );
```

If new vendor packages have been added since you last ran it, you can re-run it using the `run` method:

```php
\Render\Autoload\ClassLoader::getInstance()->run();
```

For example, you many want to run this ClassLoad right away, but then re-run after all plugins are installed

```php
require_once ( __DIR__ ) . '/vendor/autoload.php';

\Render\Autoload\ClassLoader::getInstance();

\add_action( 'plugins_loaded', function () {
	\Render\Autoload\ClassLoader::getInstance()->run();
}, PHP_INT_MIN );
```

This will replace the cached class maps with the classes added since the last run.

> NOTE: `run()` will automatically run just the first time you instantiate the ClassLoader. If you call `getInstance()` a second time, nothing will happen. If you want to re-run setting the class map, you need to call the `run()` method. If you don't want the ClassLoader to automatically execute `run()` at all, set the `$run` parameter to `FALSE` like `\Render\Autoload\ClassLoader::getInstance( FALSE );` on instantiation.

### Dump Autoloading (Optimize)

To ensure dependencies of dependencies are loaded in the same manner, you can run `composer dumpautoload --optimize` for each `composer.json`. This will still work without it, but those additional dependencies might fall back to the default Composer Autoload. If using WordPress, you can do this part of a hook that runs on a plugin install (assuming composer is installed on that server).

If you're only concerned with packages you/your team personally created, then you can make sure to run `composer dumpautoload --optimize` as part of your build process.

## How It Works

1. **Version Analysis**: Composer Smart Autoloader analyzes the versions of all installed packages across different `composer.json` files in the project.
2. **Weight Calculation**: It assigns weights to different versions of the same package, favoring more recent versions.
3. **Smart Loading**: When autoloading classes, it prioritizes loading from the most up-to-date version of a package.
4. **Conflict Resolution**: In case of version conflicts, it attempts to use the latest compatible version, reducing the risk of missing required features.

## Key Features and Benefits

- **Version Resolution**: Attempts to estimate and load the latest version of Composer packages when conflicts arise.
- **Prioritized Loading**: Implements a weight-based system to prioritize loading of more up-to-date package versions.
- **Conflict Resolution**: Resolves conflicts when multiple projects include the same package with different versions.
- **Improved Reliability**: Reduces the chance of missing features by always attempting to load the latest version of a package.
- **Consistency**: Provides a more consistent environment across different parts of a complex application.
- **Flexibility**: Adapts to various project structures and composer setups.
- **Performance**: Optimizes class loading by prioritizing the most relevant and up-to-date sources.