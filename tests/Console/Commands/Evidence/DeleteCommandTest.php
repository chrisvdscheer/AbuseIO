<?php

namespace tests\Console\Commands\Evidence;

use Illuminate\Support\Facades\Artisan;
use \TestCase;

/**
 * Class DeleteCommandTest
 * @package tests\Console\Commands\Evidence
 */
class DeleteCommandTest extends TestCase
{

// TODO not working because seeder is not deleting and id=1 is in te schema.

//    public function testValid()
//    {
//        $exitCode = Artisan::call('account:delete', [
//            "--id" => "2"
//        ]);
//
//        $this->assertEquals($exitCode, 0);
//        $this->assertContains("The account has been deleted from the system", Artisan::output());
//        /**
//         * I use the seeder to re-initialize the table because Artisan:call is another instance of DB
//         */
//        //$this->seed('AccountsTableSeeder');
//    }

    public function testInvalidId()
    {
        $exitCode = Artisan::call(
            'evidence:delete',
            [
                'id' => '1000'
            ]
        );

        $this->assertEquals($exitCode, 0);
        $this->assertContains("Unable to find evidence", Artisan::output());
    }
}