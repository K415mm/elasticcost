<?php

namespace App\Http\Client;

use Illuminate\Http\Client\Factory as BaseFactory;
use Illuminate\Http\Client\PendingRequest;

class CustomHttpFactory extends BaseFactory
{
    /**
     * Create a new pending request instance for this factory.
     *
     * @return PendingRequest
     */
    public function newPendingRequest()
    {
        $request = parent::newPendingRequest();

        if (app()->environment('local', 'testing')) {
            $request->withoutVerifying();
        }

        return $request;
    }
}
