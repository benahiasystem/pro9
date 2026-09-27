<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\Api\RetentionController as ApiRetentionController;
use App\Http\Controllers\Tenant\RetentionController as WebRetentionController;
use Tests\TestCase;

class RetentionCreationDisabledTest extends TestCase
{
    public function test_web_form_and_both_store_actions_reject_creation_before_processing(): void
    {
        $web = new WebRetentionController();
        $api = new ApiRetentionController();

        foreach ([$web->create(), $web->store(), $api->store()] as $response) {
            self::assertSame(409, $response->getStatusCode());
            self::assertSame('RETENTION_CREATION_DISABLED', json_decode($response->getContent(), true)['code']);
        }

        self::assertSame([], $web->getMiddleware());
        self::assertSame([], $api->getMiddleware());
    }
}
