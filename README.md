# Kingsoft / Persis REST

This packege uses \Kingsoft\Http, \Kingsoft\PersistDb to expose all the tables and views discoverd by PersistDb `discover.php` and of those the ones added to the `allowedEndPoints` list. If it is not on that list the api will return a `404`. So
other data is save. A table or view is accessible with, GET, POST, PUT, DELETE reqeests but those can also be restricted using `allowedMethods`. The reqeust follow the [rfc9205](https://www.rfc-editor.org/rfc/rfc9205.html) standard with some extensions.

 * POST `https://example.com/resource` will create a new record with the values specifed in the json payload. If the record has autoincrment true a new ID is created and returned (C)
 * GET `https://example.com/resource` gets a list of all resources restricted by `maxresults` (R)
 * GET `https://example.com/resource[n,c]` gets a list of resource starting with position `n` and limited by `c`. The response will include links for pagination. (R)
 * GET `https://example.com/resurce/<id>` get the record with `id` specified (R)
 * GET `https://example.com/resurce?<key>=<value>` gets all the recources where attribute `key` = `value`. Combine mutliples with `&` (R)
 * GET `https://example.com/resurce?<key>=!<value>` gets all the recources where attribute `key` != `value`. (R)
 * GET `https://example.com/resurce?<key>=U<value1>,<value2>` gets all the recources where attribute `key`  IN  (`value1`, `value2`) other operators are avaiable. See \Kingsoft\Persist for those (R)
 * PUT `https://example.com/resource/<id>` will set new values specifed in the json payload (U)
 * DELETE `https://example.com/resource/<id>` deletes the resource with the `id` specifie (D)

So the interface creates a complete CRUD interface for all resources (tables, views) exposed. Remember however that views can not normally be updated. So make sure your views include the key for the _main_ underlying table. I have not found out a
way to retrieve the underlying tables (on MySQL, MariaDB) so it is currently not possible to automate this 
GET results are stored in a `resources` object in the json response allongside links and other messages to make a semi level 3. 

Pre-flight is handled by observing the OPTION method and returning the proper hints.


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

Make sure to make the SETTINGS available with 
```ini
[api]
namespace = Namespace
maxresults = 10
allowedorigin = *
allowedmethods = "OPTIONS,HEAD,GET,POST,PUT,DELETE"
maxage = 60
```
# Add allowedendpoints
## Kingsoft persist-db
To create the allowed endpoints with `Kingsoft\Persist-db` generate them with
`http://example.com/vendor/kingsoft/persist-db/src/discover/`
Followed by these steps
 * Copy the allowedendpoints settings to the ini file and
 * Copy the psr-4 section to `composer.json`. After that make sure to reload the autoloader with
```
composer dump-autoload
```
And to use pretty URLs a htacess
```apacheconf
<FilesMatch "\.(?:ini|htaccess)$">
Order allow,deny
Deny from all
</FilesMatch>

RewriteEngine On
RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -f [OR]
RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -d
RewriteRule ^ - [L]
RewriteRule ^ /api/index.php
```
