<?php declare(strict_types=1);
namespace Kingsoft\PersistRest;

use Kingsoft\Http\Request;

readonly class PersistRequest extends Request
{
  public \ReflectionClass $resourceClass;
  /**
   * Handle a GET request, if {id} is provided attempt to retrieve one, otherwise all.
   *
   * @return void
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   */
  protected function getNamespace(): string
  {
    return SETTINGS[ 'api' ][ 'namespace' ];
  }

  public function __construct(
    array $allowedEndpoints,
    string $allowedMethods = 'GET,POST,PUT,DELETE,PATCH,OPTIONS',
    string $allowedOrigin = '*'
  ) {
    parent::__construct(
      $allowedEndpoints,
      $allowedMethods,
      $allowedOrigin,
    );
  }
  protected function isResourceValid(): bool
  {
    if( !parent::isResourceValid() ) {
      return false;
    }
    // test the resource
    try {
      $fullyQualifiedResourceClass = '\\' . $this->getNamespace() . '\\' . $this->resource;
      $this->resourceClass         = new \ReflectionClass(
        $fullyQualifiedResourceClass
      );
      $resourceInstance            = $this->resourceClass->newInstance();
      unset( $resourceInstance );
      $this->logger->debug( 'Instantiated resourceObject successful', [ 'resource' => $fullyQualifiedResourceClass ] );

      return true;

    } catch ( \Exception $e ) {
      $this->logger->info( 'Instantiate resourceObject failure', [ 'resource' => $fullyQualifiedResourceClass, 'error' => $e->getMessage() ] );

      return false;
    }
  }
  public function newResource( mixed ...$args ): object
  {
    return $this->resourceClass->newInstance( $args[0] );
  }
  public function getResourceMethod( string $name ): \ReflectionMethod
  {
    return $this->resourceClass->getMethod( $name );
  }
  public function getResourceClass(): \ReflectionClass
  {
    return $this->resourceClass;
  }
}
