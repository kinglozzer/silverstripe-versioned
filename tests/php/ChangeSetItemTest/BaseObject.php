<?php

namespace SilverStripe\Versioned\Tests\ChangeSetItemTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class BaseObject extends DataObject implements TestOnly
{
    use Permissions;

    private static $table_name = 'ChangeSetItemTest_Base';

    private static $db = [
        'Foo' => 'Int',
    ];

    private static $has_many = [
        'Mids' => MidObject::class,
    ];

    private static $owns = [
        'Mids',
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
