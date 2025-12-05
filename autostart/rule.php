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
        
        // Verificar si el autostart o hide_questionsinfotostudents está habilitado para este quiz
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        if (empty($autostart) || (empty($autostart->enabled) && empty($autostart->hide_questionsinfotostudents))) {
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
     * Configurar la página durante el intento del quiz.
     * Este método se llama cuando el estudiante está respondiendo las preguntas.
     *
     * @param moodle_page $page la página que se está configurando.
     */
    public function setup_attempt_page($page) {
        $this->apply_hide_question_info_css($page);
    }
    
    /**
     * Configurar la página de revisión del quiz.
     * Este método se llama cuando el estudiante revisa sus respuestas.
     *
     * @param moodle_page $page la página que se está configurando.
     */
    public function setup_review_page($page) {
        $this->apply_hide_question_info_css($page);
    }
    
    /**
     * Aplica el CSS para ocultar .que .info a estudiantes si está configurado.
     *
     * @param moodle_page $page la página actual.
     */
    private function apply_hide_question_info_css($page) {
        global $DB;
        
        $quiz = $this->quizobj->get_quiz();
        $context = $this->quizobj->get_context();
        
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        if (!empty($autostart) && !empty($autostart->hide_questionsinfotostudents)) {
            // Verificar si el usuario es estudiante (no tiene capacidad de ver reportes)
            $isstudent = !has_capability('mod/quiz:viewreports', $context);
            
            if ($isstudent) {
                // Cargar el archivo CSS del plugin
                $page->requires->css('/mod/quiz/accessrule/autostart/styles.css');
                
                // Añadir clase al body para activar el CSS
                $page->add_body_class('quizaccess-autostart-hideinfo');
                
                // También usar JavaScript como respaldo para asegurar que se oculte
                // en caso de que el CSS no se cargue a tiempo o haya elementos dinámicos
                $jscode = '
                    (function() {
                        // Añadir clase al body por si no se añadió desde PHP
                        document.body.classList.add("quizaccess-autostart-hideinfo");
                        
                        // Inyectar CSS inline como respaldo
                        if (!document.getElementById("quizaccess-autostart-hide-info")) {
                            var style = document.createElement("style");
                            style.id = "quizaccess-autostart-hide-info";
                            style.innerHTML = ".que .info { display: none !important; }";
                            document.head.appendChild(style);
                        }
                        
                        function hideQuestionInfo() {
                            var infoElements = document.querySelectorAll(".que .info");
                            for (var i = 0; i < infoElements.length; i++) {
                                infoElements[i].style.display = "none";
                            }
                        }
                        
                        // Ejecutar inmediatamente
                        hideQuestionInfo();
                        
                        // Observar cambios dinámicos en el DOM
                        if (typeof MutationObserver !== "undefined") {
                            var observer = new MutationObserver(hideQuestionInfo);
                            if (document.body) {
                                observer.observe(document.body, { childList: true, subtree: true });
                            }
                        }
                    })();
                ';
                $page->requires->js_init_code($jscode, true);
            }
        }
    }
    
    /**
     * Información adicional para mostrar en la página del quiz.
     * Usamos esto para inyectar JavaScript que auto-inicia el intento.
     */
    public function description() {
        global $PAGE, $DB;
        
        $quiz = $this->quizobj->get_quiz();
        $context = $this->quizobj->get_context();
        
        // Verificar si el autostart está habilitado
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        $output = '';
        
        if (!empty($autostart)) {
            if (!empty($autostart->enabled)) {
                // Agregar JavaScript para auto-iniciar cuando el DOM esté listo
                $jsurl = new moodle_url('/mod/quiz/accessrule/autostart/autostart.js');
                $PAGE->requires->js($jsurl);
                $PAGE->requires->js_init_call('M.quizaccess_autostart.init', array(), true);
            }
        }
        return $output;
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
        $defaulthidevalue = 0;
        
        // Verificar que quiz->id existe y es un número entero válido (no cadena vacía)
        if ($quiz && isset($quiz->id) && !empty($quiz->id) && is_numeric($quiz->id) && $quiz->id > 0) {
            $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => (int)$quiz->id]);
            if ($autostart) {
                if (!empty($autostart->enabled)) {
                    $defaultvalue = 1;
                }
                if (!empty($autostart->hide_questionsinfotostudents)) {
                    $defaulthidevalue = 1;
                }
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
        
        $mform->addElement(
            'advcheckbox',
            'hide_questionsinfotostudents',
            get_string('hidequestionsinfotostudents', 'quizaccess_autostart'),
            null,
            null,
            [0, 1]
        );

        $mform->setDefault('hide_questionsinfotostudents', $defaulthidevalue);
        $mform->addHelpButton('hide_questionsinfotostudents', 'hidequestionsinfotostudents', 'quizaccess_autostart');
    }

    /**
     * Guardar el valor al crear/editar el quiz.
     *
     * @param stdClass $quiz el objeto quiz que se está guardando.
     */
    public static function save_settings($quiz) {
        global $DB;
        
        // El valor viene del formulario en $quiz->autostart_enabled y $quiz->hide_questionsinfotostudents
        if (!isset($quiz->id) || empty($quiz->id) || !is_numeric($quiz->id) || $quiz->id <= 0) {
            return;
        }
        
        $enabled = !empty($quiz->autostart_enabled) ? 1 : 0;
        $hidequestionsinfo = !empty($quiz->hide_questionsinfotostudents) ? 1 : 0;
        $now = time();
        $quizid = (int)$quiz->id;
        
        // Buscar si ya existe un registro para este quiz
        $existing = $DB->get_record('quizaccess_autostart', ['quizid' => $quizid]);
        
        if ($existing) {
            // Actualizar el registro existente
            $existing->enabled = $enabled;
            $existing->hide_questionsinfotostudents = $hidequestionsinfo;
            $existing->timemodified = $now;
            $DB->update_record('quizaccess_autostart', $existing);
        } else {
            // Crear un nuevo registro si está habilitado o si hide_questionsinfotostudents está marcado
            if ($enabled || $hidequestionsinfo) {
                $record = new stdClass();
                $record->quizid = $quizid;
                $record->enabled = $enabled;
                $record->hide_questionsinfotostudents = $hidequestionsinfo;
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
        
        if (isset($quiz->id) && !empty($quiz->id) && is_numeric($quiz->id) && $quiz->id > 0) {
            $DB->delete_records('quizaccess_autostart', ['quizid' => (int)$quiz->id]);
        }
    }
    
}
