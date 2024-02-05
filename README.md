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

# add allowedendpoints
```
And to use pretty URLs a htacess
```conf
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
