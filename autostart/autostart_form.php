<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/mod_form.php');

class quizaccess_autostart_form {

    /**
     * Agregar campos al formulario de configuración del quiz.
     */
    public static function add_settings_form_fields(
        MoodleQuickForm $form,
        stdClass $quiz
    ) {
        // Checkbox
        $form->addElement(
            'advcheckbox',
            'autostart_enabled',
            get_string('autostartenabled', 'quizaccess_autostart'),
            null,
            null,
            [0, 1]
        );

        $form->setDefault('autostart_enabled', $quiz->autostart_enabled ?? 0);
        $form->addHelpButton('autostart_enabled', 'autostartenabled', 'quizaccess_autostart');
    }

    /**
     * Guardar el valor al crear/editar el quiz.
     */
    public static function save_settings(stdClass &$quiz, stdClass $data) {
        $quiz->autostart_enabled = isset($data->autostart_enabled) ? 1 : 0;
    }
}
