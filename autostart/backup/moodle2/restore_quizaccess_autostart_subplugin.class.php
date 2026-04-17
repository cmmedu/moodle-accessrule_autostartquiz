<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Restore instructions for the autostart quiz access subplugin.
 *
 * @package    quizaccess_autostart
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/restore_mod_quiz_access_subplugin.class.php');

/**
 * Restore instructions for the autostart quiz access subplugin.
 */
class restore_quizaccess_autostart_subplugin extends restore_mod_quiz_access_subplugin {

    /**
     * Paths for quiz-level restore.
     *
     * @return restore_path_element[]
     */
    protected function define_quiz_subplugin_structure() {
        $paths = [];
        $path = $this->get_pathfor('/quizaccess_autostart');
        $paths[] = new restore_path_element('quizaccess_autostart', $path);
        return $paths;
    }

    /**
     * Restore one row from backup XML into {@see quizaccess_autostart}.
     *
     * @param array|stdClass $data
     */
    public function process_quizaccess_autostart($data) {
        global $DB;

        $data = (object) $data;
        $quizid = (int) $this->get_new_parentid('quiz');

        $record = (object) [
            'quizid' => $quizid,
            'enabled' => !empty($data->enabled) ? 1 : 0,
            'hide_questionsinfotostudents' => !empty($data->hide_questionsinfotostudents) ? 1 : 0,
            'autosend' => !empty($data->autosend) ? 1 : 0,
            'disable_right_drawer' => !empty($data->disable_right_drawer) ? 1 : 0,
            'timecreated' => !empty($data->timecreated) ? (int) $data->timecreated : time(),
            'timemodified' => time(),
        ];

        if (!$record->enabled && !$record->hide_questionsinfotostudents && !$record->autosend
                && !$record->disable_right_drawer) {
            return;
        }

        if ($existing = $DB->get_record('quizaccess_autostart', ['quizid' => $quizid])) {
            $record->id = $existing->id;
            $DB->update_record('quizaccess_autostart', $record);
        } else {
            $DB->insert_record('quizaccess_autostart', $record);
        }
    }
}
