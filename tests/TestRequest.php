<?php declare(strict_types=1);
namespace Kingsoft\PersistRest;

use Kingsoft\PersistRest\{PersistRequest};

class TestRequest extends \PHPUnit\Framework\TestCase
{
    public function testRequest()
    {
        $request = new PersistRequest(
            SETTINGS[ 'api' ][ 'allowedendpoints' ],
            SETTINGS[ 'api' ][ 'allowedmethods' ] ?? 'OPTIONS,HEAD,GET,POST,PUT,DELETE',
            SETTINGS[ 'api' ][ 'allowedorigin' ] ?? '*',
        );
        $this->assertInstanceOf( PersistRequest::class, $request );
    }
}

const SETTINGS = [ 
    'api' => [ 
        'namespace'        => 'TestSpace\\',
        'allowedendpoints' => [ 
            'sample'
        ],
        'allowedmethods'   => 'OPTIONS,HEAD,GET,POST,PUT,DELETE',
        'allowedorigin'    => '*',
        'skippathparts'    => 0, // number of path parts to skip
    ],
    'db'  => [ 
        'hostname' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'sample'
    ],
    'log' => [ 
        'path'  => 'logs',
        'name'  => 'api',
        'level' => 'debug'
    ]
];