<?php declare(strict_types=1);

const SETTINGS = [
  'api' => [
    'namespace' => 'TestSpace\\',
    'allowedendpoints' => [
      'sample'
    ],
    'allowedmethods' => 'OPTIONS,HEAD,GET,POST,PUT,DELETE',
    'allowedorigin' => '*',
    'skippathparts' => 0, // number of path parts to skip
  ],
  'db' => [
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'sample'
  ],
  'log' => [
    'path' => 'logs',
    'name' => 'api',
    'level' => \Monolog\Logger::DEBUG
  ]
];

