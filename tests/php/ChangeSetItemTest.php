<?php

namespace SilverStripe\Versioned\Tests;

use BadMethodCallException;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

class ChangeSetItemTest extends SapphireTest
{
    protected static $fixture_file = 'ChangeSetItemTest.yml';

    protected static $extra_dataobjects = [
        ChangeSetItemTest\VersionedObject::class,
        ChangeSetItemTest\BaseObject::class,
        ChangeSetItemTest\MidObject::class,
        ChangeSetItemTest\EndObject::class
    ];

    /**
     * Automatically publish all objects
     */
    protected function publishAllFixtures()
    {
        $this->logInWithPermission('ADMIN');
        foreach ($this->fixtureFactory->getFixtures() as $class => $fixtures) {
            foreach ($fixtures as $handle => $id) {
                /** @var Versioned|DataObject $object */
                $object = $this->objFromFixture($class, $handle);
                if ($object->hasExtension(Versioned::class)) {
                    $object->publishSingle();
                }
            }
        }
    }

    public function testChangeType()
    {
        $this->logInWithPermission('ADMIN');
        $object = new ChangeSetItemTest\VersionedObject(['Foo' => 1]);
        $object->write();

        $item = new ChangeSetItem(
            [
            'ObjectID' => $object->ID,
            'ObjectClass' => $object->baseClass(),
            ]
        );

        $this->assertEquals(
            ChangeSetItem::CHANGE_CREATED,
            $item->ChangeType,
            'New objects that aren\'t yet published should return created'
        );

        $object->publishRecursive();

        $this->assertEquals(
            ChangeSetItem::CHANGE_NONE,
            $item->ChangeType,
            'Objects that have just been published should return no change'
        );

        $object->Foo += 1;
        $object->write();

        $this->assertEquals(
            ChangeSetItem::CHANGE_MODIFIED,
            $item->ChangeType,
            'Object that have unpublished changes written to draft should show as modified'
        );

        $object->publishRecursive();

        $this->assertEquals(
            ChangeSetItem::CHANGE_NONE,
            $item->ChangeType,
            'Objects that have just been published should return no change'
        );

        // We need to use a copy, because ID is set to 0 by delete, causing the following unpublish to fail
        $objectCopy = clone $object;
        $objectCopy->delete();

        $this->assertEquals(
            ChangeSetItem::CHANGE_DELETED,
            $item->ChangeType,
            'Objects that have been deleted from draft (but not yet unpublished) should show as deleted'
        );

        $object->doUnpublish();

        $this->assertEquals(
            ChangeSetItem::CHANGE_NONE,
            $item->ChangeType,
            'Objects that have been deleted and then unpublished should return no change'
        );
    }

    public function testGetForObject()
    {
        $this->logInWithPermission('ADMIN');
        $object = new ChangeSetItemTest\VersionedObject(['Foo' => 1]);
        $object->write();

        $item = new ChangeSetItem(
            [
            'ObjectID' => $object->ID,
            'ObjectClass' => $object->baseClass(),
            ]
        );
        $item->write();

        $this->assertEquals(
            ChangeSetItemTest\VersionedObject::get()->byID($object->ID)->toMap(),
            ChangeSetItem::get_for_object($object)->first()->Object()->toMap()
        );

        $this->assertEquals(
            ChangeSetItemTest\VersionedObject::get()->byID($object->ID)->toMap(),
            ChangeSetItem::get_for_object_by_id($object->ID, $object->ClassName)->first()->Object()->toMap()
        );
    }

    public function testPublish()
    {
        $this->publishAllFixtures();

        $base = $this->objFromFixture(ChangeSetItemTest\BaseObject::class, 'base');
        $baseID = $base->ID;
        $baseVersionBefore = $base->Version;
        $end = $this->objFromFixture(ChangeSetItemTest\EndObject::class, 'end1');
        $endID = $end->ID;
        $endVersionBefore = $end->Version;

        // Make a lot of changes
        // - ChangeSetItemTest_Base.base modified
        // - ChangeSetItemTest_End.end1 deleted
        // - new ChangeSetItemTest_Mid added
        $base->Foo = 999;
        $base->write();
        $baseVersionAfter = $base->Version;
        $mid = new ChangeSetItemTest\MidObject();
        $mid->Bar = 39;
        $mid->write();
        $midID = $mid->ID;
        $midVersionAfter = $mid->Version;
        $end->delete();

        $baseChange = new ChangeSetItem([
            'ObjectID' => $baseID,
            'ObjectClass' => $base->baseClass()
        ]);
        $baseChange->write();
        $midChange = new ChangeSetItem([
            'ObjectID' => $midID,
            'ObjectClass' => $mid->baseClass()
        ]);
        $midChange->write();
        $endChange = new ChangeSetItem([
            'ObjectID' => $endID,
            'ObjectClass' => $end->baseClass()
        ]);
        $endChange->write();

        // Publish the ChangeSetItems
        $this->logInWithPermission('ADMIN');
        $this->assertTrue($baseChange->canPublish());
        $this->assertTrue($midChange->canPublish());
        $this->assertTrue($endChange->canPublish());
        $baseChange->publish();
        $midChange->publish();
        $endChange->publish();

        // Check version numbers have been updated on the ChangeSetItems
        $this->assertEquals(
            (int)$baseVersionBefore,
            (int)$baseChange->VersionBefore,
            'VersionBefore does not match the version number before the item was edited'
        );
        $this->assertEquals(
            (int)$baseVersionAfter,
            (int)$baseChange->VersionAfter,
            'VersionAfter does not match the version number after the item was edited'
        );
        $this->assertEquals(
            (int)$baseChange->VersionAfter,
            (int)Versioned::get_versionnumber_by_stage(ChangeSetItemTest\BaseObject::class, Versioned::LIVE, $baseID),
            'The live version number for the item does not match VersionAfter'
        );

        // Check version numbers updated correctly for new items published for the first time
        $this->assertEquals(
            0,
            (int)$midChange->VersionBefore,
            'VersionBefore should be 0 for new items'
        );
        $this->assertEquals(
            (int)$midVersionAfter,
            (int)$midChange->VersionAfter,
            'VersionAfter does not match the version number after the item was edited'
        );
        $this->assertEquals(
            (int)$midVersionAfter,
            (int)Versioned::get_versionnumber_by_stage(ChangeSetItemTest\MidObject::class, Versioned::LIVE, $midID),
            'The live version number for the item does not match VersionAfter'
        );

        // Check version numbers updated correctly for items deleted
        $this->assertEquals(
            (int)$endVersionBefore,
            (int)$endChange->VersionBefore,
            'VersionBefore does not match the version number before the item was deleted'
        );
        $this->assertEquals(
            0,
            (int)$endChange->VersionAfter,
            'VersionAfter should be 0 for deleted items'
        );
        $this->assertEquals(
            0,
            (int)Versioned::get_versionnumber_by_stage(ChangeSetItemTest\EndObject::class, Versioned::LIVE, $endID),
            'The item appears not to have been deleted from the live site'
        );

        // Test trying to re-publish is blocked
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage("This ChangeSetItem has already been published");
        $baseChange->publish();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage("This ChangeSetItem has already been published");
        $midChange->publish();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage("This ChangeSetItem has already been published");
        $endChange->publish();
    }

    public function testRevert()
    {
        $this->publishAllFixtures();

        $base = $this->objFromFixture(ChangeSetItemTest\BaseObject::class, 'base');
        $baseID = $base->ID;
        $baseVersionBefore = $base->Version;
        $end = $this->objFromFixture(ChangeSetItemTest\EndObject::class, 'end1');
        $endID = $end->ID;
        $endVersionBefore = $end->Version;

        // Make a lot of changes
        // - ChangeSetItemTest_Base.base modified
        // - ChangeSetItemTest_End.end1 deleted
        // - new ChangeSetItemTest_Mid added
        $base->Foo = 999;
        $base->write();
        $mid = new ChangeSetItemTest\MidObject();
        $mid->Bar = 39;
        $mid->write();
        $midID = $mid->ID;
        $end->delete();

        $baseChange = new ChangeSetItem([
            'ObjectID' => $baseID,
            'ObjectClass' => $base->baseClass()
        ]);
        $baseChange->write();
        $midChange = new ChangeSetItem([
            'ObjectID' => $midID,
            'ObjectClass' => $mid->baseClass()
        ]);
        $midChange->write();
        $endChange = new ChangeSetItem([
            'ObjectID' => $endID,
            'ObjectClass' => $end->baseClass()
        ]);
        $endChange->write();

        // Publish the ChangeSetItem
        $this->logInWithPermission('ADMIN');
        $baseChange->publish();
        $midChange->publish();
        $endChange->publish();

        // testPublish() checks that version IDs have been correctly updated on the ChangeSetItems at this point,
        // no need to repeat those assertions here

        // Store version numbers prior to reversion for later assertions
        $baseDraftVersion = Versioned::get_versionnumber_by_stage(
            ChangeSetItemTest\BaseObject::class,
            Versioned::DRAFT,
            $baseID
        );
        $baseLiveVersion = Versioned::get_versionnumber_by_stage(
            ChangeSetItemTest\BaseObject::class,
            Versioned::LIVE,
            $baseID
        );
        $midDraftVersion = Versioned::get_versionnumber_by_stage(
            ChangeSetItemTest\MidObject::class,
            Versioned::DRAFT,
            $midID
        );
        $endLiveVersion = Versioned::get_versionnumber_by_stage(
            ChangeSetItemTest\EndObject::class,
            Versioned::LIVE,
            $baseID
        );

        // Revert the ChangeSetItems
        $this->assertTrue($baseChange->canRevert());
        $this->assertTrue($midChange->canRevert());
        $this->assertTrue($endChange->canRevert());
        $baseChange->revert();
        $midChange->revert();
        $endChange->revert();

        // Check live item has been reverted to correct version
        $this->assertEquals(
            (int)$baseVersionBefore,
            (int)Versioned::get_versionnumber_by_stage(ChangeSetItemTest\BaseObject::class, Versioned::LIVE, $baseID),
            'The live version number does not match the original version number prior to publishing the ChangeSetItem'
        );
        $this->assertEquals(
            (int)$baseDraftVersion,
            (int)$baseChange->VersionBefore,
            'VersionBefore does not match the draft version prior to reverting the ChangeSetItem'
        );
        $this->assertEquals(
            (int)$baseLiveVersion,
            (int)$baseChange->VersionAfter,
            'VersionAfter does not match the live version prior to reverting the ChangeSetItem'
        );

        // Check item has been unpublished
        $this->assertNull(
            Versioned::get_by_stage(ChangeSetItemTest\MidObject::class, Versioned::LIVE)
                ->byID($midID),
            'The item should not exist on the live site'
        );
        $this->assertEquals(
            (int)$midDraftVersion,
            (int)$midChange->VersionBefore,
            'VersionBefore does not match the draft version prior to reverting the ChangeSetItem'
        );
        $this->assertEquals(
            0,
            (int)$midChange->VersionAfter,
            'VersionAfter should be 0 when reverting record creation'
        );

        // Check live item has been restored
        $this->assertEquals(
            (int)$endVersionBefore,
            (int)Versioned::get_versionnumber_by_stage(ChangeSetItemTest\EndObject::class, Versioned::LIVE, $endID),
            'The original item has not been restored to the live stage'
        );
        $this->assertEquals(
            0,
            (int)$endChange->VersionBefore,
            'VersionBefore should be 0 when reverting record deletion'
        );
        $this->assertEquals(
            (int)$endLiveVersion,
            (int)$endChange->VersionAfter,
            'VersionAfter does not match the live version prior to reverting the ChangeSetItem'
        );
    }
}
