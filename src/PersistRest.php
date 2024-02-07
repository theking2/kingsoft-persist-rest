<?php declare(strict_types=1);
namespace Kingsoft\PersistRest;

use Kingsoft\Http\Response;
use Kingsoft\Http\StatusCode;
use Kingsoft\Http\ContentType;
use Kingsoft\Http\Rest;
use Kingsoft\Db\DatabaseException;
use Kingsoft\Persist\Base as PersistBase;

/**
 * Implementation of the Rest abstract class backed by a PersistDb Class
 *
 * @return void
 * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
 */
class PersistRest extends Rest
{
  public function __construct(
    readonly PersistRequest $request,
    readonly ?\Psr\Log\LoggerInterface $logger = new \Psr\Log\NullLogger()
  ) {
    try {
      parent::__construct( $request );
    } catch ( DatabaseException $e ) {
      Response::sendStatusCode( StatusCode::InternalServerError );
      Response::sendContentType( ContentType::Json );
      exit( $this->createExceptionBody( $e ) );
    }
  }
  /**
   * Create a JSON string from an exception
   *
   * @param  mixed $e
   * @return string
   */
  protected function createExceptionBody( \Throwable $e ): string
  {
    $this->logger->error( "Exception in PersistRest", [ 'exception' => $e->__toString() ] );
    // don't reveal internal errors
    if( $e instanceof Kingsoft\DB\DatabaseException ) {
      return json_encode( [ 
        'result' => 'error',
        'code' => $e->getCode(),
        'type' => 'DatabaseException',
        'message' => 'Internal error'
      ] );
    }
    return json_encode( [ 
      'result' => 'error',
      'code' => $e->getCode(),
      'type' => get_class( $e ),
      'message' => $e->getMessage(),
    ] );
  }
  /**
   * Get a resource by id
   * @return \Kingsoft\Persist\Base
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * side effect: sends a response and exits if the resource is not found
   */
  protected function getResource(): PersistBase
  {
    if( !isset( $this->request->id ) ) {
      $this->logger->info( "Id not set", [ 'ressource' => $this->request->resourceClass->__toString() ] );
      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendMessage( 'error', 0, 'No id provided' );
      exit;
    }
    if( $resourceObject = $this->request->resourceClass->newInstance( $this->request->id ) and $resourceObject->isRecord() ) {
      return $resourceObject;

    } else {
      $this->logger->info( "Not found", [ 'ressource' => $this->request->resourceClass->__toString(), 'id' => $this->request->id ] );

      Response::sendStatusCode( StatusCode::NotFound );
      Response::sendMessage( 'error', 0, 'Not found' );
      exit;
    }
  }
  /* #region GET */
  public function get(): void
  {
    try {
      $result = [];
      if( $this->request->id ) {
        /* get one element by key */
        $this->doGetOne();
      }
      if( isset( $this->request->query ) and is_array( $this->request->query ) ) {
        /**
         * no key provided, return all or selection
         * paging would be nice here
         */
        $this->doGetMany();

      }
      /**
       * no key provided, return all
       * paging would be nice here
       */
      $this->doGetAll();
    } catch ( \Exception $e ) {
      $this->logger->error( "Exception in get()", [ 'ressource' => $this->request->resourceClass->__toString() ] );

      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendMessage(
        StatusCode::toString( StatusCode::BadRequest ),
        StatusCode::BadRequest->value,
        "Could nor process request, {$e->getMessage()}" );
    }
  }
  /**
   * Get a single record by id
   *
   * @return void
   */
  function doGetOne(): void
  {
    if( $obj = $this->getResource() ) {
      Response::sendStatusCode( StatusCode::OK );
      $payload = $obj->getArrayCopy();
      Response::sendPayload( $payload, [ $obj, "getStateHash" ] );
    }

  }
  /**
   * Get multiple records by criteria
   *
   * @return void
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   */
  function doGetMany(): void
  {
    $records   = [];
    $keys      = [];
    $row_count = SETTINGS['api']['maxresults'] ?? 10;

    $where = [];
    foreach( $this->request->query as $key => $value ) {
      $where[ $key ] = urldecode( $value );
    }

    foreach( $this->request->resourceClass->getMethod( "findall" )->invoke( null, $where ) as $resourceObject ) {
      if( !$row_count-- )
        break; // limit the number of rows returned (paging would be nice here) 
      $records[] = $resourceObject->getArrayCopy();
      $keys[]    = $resourceObject->getKeyValue();
    }

    if( count( $keys ) ) {
      Response::sendStatusCode( StatusCode::OK );
      // Here we should allow for paging
      header( 'Content-Range: ID ' . $keys[0] . '-' . $keys[ count( $keys ) - 1 ] );
      Response::sendPayload( $records );
    }

    Response::sendStatusCode( StatusCode::NoContent );
    exit();
  }

  /**
   * Get all records up to MAXROWS
   *
   * @return void
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   */
  function doGetAll(): void
  {
    $records   = [];
    $keys      = [];
    $row_count = SETTINGS['api']['maxresults'] ?? 10;
    $partial   = false;

    foreach( ( $this->request->resourceClass->getMethod( "findall" )->invoke( null ) ) as $id => $resourceObject ) {
      if( !$row_count-- ) {
        $partial = true;
        break; // limit the number of rows returned (paging would be nice here) 
      }
      $records[] = $resourceObject->getArrayCopy();
      $keys[]    = $resourceObject->getKeyValue();
    }
    if( count( $keys ) ) {
      Response::sendStatusCode( $partial ? StatusCode::PartialContent : StatusCode::OK );
      // Here we should allow for paging
      header( 'Content-Range: ID ' . $keys[0] . '-' . $keys[ count( $keys ) - 1 ] );
      Response::sendPayload( $records );
    }
    Response::sendStatusCode( StatusCode::NoContent );
    exit();
  }
  /* #endregion */

  /* #region POST */

  /**
   * post
   *
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * @return void
   */
  public function post(): void
  {
    $input = json_decode( file_get_contents( 'php://input' ), true );

    /** @var \Kingsoft\Persist\Base $obj */
    $resourceObject = $this->request->resourceClass->getMethod( "createFromArray" )->invoke( null, $input );
    if( $resourceObject->freeze() ) {
      Response::sendStatusCode( StatusCode::OK );
      $payload = [ 
        'result' => 'created',
        'message' => '',
        'id' => $resourceObject->getKeyValue(),
        'ETag' => $resourceObject->getStateHash() ];
      Response::sendPayload( $payload, [ $resourceObject, "getStateHash" ] );
    }

    $this->logger->info( "Error in post", [ 'payload' => $input ] );

    Response::sendStatusCode( StatusCode::InternalServerError );
    Response::sendMessage( 'error', 0, 'Internal error' );
  }

  /* #endregion */

  /* #region PUT */

  /**
   * put
   *
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * @return void
   */
  public function put(): void
  {
    /** @var \Kingsoft\Persist\Base $obj */
    if( $obj = $this->getResource() ) {

      $input = json_decode( file_get_contents( 'php://input' ), true );
      $obj->setFromArray( $input );

      if( $result = $obj->freeze() ) {
        Response::sendStatusCode( StatusCode::OK );
        $payload = [ 'id' => $obj->getKeyValue(), 'result' => $result ];
        Response::sendPayLoad( $payload, [ $obj, "getStateHash" ] );

      }

      $this->logger->info( "Error in put", [ 'payload' => $input ] );

      Response::sendStatusCode( StatusCode::InternalServerError );
      Response::sendMessage( 'error', 0, 'Internal error' );

    }
  }

  /* #endregion */

  /* #region DELETE */
  /**
   * delete
   *
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * @return void
   */
  public function delete(): void
  {
    /**@var \Kingsoft\Persist\Db\DBPersistTrait $obj */
    if( $resourceObject = $this->getResource() ) {
      Response::sendStatusCode( StatusCode::OK );
      $payload = [ 'id' => $resourceObject->getKeyValue(), 'result' => $resourceObject->delete() ];
      Response::sendPayLoad( $payload );
    }
  }

  /* #endregion */

  /* #region HEAD */

  /**
   * head
   *
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * @return void
   */
  public function head(): void
  {
    if( $obj = $this->getResource() ) {
      $null = null;
      if( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) and $_SERVER['HTTP_IF_NONE_MATCH'] == $obj->getStateHash() ) {
        Response::sendStatusCode( StatusCode::NotModified );
        Response::sendPayload( $null, [ $obj, "getStateHash" ] );
      }
      Response::sendStatusCode( StatusCode::NoContent );
      Response::sendPayload( $null, [ $obj, "getStateHash" ] );
    }
    Response::sendStatusCode( StatusCode::NotFound );
    exit();
  }

  /* #endregion */

  /* #region PATCH */
  /**
   *
   */
  public function patch(): void
  {
    $this->put();
  }
  /* #endregion */
}