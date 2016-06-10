<?php

namespace tests\Api\Contact;

use AbuseIO\Models\Contact;
use AbuseIO\Models\User;
use tests\Api\DestroyTestHelper;
use tests\TestCase;

class DestroyTest extends TestCase
{
    use DestroyTestHelper;

    const URL = '/api/d41d8cd98f00b204e8000998ecf8427e/v1/contacts';

    public function initWithValidResponse()
    {
        $user = User::find(1);

        $contact = factory(Contact::class)->create();

        $response = $this->actingAs($user)->call('DELETE', self::getURLWithId($contact->id));

        $this->statusCode = $response->getStatusCode();
        $this->content = $response->getContent();
    }

    private static function getURLWithId($id)
    {
        return sprintf('%s/%s', self::URL, $id);
    }
}