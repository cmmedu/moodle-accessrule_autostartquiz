<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Invocado al renderizar el formulario del cuestionario.
 */
function quizaccess_autostart_add_settings_form_fields(
    MoodleQuickForm $form,
    stdClass $quiz
) {
    require_once(__DIR__ . '/mod_form_hooks.php');
    quizaccess_autostart_mod_form::add_form_fields($form, $quiz);
}

/**
 * Invocado cuando se guardan opciones del quiz.
 */
function quizaccess_autostart_save_settings(
    stdClass &$quiz,
    stdClass $data
) {
    require_once(__DIR__ . '/mod_form_hooks.php');
    quizaccess_autostart_mod_form::save_form_data($quiz, $data);
}
