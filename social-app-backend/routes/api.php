<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This file dispatches to per-version route files so that breaking changes
| can be shipped on a new path (e.g. routes/api/v2.php) without touching
| the previous version. The base prefix is set in bootstrap/app.php.
|
*/

require __DIR__.'/api/v1.php';
