<?php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'quizaccess_autostart_header',
        'Auto Start Quiz',
        'Configuración global para la regla Auto Start Quiz.'
    ));
}