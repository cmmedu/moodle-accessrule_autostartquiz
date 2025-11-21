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
 * Upgrade script for quizaccess_autostart plugin.
 *
 * @package    quizaccess_autostart
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for quizaccess_autostart
 *
 * @param int $oldversion The version we are upgrading from
 * @return bool Success
 */
function xmldb_quizaccess_autostart_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025112201) {
        // Create the quizaccess_autostart table.
        $table = new xmldb_table('quizaccess_autostart');
        
        if (!$dbman->table_exists($table)) {
            // Define fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            // Define keys.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('quizid', XMLDB_KEY_FOREIGN_UNIQUE, ['quizid'], 'quiz', ['id']);

            // Note: No need to add index for quizid because the foreign-unique key already creates one.

            // Create table.
            $dbman->create_table($table);
        }

        // Migrate data from quiz table if autostart_enabled field exists.
        $quiztable = new xmldb_table('quiz');
        $field = new xmldb_field('autostart_enabled');
        if ($dbman->field_exists($quiztable, $field)) {
            // Migrate existing data.
            $quizzes = $DB->get_records('quiz', ['autostart_enabled' => 1]);
            foreach ($quizzes as $quiz) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->enabled = 1;
                $record->timecreated = time();
                $record->timemodified = time();
                $DB->insert_record('quizaccess_autostart', $record);
            }
        }

        // Autostart savepoint reached.
        upgrade_plugin_savepoint(true, 2025112201, 'quizaccess', 'autostart');
    }

    return true;
}

