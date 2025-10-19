<?php declare(strict_types=1);
namespace Kingsoft\PersistRest;

use Kingsoft\PersistRest\{PersistRequest};
use PHPUnit\Framework\TestCase;

class InvalidPKTest extends TestCase
{
    public function testResourceWithEmptyPrimaryKeyThrowsException()
    {
        // Define test settings with a resource that has an empty primary key
        if (!defined('SETTINGS')) {
            define('SETTINGS', [ 
                'api' => [ 
                    'namespace'        => 'TestSpace\\',
                    'allowedendpoints' => [ 
                        'InvalidPKResource'
                    ],
                    'allowedmethods'   => 'OPTIONS,HEAD,GET,POST,PUT,DELETE',
                    'allowedorigin'    => '*',
                    'skippathparts'    => 0,
                ],
                'db'  => [ 
                    'hostname' => 'localhost',
                    'username' => 'root',
                    'password' => '',
                    'database' => 'test'
                ],
                'log' => [ 
                    'path'  => 'logs',
                    'name'  => 'api',
                    'level' => 'debug'
                ]
            ]);
        }

        // Mock the request to trigger resource validation
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/InvalidPKResource';
        $_SERVER['HTTP_HOST'] = 'localhost';
        
        // Create request
        $request = new PersistRequest(
            SETTINGS['api']['allowedendpoints'],
            SETTINGS['api']['allowedmethods'],
            SETTINGS['api']['allowedorigin']
        );
        
        // Set a null logger to avoid initialization errors
        $request->setLogger(new \Psr\Log\NullLogger());
        
        // Capture output since Response::sendMessage() outputs JSON
        ob_start();
        
        // handleRequest() catches exceptions and sends HTTP response
        // So we test that the request returns false (validation failed)
        $result = $request->handleRequest();
        
        // Get and discard the output
        $output = ob_get_clean();
        
        // When validation fails due to invalid PK, handleRequest should return false
        $this->assertFalse($result, "Request should fail when resource has invalid primary key");
        
        // Verify the output contains the error message about invalid resource
        $this->assertStringContainsString('invalid', $output);
    }
    
    public function testResourceWithValidPrimaryKeyWorks()
    {
        // Use the sample resource which doesn't have getPrimaryKey method (should work)
        if (!defined('SETTINGS')) {
            define('SETTINGS', [ 
                'api' => [ 
                    'namespace'        => 'TestSpace\\',
                    'allowedendpoints' => [ 
                        'sample'
                    ],
                    'allowedmethods'   => 'OPTIONS,HEAD,GET,POST,PUT,DELETE',
                    'allowedorigin'    => '*',
                    'skippathparts'    => 0,
                ],
                'db'  => [ 
                    'hostname' => 'localhost',
                    'username' => 'root',
                    'password' => '',
                    'database' => 'test'
                ],
                'log' => [ 
                    'path'  => 'logs',
                    'name'  => 'api',
                    'level' => 'debug'
                ]
            ]);
        }

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/sample';
        $_SERVER['HTTP_HOST'] = 'localhost';
        
        // This should not throw an exception
        $request = new PersistRequest(
            ['sample'],
            'OPTIONS,HEAD,GET,POST,PUT,DELETE',
            '*'
        );
        
        $this->assertInstanceOf(PersistRequest::class, $request);
    }
}
