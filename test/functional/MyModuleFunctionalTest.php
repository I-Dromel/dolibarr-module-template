<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace test\functional;

use PHPUnit_Extensions_Selenium2TestCase_WebDriverException;

/**
 * Class MyModuleFunctionalTest
 *
 * Requires chromedriver for Google Chrome
 * Requires geckodriver for Mozilla Firefox
 *
 * @fixme Firefox (Geckodriver/Marionette) support
 * @todo Opera linux support
 * @todo Windows support (IE, Google Chrome, Mozilla Firefox, Safari)
 * @todo OSX support (Safari, Google Chrome, Mozilla Firefox)
 *
 * @package test\functional
 */
class MyModuleFunctionalTest extends \PHPUnit_Extensions_Selenium2TestCase
{
    // TODO: move to a global configuration file?
    protected static $base_url = 'http://dev.zenfusion.fr';
    protected static $dol_admin_user = 'admin';
    protected static $dol_admin_pass = 'admin';
    private static $module_id = 500000; // TODO: autodetect?

    public static $browsers = array(
        array(
            'browser' => 'Google Chrome on Linux',
            'browserName' => 'chrome',
            'sessionStrategy' => 'shared',
            'desiredCapabilities' => array()
        ),
        // Geckodriver does not keep the session at the moment?!
        // XPath selectors also don't seem to work
//        array(
//            'browser' => 'Mozilla Firefox on Linux',
//            'browserName' => 'firefox',
//            'sessionStrategy' => 'shared',
//            'desiredCapabilities' => array(
//                'marionette' => true
//            )
//        )
    );

    /**
     * Helper function to select links by href
     *
     * @param $value
     * @return mixed
     */
    protected function byHref($value)
    {
        $anchor = null;
        $anchors = $this->elements($this->using('tag name')->value('a'));
        foreach ($anchors as $anchor) {
            if (strstr($anchor->attribute('href'), $value)) {
                break;
            }
        }
        return $anchor;
    }

    /**
     * Global test setup
     */
    public static function setUpBeforeClass()
    {
    }

    /**
     * Unit test setup
     */
    public function setUp()
    {
        $this->setSeleniumServerRequestsTimeout(3600);
        $this->setBrowserUrl(self::$base_url);
    }

    /**
     * Verify pre conditions
     */
    protected function assertPreConditions()
    {
    }

    /**
     * Handle Dolibarr authentication
     */
    private function authenticate()
    {
        try {
            if ($this->byId('login')) {
                $login = $this->byId('username');
                $login->clear();
                $login->value('admin');
                $password = $this->byId('password');
                $password->clear();
                $password->value('admin');
                $this->byId('login')->submit();
            }
        } catch (PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) {
            // Login does not exist. Assume we are already authenticated
        }
    }

    /**
     * Test enabling developer mode
     */
    public function testEnableDeveloperMode()
    {
        $this->url('/admin/const.php');
        $this->authenticate();
        $main_features_level_path='//input[@value="MAIN_FEATURES_LEVEL"]/following::input[@type="text"]';
        $main_features_level = $this->byXPath($main_features_level_path);
        $main_features_level->clear();
        $main_features_level->value('2');
        $this->byName('update')->click();
        // Page reloaded, we need a new XPath
        $main_features_level = $this->byXPath($main_features_level_path);
        return $this->assertEquals('2', $main_features_level->value(), "MAIN_FEATURES_LEVEL value is 2");
    }

    /**
     * Test enabling the module
     *
     * @depends testEnableDeveloperMode
     */
    public function testModuleEnabled()
    {
        $this->url('/admin/modules.php');
        $this->authenticate();
        $module_status_image_path='//a[contains(@href, "' . self::$module_id . '")]/img';
        $module_status_image = $this->byXPath($module_status_image_path);
        if (strstr($module_status_image->attribute('src'), 'switch_off.png')) {
            // Enable the module
            $this->byHref('modMyModule')->click();
        } else {
            // Disable the module
            $this->byHref('modMyModule')->click();
            // Reenable the module
            $this->byHref('modMyModule')->click();
        }
        // Page reloaded, we need a new Xpath
        $module_status_image = $this->byXPath($module_status_image_path);
        return $this->assertContains('switch_on.png', $module_status_image->attribute('src'), "Module enabled");
    }

    /**
     * @depends testModuleEnabled
     */
    public function testConfigurationPage()
    {
        $this->url('/custom/mymodule/admin/setup.php');
        $this->authenticate();
        return $this->assertContains('mymodule/admin/setup.php', $this->url(), 'Configuration page');
    }

    /**
     * @depends testConfigurationPage
     */
    public function testAboutPage()
    {
        $this->url('/custom/mymodule/admin/about.php');
        $this->authenticate();
        return $this->assertContains('mymodule/admin/about.php', $this->url(), 'About page');
    }

    /**
     * @depends testAboutPage
     */
    public function testAboutPageRendersMarkdownReadme()
    {
        $this->url('/custom/mymodule/admin/about.php');
        $this->authenticate();
        return $this->assertEquals(
            'Dolibarr Module Template (aka My Module)',
            $this->byTag('h1')->text(),
            "Readme title"
        );
    }

    /**
     * @depends testModuleEnabled
     */
    public function testBoxDeclared()
    {
        $this->url('/admin/boxes.php');
        $this->authenticate();
        return $this->assertContains('mybox', $this->source(), "Box enabled");
    }

    /**
     * @depends testModuleEnabled
     */
    public function testTriggerDeclared()
    {
        $this->url('/admin/triggers.php');
        $this->authenticate();
        return $this->assertContains(
            'interface_99_modMyModule_MyTrigger.class.php',
            $this->byTag('body')->text(),
            "Trigger declared"
        );
    }

    /**
     * @depends testTriggerDeclared
     */
    public function testTriggerEnabled()
    {
        $this->url('/admin/triggers.php');
        $this->authenticate();
        return $this->assertContains(
            'tick.png',
            $this
                ->byXPath('//td[text()="interface_99_modMyModule_MyTrigger.class.php"]/following::img')
                ->attribute('src'),
            "Trigger enabled"
        );
    }

    /**
     * Verify post conditions
     */
    protected function assertPostConditions()
    {
    }

    /**
     * Unit test teardown
     */
    public function tearDown()
    {
    }

    /**
     * Global test teardown
     */
    public static function tearDownAfterClass()
    {
    }
}
