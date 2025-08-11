<?php

namespace Examples\Client;

use Iafilin\EloquentHttpAdapter\HttpModel;
use Illuminate\Http\Client\PendingRequest;

class Purchase extends HttpModel
{
    protected ?string $apiEndpoint = 'https://api.example.com/purchases';

    protected array $relationClassMap = [
        'user' => \Examples\Client\User::class,
        'items' => \Examples\Client\PurchaseItem::class,
    ];

    public function httpClient(): PendingRequest
    {
        return \Http::asJson()
            ->baseUrl($this->apiEndpoint)
            ->withHeaders(['Authorization' => 'Bearer <token>'])
            ->timeout(30);
    }
}

class User extends HttpModel {}
class PurchaseItem extends HttpModel {}


