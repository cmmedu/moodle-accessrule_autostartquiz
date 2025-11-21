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

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use mod_quiz_mod_form;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

/**
 * A rule implementing the autostart functionality.
 *
 * @package   quizaccess_autostart
 * @copyright 2024
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_autostart extends access_rule_base {

    /**
     * Crea la regla si corresponde.
     *
     * @param quiz_settings $quizobj información sobre el quiz.
     * @param int $timenow el tiempo que debe considerarse como 'ahora'.
     * @param bool $canignoretimelimits si el usuario actual está exento de límites de tiempo.
     * @return self|null la regla, si es aplicable, o null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        global $DB;
        
        $quiz = $quizobj->get_quiz();
        
        // Verificar si el autostart está habilitado para este quiz
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        if (empty($autostart) || empty($autostart->enabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Esta regla no bloquea el acceso en modo normal.
     */
    public function prevent_access() {
        return false;
    }
    
    /**
     * Información adicional para mostrar en la página del quiz.
     * Usamos esto para inyectar JavaScript que auto-inicia el intento.
     */
    public function description() {
        global $PAGE, $DB;
        
        $quiz = $this->quizobj->get_quiz();
        
        // Verificar si el autostart está habilitado
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        if (!empty($autostart) && !empty($autostart->enabled)) {
            // Agregar JavaScript para auto-iniciar cuando el DOM esté listo
            $jsurl = new moodle_url('/mod/quiz/accessrule/autostart/autostart.js');
            $PAGE->requires->js($jsurl);
            $PAGE->requires->js_init_call('M.quizaccess_autostart.init', array(), true);
        }
        return '';
    }

    /**
     * Agregar campos al formulario de configuración del quiz.
     *
     * @param mod_quiz_mod_form $quizform el formulario del quiz.
     * @param MoodleQuickForm $mform el formulario MoodleQuickForm.
     */
    public static function add_settings_form_fields(
            mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        global $DB;
        
        $quiz = $quizform->get_current();
        $defaultvalue = 0;
        
        // Obtener el valor de la tabla quizaccess_autostart
        if ($quiz && isset($quiz->id)) {
            $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
            if ($autostart && !empty($autostart->enabled)) {
                $defaultvalue = 1;
            }
        }
        
        // Agregar header para la sección propia
        $mform->addElement('header', 'autostartheader', 
            get_string('autostartheader', 'quizaccess_autostart'));
        $mform->addHelpButton('autostartheader', 'autostartheader', 'quizaccess_autostart');
        
        $mform->addElement(
            'advcheckbox',
            'autostart_enabled',
            get_string('autostartenabled', 'quizaccess_autostart'),
            null,
            null,
            [0, 1]
        );

        $mform->setDefault('autostart_enabled', $defaultvalue);
        $mform->addHelpButton('autostart_enabled', 'autostartenabled', 'quizaccess_autostart');
    }

    /**
     * Guardar el valor al crear/editar el quiz.
     *
     * @param stdClass $quiz el objeto quiz que se está guardando.
     */
    public static function save_settings($quiz) {
        global $DB;
        
        // El valor viene del formulario en $quiz->autostart_enabled
        if (!isset($quiz->id) || !isset($quiz->autostart_enabled)) {
            return;
        }
        
        $enabled = !empty($quiz->autostart_enabled) ? 1 : 0;
        $now = time();
        
        // Buscar si ya existe un registro para este quiz
        $existing = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        if ($existing) {
            // Actualizar el registro existente
            $existing->enabled = $enabled;
            $existing->timemodified = $now;
            $DB->update_record('quizaccess_autostart', $existing);
        } else {
            // Crear un nuevo registro solo si está habilitado
            if ($enabled) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->enabled = $enabled;
                $record->timecreated = $now;
                $record->timemodified = $now;
                $DB->insert_record('quizaccess_autostart', $record);
            }
        }
    }

    /**
     * Eliminar los registros cuando se elimina un quiz.
     *
     * @param stdClass $quiz el objeto quiz que se está eliminando.
     */
    public static function delete_settings($quiz) {
        global $DB;
        
        if (isset($quiz->id)) {
            $DB->delete_records('quizaccess_autostart', ['quizid' => $quiz->id]);
        }
    }
    
}
