<?php

namespace SilverStripe\Versioned\Tests\ChangeSetItemTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class EndObject extends DataObject implements TestOnly
{
    use Permissions;

    private static $table_name = 'ChangeSetItemTest_End';

    private static $db = [
        'Baz' => 'Int',
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
