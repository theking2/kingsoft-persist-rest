# Kingsoft / Persist REST

This package uses \Kingsoft\Http, \Kingsoft\PersistDb to expose all the tables and views discovered by PersistDb `discover.php` and of those the ones added to the `allowedEndPoints` list. If it is not on that list the api will return a `404`. So
other data is save. A table or view is accessible with, GET, POST, PUT, DELETE reqeests but those can also be restricted using `allowedMethods`. The reqeust follow the [rfc9205](https://www.rfc-editor.org/rfc/rfc9205.html) standard with some extensions. This module is `CORS` complient and can allow or disallow certain methods and communicate that fact by properly responding to a `OPTION` request. The resulting service it `HATEOAS` enabled as it provides uris for pagination and other functions. 

## Methods

 * POST `https://example.com/resource` will create a new record with the values specifed in the json payload. If the record has autoincrment true a new ID is created and returned (C)
 * GET `https://example.com/resource` gets a list of all resources restricted by `maxresults` (R)
 * GET `https://example.com/resource[n,c]` gets a list of resource starting with position `n` and limited by `c`. The response will include links for pagination. (R)
 * GET `https://example.com/resource/<id>` get the record with `id` specified (R)
 * GET `https://example.com/resource?<key>=<value>` gets all the recources where attribute `key` = `value`. Combine mutliples with `&` (R)
 * GET `https://example.com/resource?<key>=!<value>` gets all the recources where attribute `key` != `value`. (R)
 * GET `https://example.com/resource?<key>=U<value1>,<value2>` gets all the recources where attribute `key`  IN  (`value1`, `value2`) other operators are avaiable. See \Kingsoft\Persist for those (R)
 * PUT `https://example.com/resource/<id>` will set new values specifed in the json payload (U)
 * DELETE `https://example.com/resource/<id>` deletes the resource with the `id` specified (D)

So the facade creates a complete CRUD interface for all resources (tables, views) exposed. Tables and views can be discoverd using the "discover" feature from `\Kingsoft\Persist`. This will allow to have full access to tables and read access to views or even stored procedures if they produce a result set. 

## Results

GET results are stored in a `resources` object in the json response allongside links and other messages to make a semi level 3. The results can be a single or multiple objects up to the maximum set. The response will include the urls to implement a pagination.

## HATEOAS

Results contain URIs for pagination and other navigation.

## Pre flight

Pre-flight is handled by observing the OPTION method and returning the proper hints where the caching can be specified with the `[api][maxage]`setting:

* Access-Control-Allow-Headers: Content-Type
* Access-Control-Allow-Origen
* Access-Control-Request-Method
* Origin

[CORS](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)


## Sample usage

```php

use Kingsoft\Http\{StatusCode, Response};
use Kingsoft\PersistRest\{PersistRest, PersistRequest};

try {
  $request = new PersistRequest(
    ['Test', 'TestView'],
    "GET, POST",
    "client.example.com",
  );
  $request->setLogger( LOG );
  $api = new PersistRest( $request, LOG );
  $api->handleRequest();
} catch ( Exception $e ) {
  Response::sendError( $e->getMessage(), StatusCode::InternalServerError->value );
}
```

# Discover

To discover the tables (and views) in you database use this `discover.php` in order to create the class files for access. Make sure the DB connection is made in `config.php` and the `SETTINGS` global array constant is defined. The class `\Kingsoft\Db\Database` needs the following configuration:

```php
const SETTINGS = [ 
    'api' => [ 
        'namespace' => 'kingsoft\\api'
    ],
    'db'  => [ 
        'hostname' => 'localhost',
        'username' => 'root',
        'password' => 'password',
        'database' => 'database'
    ]
];
```

where `namespace` can be adjusted. Discovery can than commence with:

```php
<?php declare(strict_types=1);

require_once 'config/config.php';
const ROOT = __DIR__ . '/';
require 'vendor/autoload.php';

use \Kingsoft\Persist\Db\Bootstrap;
$bootstrap = new Bootstrap( SETTINGS['api']['namespace'] );
$bootstrap->discover();
```

This will generate the fils in the discovery folder that can be copied to whereever you like as long as you update the `composer.json` file accordingly and issue a `composer dump-autoload` to re-read the class location. Example:

```js
{
  "require": {
    "php": "^8.1.0",
    "psr/log":"^3.0.0",
    "monolog/monolog":"3.5.0",
    "kingsoft/monolog-handler":"^1.0.0",
    "kingsoft/utils":"^2.7.0",
    "kingsoft/persist-db": "^2.8.8",
    "kingsoft/persist-rest": "^2.8.7"
  },
  "autoload": {
    "psr-4": {
      "Organisation\Application\\": "./classes/Organisation/Application/"
    }
  }
}
```

after copying the result of `discovery.php` to the folder `classes/Organisation/Application/` 

It is ofcourse possible to read the constructor parameters from a configuration file.

# Add allowedendpoints
## Kingsoft persist-db
To create the allowed endpoints with `Kingsoft\Persist-db` generate them with
`http://example.com/vendor/kingsoft/persist-db/src/discover/`
Followed by these steps
 * Copy the allowedendpoints settings to the ini file and
 * Copy the psr-4 section to `composer.json`. After that make sure to reload the autoloader with
 * 
```
composer dump-autoload
```

And to use pretty URLs a htacess
```apacheconf
<FilesMatch "\.(?:ini|htaccess)$">
Require all granted
</FilesMatch>

RewriteEngine On
RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -f [OR]

## nginx

```
# nginx configuration by winginx.com

location ~ ^(.*)$ { }

location / {
  rewrite ^(.*)$ /api/index.php;
}
```
RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -d
RewriteRule ^ - [L]
RewriteRule ^ /api/index.php
```
