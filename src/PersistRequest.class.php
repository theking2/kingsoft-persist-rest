<?php declare(strict_types=1);
namespace Kingsoft\Persist;

use Kingsoft\Http\Request;

class PersistRequest extends Request
{
  public null|\ReflectionClass $resourceClass = null;
  /**
   * Handle a GET request, if {id} is provided attempt to retrieve one, otherwise all.
   *
   * @return void
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   */
  protected function getNamespace(): string
  {
    return SETTINGS['api']['namespace'];
  }

  public function __construct(
    array $allowedEndpoints,
    ?string $allowedMethods = 'GET,POST,PUT,DELETE,PATCH,OPTIONS',
    ?string $allowedOrigin = '*',
    ?int $maxAge = 86400,
    ?\Psr\Log\LoggerInterface $log = new \Psr\Log\NullLogger(),
  ) {
    parent::__construct(
      $allowedEndpoints,
      $allowedMethods,
      $allowedOrigin,
      300,
      $log
    );

  }
  protected function isResourceValid(): bool
  {
    if( !parent::isResourceValid() ) {
      return false;
    }
    // test the resource
    try {
      $this->resourceClass = new \ReflectionClass(
        $this->getNamespace() . '\\' .
        $this->resource
      );
      $resourceInstance    = $this->resourceClass->newInstance();
      unset( $resourceInstance );
      $this->log->debug( 'Successful instantiated resourceObject', [ 'resource' => $this->resource ] );
      return true;

    } catch ( \Exception $e ) {
      $this->log->info( 'Could not instansiate resourceObject', [ 'resource' => $this->resource ] );
      return false;

    }
  }
}