<?php

namespace GlpiPlugin\Inventorymultitenancy\Tests\Units;

use Glpi\Tests\DbTestCase;

/**
 * Tests for the plugin descriptor and lifecycle functions declared in
 * setup.php and hook.php.
 */
final class SetupTest extends DbTestCase
{
    public function testPluginVersion(): void
    {
        $infos = \plugin_version_inventorymultitenancy();

        $this->assertIsArray($infos);
        $this->assertSame('Inventory Multitenancy', $infos['name']);
        $this->assertSame(PLUGIN_INVENTORYMULTITENANCY_VERSION, $infos['version']);
        $this->assertArrayHasKey('author', $infos);
        $this->assertArrayHasKey('license', $infos);
        $this->assertSame('10.0.6', $infos['minGlpiVersion']);
    }

    public function testCheckPrerequisites(): void
    {
        $this->assertTrue(\plugin_inventorymultitenancy_check_prerequisites());
    }

    public function testCheckConfig(): void
    {
        $this->assertTrue(\plugin_inventorymultitenancy_check_config());
    }

    public function testInstall(): void
    {
        $this->assertTrue(\plugin_inventorymultitenancy_install());
    }

    public function testUninstall(): void
    {
        $this->assertTrue(\plugin_inventorymultitenancy_uninstall());
    }

    public function testHookIsRegisteredOnInit(): void
    {
        global $PLUGIN_HOOKS;

        \plugin_init_inventorymultitenancy();

        $this->assertTrue($PLUGIN_HOOKS['csrf_compliant']['inventorymultitenancy']);
        $this->assertSame(
            'plugin_post_process_importentity_rules_inventorymultitenancy',
            $PLUGIN_HOOKS['post_process_import_entity_rules']['inventorymultitenancy']
        );
    }
}
