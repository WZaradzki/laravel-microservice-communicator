<?php

namespace WZaradzki\MicroserviceCommunicator\Contracts;

use GuzzleHttp\Client;
use WZaradzki\MicroserviceCommunicator\Brokers\MessageBrokerInterface;
use WZaradzki\MicroserviceCommunicator\Exceptions\BrokerException;


class AzureServiceBusBroker implements MessageBrokerInterface
{
    private Client $client;
    private string $baseUrl;
    private string $sasToken;

    public function __construct(array $config)
    {
        $this->client = new Client();
        $this->baseUrl = $config['endpoint'];
        $this->sasToken = $this->generateSasToken(
            $config['endpoint'],
            $config['shared_access_key_name'],
            $config['shared_access_key']
        );
    }

    private function generateSasToken(string $resourceUri, string $keyName, string $key): string
    {
        $expiry = time() + 3600; // Token valid for 1 hour
        $stringToSign = urlencode($resourceUri) . "\n" . $expiry;
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $key, true));

        return sprintf(
            'SharedAccessSignature sr=%s&sig=%s&se=%s&skn=%s',
            urlencode($resourceUri),
            urlencode($signature),
            $expiry,
            $keyName
        );
    }

    public function publish(string $topic, array $message): bool
    {
        try {
            $response = $this->client->post(
                "{$this->baseUrl}/{$topic}/messages",
                [
                    'headers' => [
                        'Authorization' => $this->sasToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $message,
                ]
            );

            return $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            \Log::error("Azure Service Bus REST API publish error: " . $e->getMessage());
            throw new BrokerException("Failed to publish message: " . $e->getMessage());
        }
    }

    public function subscribe(string $topic, callable $callback): void
    {
        $subscriptionName = 'default';

        while (true) {
            try {
                $response = $this->client->post(
                    "{$this->baseUrl}/{$topic}/subscriptions/{$subscriptionName}/messages/head",
                    [
                        'headers' => [
                            'Authorization' => $this->sasToken,
                        ],
                    ]
                );

                if ($response->getStatusCode() === 204) {
                    sleep(1);
                    continue;
                }

                $message = json_decode($response->getBody()->getContents(), true);
                $lockToken = $response->getHeader('BrokerProperties')[0] ?? null;

                if ($lockToken) {
                    $callback($message);

                    $this->client->delete(
                        "{$this->baseUrl}/{$topic}/subscriptions/{$subscriptionName}/messages/{$lockToken}",
                        [
                            'headers' => [
                                'Authorization' => $this->sasToken,
                            ],
                        ]
                    );
                }
            } catch (\Exception $e) {
                Log::error("Azure Service Bus REST API subscribe error: " . $e->getMessage());
                sleep(5);
            }
        }
    }
}