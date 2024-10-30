<?php

namespace WZaradzki\MicroserviceCommunicator\Brokers;

interface MessageBrokerInterface
{
    public function publish(string $topic, array $message): bool;
    public function subscribe(string $topic, callable $callback): void;
}