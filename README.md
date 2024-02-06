## Sample usage
```php
use Kingsoft\Http\StatusCode;
use Kingsoft\PersistRest\PersistRest;
use Kingsoft\PersistRest\PersistRequest;
use Kingsoft\Http\Response;

try {
  $request = new PersistRequest(
    SETTINGS['api']['allowedendpoints'],
    SETTINGS['api']['allowedmethods'],
    SETTINGS['api']['allowedorigin'],
    (int) SETTINGS['api']['maxage']
  );
  $api = new PersistRest( $request );
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
