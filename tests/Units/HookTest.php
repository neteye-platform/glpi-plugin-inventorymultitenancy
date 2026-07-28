<?php

namespace GlpiPlugin\Inventorymultitenancy\Tests\Units;

use Glpi\Tests\DbTestCase;

/**
 * Tests for the entity-enforcement hook implemented in setup.php:
 * plugin_post_process_importentity_rules_inventorymultitenancy().
 *
 * The hook receives the result of GLPI's entity import rules and must guarantee
 * that every imported asset stays inside the active entity of the session
 * performing the import (or one of its sub-entities).
 */
final class HookTest extends DbTestCase
{
    /**
     * Invoke the plugin hook with a given rules result and a spy asset object.
     */
    private function callHook(array $rulesTargetEntity, MainAssetSpy $spy): void
    {
        $params = (object) [
            'mainAssetObj'      => $spy,
            'rulesTargetEntity' => $rulesTargetEntity,
        ];

        \plugin_post_process_importentity_rules_inventorymultitenancy($params);
    }

    /**
     * A resolution pointing to a sub-entity of the active entity must be kept
     * as-is (legitimate placement into a child entity).
     */
    public function testSubEntityResolutionIsAccepted(): void
    {
        $this->login();
        $this->setEntity('_test_root_entity', true);

        $child_id = getItemByTypeName('Entity', '_test_child_1', true);

        $spy = new MainAssetSpy();
        $this->callHook(['entities_id' => $child_id], $spy);

        $this->assertFalse(
            $spy->called,
            'A sub-entity resolution must be accepted without overriding the target entity'
        );
    }

    /**
     * A resolution pointing to the active entity itself must be kept as-is.
     */
    public function testSameEntityResolutionIsAccepted(): void
    {
        $this->login();
        $root_id = getItemByTypeName('Entity', '_test_root_entity', true);
        $this->setEntity('_test_root_entity', true);

        $spy = new MainAssetSpy();
        $this->callHook(['entities_id' => $root_id], $spy);

        $this->assertFalse($spy->called);
    }

    /**
     * A resolution pointing to an entity outside the active entity (a sibling
     * that is not a descendant) must be forced back to the active entity.
     */
    public function testForeignEntityResolutionIsForcedToActiveEntity(): void
    {
        $this->login();
        $child1_id = getItemByTypeName('Entity', '_test_child_1', true);
        $child2_id = getItemByTypeName('Entity', '_test_child_2', true);
        $this->setEntity('_test_child_1', true);

        $spy = new MainAssetSpy();
        $this->callHook(['entities_id' => $child2_id], $spy);

        $this->assertTrue($spy->called, 'A foreign entity resolution must be overridden');
        $this->assertEquals($child1_id, $spy->entities_id);
    }

    /**
     * An empty resolution (no entity resolved by the rules) must fall back to
     * the active entity.
     */
    public function testEmptyEntityResolutionIsForcedToActiveEntity(): void
    {
        $this->login();
        $root_id = getItemByTypeName('Entity', '_test_root_entity', true);
        $this->setEntity('_test_root_entity', true);

        $spy = new MainAssetSpy();
        $this->callHook(['entities_id' => 0], $spy);

        $this->assertTrue($spy->called);
        $this->assertEquals($root_id, $spy->entities_id);
    }

    /**
     * The root entity (id 0) is a valid active entity and must not trigger the
     * safety exception.
     */
    public function testRootActiveEntityDoesNotThrow(): void
    {
        $this->login();
        $_SESSION['glpiactive_entity'] = 0;

        $spy = new MainAssetSpy();
        $this->callHook(['entities_id' => 0], $spy);

        $this->assertTrue($spy->called);
        $this->assertEquals(0, $spy->entities_id);
    }

    /**
     * When no active entity can be determined for the session (and it is not
     * the root entity), the import must be aborted with an exception rather
     * than risk placing the asset in the wrong tenant.
     */
    public function testMissingActiveEntityThrows(): void
    {
        $this->login();
        $_SESSION['glpiactive_entity'] = null;

        $spy = new MainAssetSpy();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PANIC!');
        $this->callHook(['entities_id' => 0], $spy);
    }
}

/**
 * Lightweight test double standing in for the inventory MainAsset object.
 *
 * The hook only ever calls setEntityID() on the asset, so the spy records
 * whether the enforcement path was taken and which entity was applied.
 */
class MainAssetSpy
{
    public bool $called = false;

    public mixed $entities_id = null;

    public function setEntityID($entities_id): self
    {
        $this->called = true;
        $this->entities_id = $entities_id;

        return $this;
    }
}
