<?php

namespace SilverStripe\Versioned\Tests\ChangeSetItemTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class MidObject extends DataObject implements TestOnly
{
    use Permissions;

    private static $table_name = 'ChangeSetItemTest_Mid';

    private static $db = [
        'Bar' => 'Int',
    ];

    private static $has_one = [
        'Base' => BaseObject::class,
        'End' => EndObject::class,
    ];

    private static $owns = [
        'End',
    ];

    private static $cascade_deletes = [
        'End',
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
