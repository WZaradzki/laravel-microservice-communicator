<?php

namespace WZaradzki\MicroserviceCommunicator\Tests\Unit;

use Illuminate\Redis\Connections\Connection;
use Mockery;
use WZaradzki\MicroserviceCommunicator\Contracts\AzureServiceBusBroker;
use WZaradzki\MicroserviceCommunicator\Contracts\RedisStreamBroker;
use WZaradzki\MicroserviceCommunicator\MicroserviceCommunicationManager;

it('creates azure broker when azure driver selected', function () {
    $manager = new MicroserviceCommunicationManager('azure', [
        'endpoint' => 'test-endpoint',
        'shared_access_key_name' => 'test-key-name',
        'shared_access_key' => 'test-key',
    ]);

    $broker = get_private_property($manager, 'broker');

    expect($broker)->toBeInstanceOf(AzureServiceBusBroker::class);
});

it('creates redis broker with connection', function () {
    // Create Redis connection mock
    $redisMock = Mockery::mock(Connection::class);

    // Create manager
    $manager = new MicroserviceCommunicationManager('redis', [
        'group_name' => 'test-group',
        'redis_connection' => $redisMock // Pass mock connection
    ]);

    $broker = get_private_property($manager, 'broker');

    expect($broker)->toBeInstanceOf(RedisStreamBroker::class);
});

it('throws exception for invalid driver', function () {
    new MicroserviceCommunicationManager('invalid', []);
})->throws(\InvalidArgumentException::class);