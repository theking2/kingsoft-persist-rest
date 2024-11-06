<?php declare(strict_types=1);
use Kingsoft\Http\RestInterface;
// make sure to create  config.ini.php in the root directory
require '../config.inc.php';
require 'RestDummyImplementation.php';

use Kingsoft\Http\{StatusCode, Response, ContentType};
use Kingsoft\PersistRest\{PersistRest, PersistRequest};

try {
  $request = new PersistRequest(
    SETTINGS[ 'api' ][ 'allowedendpoints' ],
    SETTINGS[ 'api' ][ 'allowedmethods' ] ?? 'OPTIONS,HEAD,GET,POST,PUT,DELETE',
    SETTINGS[ 'api' ][ 'allowedorigin' ] ?? '*',
  );
  $logger = new \Monolog\Logger( SETTINGS[ 'log' ][ 'name' ] );
  $request->setLogger( $logger );
  $api = new PersistRest( $request, $logger);

  $api->handleRequest();
} catch ( \Throwable $e ) {
  Response::sendError( $e->getMessage(), StatusCode::InternalServerError, ContentType::Json );
}