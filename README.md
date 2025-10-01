# Rate Retrieval Challenge - Laravel (PHP 8.2) Sample

This repository contains a minimal, self-contained Laravel-style package that demonstrates:
- Authentication against the ShipPrimus sandbox (login + refreshtoken).
- Fetching rates for vendor `1901539643`.
- Transforming API results into the requested structure.
- Helper to get the cheapest rate per service level.
- Dockerfile + docker-compose example to run a PHP service.

**Notes**
- This is a sample integration. To use inside a full Laravel application, copy the files under `app/Services`, `app/Http/Controllers`, and `routes/api.php`.
- Requires PHP 8.2.
- Composer dependencies: `guzzlehttp/guzzle`, `firebase/php-jwt`, and `illuminate/support` for helpers.
- Example `.env` values (create in your Laravel project):
  ```
  SHIPPRIMUS_API_BASE=https://sandbox-api.shipprimus.com/api/v1
  SHIPPRIMUS_USERNAME=testDemo
  SHIPPRIMUS_PASSWORD=1234
  SHIPPRIMUS_VENDOR_ID=1901539643
  ```
- To run:
    1. Place files in a Laravel project.
    2. `composer require guzzlehttp/guzzle firebase/php-jwt illuminate/support`
    3. Register `RateController` route: see `routes/api.php`.
    4. Call `GET /api/rates` with optional query parameters (example included in README).
