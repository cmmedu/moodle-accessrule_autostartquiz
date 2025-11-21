<?php

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quizaccess_autostart';
$plugin->version   = 2025112201;
$plugin->requires  = 2024042200;   // Moodle 5.0

$plugin->dependencies = array(
    'mod_quiz' => 2024042200
);
