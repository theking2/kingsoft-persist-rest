<?php declare(strict_types=1);
namespace Kingsoft\PersistRest;

use Kingsoft\Http\{Response, Request, StatusCode, ContentType, Rest};
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
    PersistRequest $request,
    \Psr\Log\LoggerInterface $logger
  ) {
    parent::__construct( $request, $logger );
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
   * @return PersistBase
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * side effect: sends a response and exits if the resource is not found
   */
  protected function getResource(): PersistBase
  {
    if( !isset( $this->request->id ) ) {
      $this->logger->info( "Id not set", [ 'ressource' => $this->request->resource ] );
      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendMessage( 'error', 0, 'No id provided' );
      trigger_error( "no id" );
      exit();
    }
    if( $resourceObject = $this->request->resourceClass->newInstance( $this->request->id ) and $resourceObject->isRecord() ) {
      return $resourceObject;

    } else {
      $this->logger->info( "Not found", [ 'ressource' => $this->request->resource, 'id' => $this->request->id ?? '' ] );

      Response::sendStatusCode( StatusCode::NotFound );
      Response::sendContentType( ContentType::Json );
      $payload = [ 
        'result' => 'error',
        'message' => 'Resource not found',
        'resource' => $this->request->resource,
        'id' => $this->request->id ?? '?',
      ];
      Response::sendPayload( $payload );
      exit();
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
      $this->logger->error( "Exception in get()", [ 'ressource' => $this->request->resource ] );

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
    if( $resourceObject = $this->getResource() ) {
      Response::sendStatusCode( StatusCode::OK );
      $payload = $resourceObject->getArrayCopy();
      Response::sendPayload( $payload, [ $resourceObject, "getStateHash" ] );
    }

  }
  /**
   * Get multiple records by criteria
   *
   * @return void
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   */
  function getResourceList( \Generator $resourceGenerator ): void
  {
    $records   = [];
    $keys      = [];
    $row_count = SETTINGS['api']['maxresults'] ?? 10;
    if( $this->request->limit > 0 ) {
      $row_count = $this->request->limit;
    }
    $offset  = $this->request->offset;
    $partial = false;

    foreach( $resourceGenerator as $resourceObject ) {
      // Skip until offset
      if( ( $offset-- ) > 0 ) {
        $this->logger->debug( 'skipping...', [ 'offset' => $offset ] );
        continue;
      }
      if( !$row_count-- ) {
        $partial = true;
        break; // limit the number of rows returned (paging would be nice here) 
      }
      $records[] = $resourceObject->getArrayCopy();
      $keys[]    = $resourceObject->getKeyValue();
    }

    if( count( $keys ) ) {
      $count = count( $keys );
      Response::sendStatusCode( StatusCode::OK );
      // Here we should allow for paging
      header( 'Content-Range: keys ' . $keys[0] . '-' . $keys[ $count - 1 ] );
      $nextPageOffset = $this->request->offset + $this->request->limit;
      $prevPageOffset = $this->request->offset - $this->request->limit;
      $queryArray     = [];
      foreach( $this->request->query as $field => $constraint ) {
        $queryArray[] = $field . '=' . substr( $constraint, 1 );
      }
      $this->logger->debug( 'Query', [ 'queryArray' => $queryArray ] );

      $query = implode( '&', $queryArray );
      if( $query ) {
        $query = '?' . $query;
      }
      $payload = [ 
        'partial' => $partial,
        'first' => $keys[0],
        'last' => $keys[ $count - 1 ],
        'count' => count( $keys ),
        'links' => [ 
          [ 
            'name' => 'single',
            'href' => ( isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . $_SERVER['SERVER_NAME'] . '/' . $this->request->resource . '/${id}',
            'method' => 'GET'
          ],
          [ 
            'name' => 'prev-page',
            'href' => ( isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . $_SERVER['SERVER_NAME'] . '/' . $this->request->resource . "[{$prevPageOffset},{$count}]" . $query,
            'method' => 'GET'
          ],
          [ 
            'name' => 'next-page',
            'href' => ( isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . $_SERVER['SERVER_NAME'] . '/' . $this->request->resource . "[{$nextPageOffset},{$count}]" . $query,
            'method' => 'GET'
          ]
        ],
        'resources' => $records
      ];
      Response::sendPayload( $payload ); //exits
    }

    Response::sendStatusCode( StatusCode::NoContent );
    $payload = [ 'result' => 'none', 'message' => 'No records found' ];
    Response::sendPayload( $payload ); //exits
  }
  /**
   * dogetMany - get multiple records by criteria up to MAXROWS
   *
   * @return void
   */
  private function dogetMany()
  {
    $where = [];
    foreach( $this->request->query as $key => $value ) {
      $where[ $key ] = urldecode( $value );
    }
    $this->getResourceList( $this->request->resourceClass->getMethod( "findall" )->invoke( null, $where, ) );
  }
  /**
   * Get all records up to MAXROWS
   *
   * @return void
   */
  function doGetAll(): void
  {
    $this->getResourceList( $this->request->resourceClass->getMethod( "findall" )->invoke( null ) );
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

    /** @var \Kingsoft\Persist\Base $resourceObject */
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
    /** @var \Kingsoft\Persist\Base $resourceObject */
    if( $resourceObject = $this->getResource() ) {

      $input = json_decode( file_get_contents( 'php://input' ), true );
      $resourceObject->setFromArray( $input );

      if( $result = $resourceObject->freeze() ) {
        Response::sendStatusCode( StatusCode::OK );
        $payload = [ 'id' => $resourceObject->getKeyValue(), 'result' => $result ];
        Response::sendPayLoad( $payload, [ $resourceObject, "getStateHash" ] );

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
    /**@var \Kingsoft\Persist\Db\DBPersistTrait $resourceObject */
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
    if( $resourceObject = $this->getResource() ) {
      $null = null;
      if( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) and $_SERVER['HTTP_IF_NONE_MATCH'] == $resourceObject->getStateHash() ) {
        Response::sendStatusCode( StatusCode::NotModified );
        Response::sendPayload( $null, [ $resourceObject, "getStateHash" ] );
      }
      Response::sendStatusCode( StatusCode::NoContent );
      Response::sendPayload( $null, [ $resourceObject, "getStateHash" ] );
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
