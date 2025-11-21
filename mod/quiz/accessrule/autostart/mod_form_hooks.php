<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/autostart_form.php');

class quizaccess_autostart_mod_form {

    public static function add_form_fields(MoodleQuickForm $form, $quiz) {
        quizaccess_autostart_form::add_settings_form_fields($form, $quiz);
    }

    public static function save_form_data($quiz, $data) {
        quizaccess_autostart_form::save_settings($quiz, $data);
    }
}
