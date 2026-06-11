# Laravel Policy Discovery

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mud-qadm/policy-discovery.svg?style=flat-square)](https://packagist.org/packages/mud-qadm/policy-discovery)
[![Total Downloads](https://img.shields.io/packagist/dt/mud-qadm/policy-discovery.svg?style=flat-square)](https://packagist.org/packages/mud-qadm/policy-discovery)
[![PHP Version](https://img.shields.io/packagist/php-v/mud-qadm/policy-discovery.svg?style=flat-square)](https://packagist.org/packages/mud-qadm/policy-discovery)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x%20%7C%2013.x-blue?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/mud-qadm/policy-discovery.svg?style=flat-square)](https://packagist.org/packages/mud-qadm/policy-discovery)

Automatically discover and register Laravel policies recursively – even in deeply nested directories or modular domain structures.

## 📋 Table of Contents

- [The Problem](#-the-problem)
- [Solution](#-solution)
- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [Artisan Commands](#artisan-commands)
- [How It Works](#how-it-works)
- [Caching & Performance](#-caching--performance)
- [Troubleshooting](#-troubleshooting)
- [License](#-license)

---

## 🔥 The Problem

Laravel's native policy auto-discovery works perfectly for flat directory structures:

```text
app/Policies/
├── UserPolicy.php
├── PostPolicy.php
└── CommentPolicy.php
```

But real-world applications often grow beyond that. When you organise policies by domain or module:

```text
app/
└── Policies/
    ├── Settings/
    │   ├── RolePolicy.php
    │   └── PermissionPolicy.php
    ├── HR/
    │   └── EmployeePolicy.php
    └── Finance/
        └── InvoicePolicy.php
```

Laravel **does not** recursively scan subdirectories. This forces you to manually register every policy using `Gate::policy()` – a tedious and error-prone process.

---

## 💡 Solution

This package recursively scans any number of policy directories, automatically maps policies to their corresponding models, and registers them with Laravel's Gate – **without any manual intervention**.

- ✅ Recursively discovers policies in nested folders  
- ✅ Maps policies to models using naming conventions or explicit annotations  
- ✅ Works with Laravel's package auto-discovery  
- ✅ Includes file-based caching for production performance  
- ✅ Provides artisan commands for inspection and cache management  

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| **Recursive discovery** | Finds policies in any subdirectory under configured paths |
| **Automatic model mapping** | Infers model from policy name or uses `@model` annotation |
| **Modular support** | Perfect for DDD, modules, or domain-driven structures |
| **Zero configuration** | Drop-in installation, works out of the box |
| **Performance caching** | File-based cache to avoid filesystem scanning on every request |
| **CLI tools** | List, validate, warmup, clear, rebuild, and optimise caches |
| **Export mappings** | Export discovered policy–model mappings to JSON |
| **Diagnostics** | Statistics and validation commands to ensure correct setup |
| **Automatic policy detection** | Newly created policies are discovered automatically without manual registration |

---

## 🧰 Requirements

- PHP 8.1 or higher
- Laravel 10.x, or higher

---

## 📦 Installation

Install via Composer:

```bash
composer require mud-qadm/policy-discovery
```

Laravel will automatically register the package's service provider thanks to [Laravel's package discovery](https://laravel.com/docs/packages#package-discovery).

To publish the configuration file:

```bash
php artisan vendor:publish --tag=policy-discovery-config
```

This will create `config/policy-discovery.php` where you can customise directories, caching behaviour, and naming conventions.

---

## ⚙️ Configuration

After publishing, you can modify the following options in `config/policy-discovery.php`:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Policy Base Directory
    |--------------------------------------------------------------------------
    | Root directory where all policy classes are located.
    */
    'policy_directory' => app_path('Policies'),

    /*
    |--------------------------------------------------------------------------
    | Policy Namespace
    |--------------------------------------------------------------------------
    | Base namespace used to resolve policy classes.
    */
    'policy_namespace' => 'App\\Policies',

    /*
    |--------------------------------------------------------------------------
    | Model Directories
    |--------------------------------------------------------------------------
    | Directories used for automatic model resolution when mapping policies.
    */
    'model_directories' => [
        app_path('Models'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recursive Scanning
    |--------------------------------------------------------------------------
    | When enabled, policies inside nested directories will be discovered.
    */
    'recursive' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache Enabled
    |--------------------------------------------------------------------------
    | Enable or disable caching of discovered policy mappings.
    */
    'cache_enabled' => env('POLICY_DISCOVERY_CACHE', true),

    /*
    |--------------------------------------------------------------------------
    | Cache File Path
    |--------------------------------------------------------------------------
    | File used to store cached policy-model mappings.
    */
    'cache_file' => storage_path('policy-discovery/mapping.json'),

];
```

---

## 🚀 Usage

Once installed, simply place your policy files anywhere under the configured directories.

### Example

**Policy file:** `app/Policies/Settings/RolePolicy.php`

```php
<?php

namespace App\Policies\Settings;

use App\Models\Settings\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool { /* ... */ }
    public function view(User $user, Role $role): bool { /* ... */ }
    public function update(User $user, Role $role): bool { /* ... */ }
    public function delete(User $user, Role $role): bool { /* ... */ }
}
```

The package will automatically:

1. Discover `RolePolicy` recursively.
2. Map it to `App\Models\Settings\Role` (using convention: `RolePolicy` → `Role` model).
3. Register it with Laravel's Gate.

You can now use Laravel's authorisation methods as usual:

```php
$user->can('view', $role);
Gate::allows('update', $role);
```

No additional service provider code or manual `Gate::policy()` calls are needed.

---

<a id="artisan-commands"></a>
## 🛠️ Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan policy-discovery:list` | Display all discovered policy–model mappings |
| `php artisan policy-discovery:check` | Validate that mapped models and policies exist and are loadable |
| `php artisan policy-discovery:warmup` | Generate the cache without clearing existing cache |
| `php artisan policy-discovery:clear-cache` | Delete the cached policy mappings |
| `php artisan policy-discovery:rebuild` | Clear and regenerate the cache |
| `php artisan policy-discovery:optimize` | Optimise the cached mapping structure (e.g., sort, deduplicate) |
| `php artisan policy-discovery:stats` | Show statistics (total policies, models, directories scanned, cache age) |
| `php artisan policy-discovery:export` | Export all discovered mappings as JSON (useful for debugging or CI) |

---

<a id="how-it-works"></a>
## ⚙️ How It Works

1. **Scanning** – On the first request (or when the cache is empty), the package recursively scans the configured directories for PHP files ending with `*Policy.php`.

2. **Model Resolution** – For each discovered policy, the package determines the target model using the following strategies (in order):

   * **PHP Attributes** using `#[PolicyFor(...)]`
   * **DocBlock annotations** using `@model`
   * **Naming conventions** (`RolePolicy` → `Role`)

### 🎯 Explicit Policy Mapping (Attributes)

You can explicitly define which model a policy belongs to using PHP Attributes:

```php
use MudQadm\PolicyDiscovery\Attributes\PolicyFor;

#[PolicyFor(User::class)]
class UserPolicy
{
    //
}
```

This is especially useful when the policy name does not directly match the model name or when working with custom domain structures.

3. **Registration** – Each discovered policy–model pair is automatically registered with Laravel's Gate using `Gate::policy()`.

4. **Caching** – Discovered mappings are cached to avoid unnecessary filesystem scans and improve performance.

5. **Automatic Refresh** – When a new policy is generated using:

```bash
php artisan make:policy
```

the package automatically refreshes its mappings, ensuring the newly created policy is available without requiring manual cache rebuilds.

You can customise the discovery paths, namespaces, and mapping behaviour through the configuration file.

---

## 🧠 Caching & Performance

* Policy mappings are cached to minimise filesystem operations.
* Cache is automatically invalidated when policy files are added, removed, or modified.
* Newly generated policies are detected automatically and included in the mapping cache.
* In production, you may run `php artisan policy-discovery:warmup` after deployment to pre-build the cache.
* Run `php artisan policy-discovery:optimize` to optimise the cached mapping structure.

> ⚠️ Policy Discovery automatically keeps its cache in sync with policy changes, so manual cache rebuilding is rarely required.

---

## 🔍 Troubleshooting

| Issue                                 | Solution                                                                                                                          |
| ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Policy not discovered                 | Ensure the file name ends with `Policy.php`, verify the configured discovery paths, and run `php artisan policy-discovery:check`. |
| Model mapped incorrectly              | Use the `#[PolicyFor(...)]` attribute or add an `@model` annotation.                                                              |
| Cache appears outdated                | Run `php artisan policy-discovery:rebuild` and verify cache directory permissions.                                                |
| Performance issues during development | Disable caching or clear the cache frequently while iterating.                                                                    |

For additional help, open an issue on GitHub.

---

## 📄 License

The MIT License (MIT). See the LICENSE file for details.

---
**Created & maintained by [Ahmed Sabry Hagrs](https://github.com/AhmedSabryH)**
