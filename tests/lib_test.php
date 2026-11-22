<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace enrol_domain;

/**
 * Tests for email domain self enrolment.
 *
 * @package    enrol_domain
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_domain_plugin
 */
final class lib_test extends \advanced_testcase {
    public function test_email_matching(): void {
        $domains = "school.edu\n Partner.org ";
        $this->assertTrue(\enrol_domain_plugin::email_allowed('ana@school.edu', $domains));
        $this->assertTrue(\enrol_domain_plugin::email_allowed('Ana@STUDENTS.School.edu', $domains));
        $this->assertTrue(\enrol_domain_plugin::email_allowed('bo@partner.org', $domains));
        $this->assertFalse(\enrol_domain_plugin::email_allowed('eve@notschool.edu', $domains));
        $this->assertFalse(\enrol_domain_plugin::email_allowed('eve@school.edu.evil.com', $domains));
        $this->assertFalse(\enrol_domain_plugin::email_allowed('', $domains));
        $this->assertFalse(\enrol_domain_plugin::email_allowed('ana@school.edu', ''));
    }

    public function test_only_matching_users_can_enrol_themselves(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', $GLOBALS['CFG']->enrol_plugins_enabled . ',domain');
        $plugin = enrol_get_plugin('domain');
        $this->assertNotEmpty($plugin);
        $course = $this->getDataGenerator()->create_course();
        $id = $plugin->add_instance($course, ['customtext1' => 'school.edu', 'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $DB->get_field('role', 'id', ['shortname' => 'student'])]);
        $instance = $DB->get_record('enrol', ['id' => $id]);

        $ok = $this->getDataGenerator()->create_user(['email' => 'ana@school.edu']);
        $no = $this->getDataGenerator()->create_user(['email' => 'eve@gmail.com']);

        $this->setUser($ok);
        $this->assertTrue($plugin->can_self_enrol($instance));
        $plugin->enrol_self($instance);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $ok));
        $this->assertNotTrue($plugin->can_self_enrol($instance), 'Already enrolled');

        $this->setUser($no);
        $this->assertIsString($plugin->can_self_enrol($instance));
        $this->expectException(\moodle_exception::class);
        $plugin->enrol_self($instance);
    }

    public function test_disabled_instance_refuses(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', $GLOBALS['CFG']->enrol_plugins_enabled . ',domain');
        $plugin = enrol_get_plugin('domain');
        $course = $this->getDataGenerator()->create_course();
        $id = $plugin->add_instance($course, ['customtext1' => 'school.edu', 'status' => ENROL_INSTANCE_DISABLED]);
        $this->setUser($this->getDataGenerator()->create_user(['email' => 'ana@school.edu']));
        $this->assertNotTrue($plugin->can_self_enrol($DB->get_record('enrol', ['id' => $id])));
    }
}
