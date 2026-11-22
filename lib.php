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

/**
 * Email domain self enrolment.
 *
 * @package    enrol_domain
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Self enrolment for users whose email address is at one of the course's domains.
 *
 * Instance fields: name, status, roleid, customtext1 (allowed domains, one per line).
 *
 * @package    enrol_domain
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_domain_plugin extends enrol_plugin {
    /**
     * Domains from the setting text, lowercased, empty lines dropped.
     *
     * @param string|null $text
     * @return string[]
     */
    public static function parse_domains(?string $text): array {
        $parts = preg_split('/[\s,;]+/', core_text::strtolower((string)$text), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_map(fn($d) => ltrim($d, '@.'), $parts)));
    }

    /**
     * Whether the address is at one of the domains or a subdomain of one.
     *
     * @param string $email
     * @param string|null $domains setting text
     * @return bool
     */
    public static function email_allowed(string $email, ?string $domains): bool {
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }
        $host = core_text::strtolower(substr($email, $at + 1));
        foreach (self::parse_domains($domains) as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }
        return false;
    }

    #[\Override]
    public function get_instance_name($instance) {
        if (!empty($instance->name)) {
            return format_string($instance->name);
        }
        return get_string('pluginname', 'enrol_domain');
    }

    #[\Override]
    public function use_standard_editing_ui() {
        return true;
    }

    #[\Override]
    public function roles_protected() {
        return false;
    }

    #[\Override]
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    #[\Override]
    public function allow_manage(stdClass $instance) {
        return true;
    }

    #[\Override]
    public function show_enrolme_link(stdClass $instance) {
        return $this->can_self_enrol($instance, false) === true;
    }

    #[\Override]
    public function can_add_instance($courseid) {
        return has_capability('enrol/domain:config', context_course::instance($courseid));
    }

    #[\Override]
    public function can_delete_instance($instance) {
        return has_capability('enrol/domain:config', context_course::instance($instance->courseid));
    }

    #[\Override]
    public function can_hide_show_instance($instance) {
        return has_capability('enrol/domain:config', context_course::instance($instance->courseid));
    }

    #[\Override]
    public function get_instance_defaults() {
        $students = get_archetype_roles('student');
        return [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $students ? (int)key($students) : 0,
        ];
    }

    #[\Override]
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('textarea', 'customtext1', get_string('domains', 'enrol_domain'), ['rows' => 4, 'cols' => 40]);
        $mform->setType('customtext1', PARAM_TEXT);
        $mform->addHelpButton('customtext1', 'domains', 'enrol_domain');

        $mform->addElement('select', 'status', get_string('status', 'enrol_domain'), [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ]);

        $roles = get_assignable_roles($context, ROLENAME_BOTH);
        $mform->addElement('select', 'roleid', get_string('defaultrole', 'enrol_domain'), $roles);
    }

    #[\Override]
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];
        $domains = self::parse_domains($data['customtext1'] ?? '');
        if (!$domains) {
            $errors['customtext1'] = get_string('error_nodomains', 'enrol_domain');
        }
        foreach ($domains as $domain) {
            if (!preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/', $domain)) {
                $errors['customtext1'] = get_string('error_domain', 'enrol_domain', s($domain));
                break;
            }
        }
        return $errors;
    }

    /**
     * True when the current user may enrol now, else a reason string or false.
     *
     * @param stdClass $instance
     * @param bool $checkuserenrolment also refuse when the user already has an enrolment here
     * @return bool|string
     */
    public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
        global $DB, $USER;
        if (!enrol_is_enabled('domain') || $instance->status != ENROL_INSTANCE_ENABLED) {
            return false;
        }
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if ($checkuserenrolment && $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $USER->id])) {
            return get_string('alreadyenrolled', 'enrol_domain');
        }
        if (!self::email_allowed((string)$USER->email, $instance->customtext1)) {
            return get_string('notallowed', 'enrol_domain', s(implode(', ', self::parse_domains($instance->customtext1))));
        }
        return true;
    }

    /**
     * Enrol the current user, checking the rule again.
     *
     * @param stdClass $instance
     * @throws moodle_exception when the user may not enrol
     */
    public function enrol_self(stdClass $instance): void {
        global $USER;
        $ok = $this->can_self_enrol($instance);
        if ($ok !== true) {
            $domains = s(implode(', ', self::parse_domains($instance->customtext1)));
            throw new moodle_exception('notallowed', 'enrol_domain', '', $domains);
        }
        $this->enrol_user($instance, $USER->id, $instance->roleid);
    }

    #[\Override]
    public function enrol_page_hook(stdClass $instance) {
        global $OUTPUT, $PAGE;
        $status = $this->can_self_enrol($instance);
        if ($status === true) {
            if (optional_param('enrol_domain', 0, PARAM_INT) == $instance->id && confirm_sesskey()) {
                $this->enrol_self($instance);
                redirect(new moodle_url('/course/view.php', ['id' => $instance->courseid]));
            }
            $url = new moodle_url($PAGE->url, ['enrol_domain' => $instance->id, 'sesskey' => sesskey()]);
            $body = $OUTPUT->notification(get_string('welcome', 'enrol_domain'), 'info', false);
            $body .= $OUTPUT->single_button($url, get_string('enrolme', 'enrol_domain'), 'post', ['primary' => true]);
        } else if (is_string($status)) {
            $body = $OUTPUT->notification($status, 'info', false);
        } else {
            return '';
        }
        return $OUTPUT->box($OUTPUT->heading($this->get_instance_name($instance), 3) . $body, 'generalbox enrol_domain');
    }

    #[\Override]
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        $instanceid = $this->add_instance($course, (array)$data);
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    #[\Override]
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }
}
