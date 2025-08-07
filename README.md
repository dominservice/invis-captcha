# Invisible CAPTCHA for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dominservice/invis-captcha.svg?style=flat-square)](https://packagist.org/packages/dominservice/invis-captcha)
[![Total Downloads](https://img.shields.io/packagist/dt/dominservice/invis-captcha.svg?style=flat-square)](https://packagist.org/packages/dominservice/invis-captcha)
[![License](https://img.shields.io/packagist/l/dominservice/invis-captcha.svg?style=flat-square)](https://packagist.org/packages/dominservice/invis-captcha)

*A zero-UI, score-based anti-bot shield (reCAPTCHA v3-style) with **optional** honey-field, 1-px pixel, polyfill-poisoning, dynamic field names, ML scoring and Cloudflare Turnstile fallback.*

## Version Matrix

| Laravel | Supported? | Notes |
|---------|------------|-------|
| **9.x** | ✅ | Requires PHP ≥ 8.1 |
| **10.x**| ✅ | Classic “Kernel + `app/Http`” structure |
| **11.x**| ✅ | *New streamlined structure* (no Kernel by default) |
| **12.x**| ✅ | Identical to 11 — tested with 12.0.0-beta |

If you are **upgrading** an older app to 11/12 you may still keep the classic structure – follow the *≤10* instructions.

---

## ✨ Features
| Module | Purpose | Toggle |
|--------|---------|--------|
| **Invisible scoring** | JS collects signals → server returns JWT with `score ∈ [0-1]`. | always on |
| **Dynamic field names** | Adds random suffixes (e.g. `email_d8e7f3c1`) to fool static parsers. | `dynamic_fields.enabled` |
| **Honey field** | Hidden input ― if filled → instant block. | `honey_field.enabled` |
| **1-px tracking pixel** | Logs real browsers vs. lazy headless fetches. | `track_pixel.enabled` |
| **Polyfill-Poisoning** | Patches browser APIs (e.g. `Canvas.toDataURL()`) to break fingerprint-spoofers. | `polyfill_poison.enabled` |
| **Cloudflare Turnstile fallback** | Shows Turnstile widget only for low scores. | `turnstile.enabled` |
| **Pluggable ML model** | Drop-in JSON model for advanced scoring. | `ml_model.enabled` |

---
## Installation

You can install the package via composer:

```bash
composer require dominservice/invis-captcha
```

After installing, publish the configuration file:

```bash
php artisan vendor:publish --tag="invis"
```

```bash
# generates default thresholds model – only when file doesn't exist
php artisan invis:model:generate

# forces overwrite
php artisan invis:model:generate thresholds --force

# variant with linear weights
php artisan invis:model:generate weights
```

## Configuration

The configuration file `config/invis.php` allows you to customize:

- Secret key for JWT tokens
- Score threshold for bot detection
- Honeypot field settings
- Dynamic field name generation
- Cloudflare Turnstile integration
- Tracking pixel options

Toggle any module with `true` / `false`:

## Framework-specific wiring
### Laravel ≤ 10 (classic structure)
__Register middleware alias__

```php
// app/Http/Kernel.php   (inside the $routeMiddleware array)
'verify.invis' => \Dominservice\Invisible\Middleware\Verify::class,
```
__Protect a route__
```php
Route::post('/contact', ContactController::class)
     ->middleware('verify.invis');
```
###  Laravel ≥ 11 (streamlined structure)
Laravel 11+ uses bootstrap-driven configuration.
```php
// bootstrap/app.php  (excerpt)

use Illuminate\Foundation\Configuration\Middleware;
use Dominservice\Invisible\Middleware\Verify;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        // register as ALIAS
        $middleware->alias([
            'verify.invis' => Verify::class,
        ]);
    })
    ->withRoutes(function () {
        require __DIR__.'/../routes/web.php';
    })
    ->create();
```
Protect routes exactly the same way in `routes/web.php`:
```php
Route::post('/contact', ContactController::class)
     ->middleware('verify.invis');
```

## Basic Usage

### 1. Add the Blade directive to your form

```blade
<form method="POST" action="/submit">
    @csrf
    @invisCaptcha
    
    <!-- Your form fields -->
    <input type="text" name="name">
    <input type="email" name="email">
    
    <button type="submit">Submit</button>
</form>
```

### 2. Protect your routes with the middleware

```php
// In a route file
Route::post('/submit', 'FormController@submit')->middleware('verify.invis');

// Or in a controller
public function __construct()
{
    $this->middleware('verify.invis');
}
```

## How It Works

1. The `@invisCaptcha` directive adds JavaScript that collects user behavior data
2. When the form is submitted, a score is calculated based on:
   - Mouse movements
   - Keyboard usage
   - Time spent on page
   - Other behavioral signals
3. A JWT token with the score is sent with the form
4. The middleware validates the token and rejects suspicious submissions

## Advanced Usage

### Custom Score Threshold

You can specify a custom score threshold for specific routes:

```php
Route::post('/contact', 'ContactController@submit')
    ->middleware('verify.invis:0.7'); // Higher threshold for stricter protection
```

### JavaScript Form Submissions

To use the invisible captcha with JavaScript/AJAX form submissions:

1. Add the Blade directive to your page (outside the form):
```blade
@invisCaptcha
```

2. Add the `data-invis` attribute to your form:
```html
<form id="myForm" data-invis>
    <!-- Your form fields -->
</form>
```

3. In your JavaScript, wait for the token to be injected before submitting:
```javascript
document.getElementById('submitButton').addEventListener('click', async function(e) {
    e.preventDefault();
    
    // Wait for the token to be injected (if not already)
    if (!document.querySelector('input[name="invis_token"]')) {
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    
    const form = document.getElementById('myForm');
    const formData = new FormData(form);
    
    // Send with fetch
    fetch('/your-endpoint', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        // Handle response
    })
    .catch(error => {
        // Handle error
    });
    
    // Or with axios
    // axios.post('/your-endpoint', formData)
    //     .then(response => { /* Handle response */ })
    //     .catch(error => { /* Handle error */ });
});
```

4. Ensure your endpoint is protected with the middleware:
```php
Route::post('/your-endpoint', 'YourController@handle')
    ->middleware('verify.invis');
```

### Cloudflare Turnstile Integration

Enable Turnstile in your config file and add your site and secret keys:

```php
'turnstile' => [
    'enabled' => true,
    'sitekey' => 'your-site-key',
    'secret' => 'your-secret-key',
    'fallback' => 0.30,
],
```

## Testing

```bash
composer test
```

## Security

If you discover any security related issues, please email biuro@dso.biz.pl instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [dominservice](https://github.com/dominservice)
- [All Contributors](../../contributors)