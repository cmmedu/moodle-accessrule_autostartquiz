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
        
        // Verificar si el autostart, hide_questionsinfotostudents, autosend o disable_right_drawer está habilitado para este quiz
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        $autosend = isset($autostart->autosend) ? $autostart->autosend : 0;
        $disable_right_drawer = isset($autostart->disable_right_drawer) ? $autostart->disable_right_drawer : 0;
        if (empty($autostart) || (empty($autostart->enabled) && empty($autostart->hide_questionsinfotostudents) && empty($autosend) && empty($disable_right_drawer))) {
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
        $this->apply_autosend_js($page);
        $this->apply_disable_right_drawer($page);
    }
    
    /**
     * Configurar la página de revisión del quiz.
     * Este método se llama cuando el estudiante revisa sus respuestas.
     *
     * @param moodle_page $page la página que se está configurando.
     */
    public function setup_review_page($page) {
        $this->apply_hide_question_info_css($page);
        $this->apply_hide_finish_review_button($page);
        $this->apply_disable_right_drawer($page);
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
     * Oculta el botón "Finalizar revisión" en la página de revisión.
     * Identifica el botón por la clase mod_quiz-next-nav y el href que contiene view.php
     * Aplica a todos los usuarios (no solo estudiantes).
     *
     * @param moodle_page $page la página actual.
     */
    private function apply_hide_finish_review_button($page) {
        global $DB;
        
        $quiz = $this->quizobj->get_quiz();
        
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        // Verificar si el autosend está habilitado (solo ocultamos si autosend está activo)
        $autosend = isset($autostart->autosend) ? $autostart->autosend : 0;
        if (!empty($autostart) && !empty($autosend)) {
            // Cargar el archivo CSS del plugin
            $page->requires->css('/mod/quiz/accessrule/autostart/styles.css');
            
            // JavaScript para ocultar el botón "Finalizar revisión"
            // Identificamos el botón por la clase mod_quiz-next-nav y el href que contiene view.php
            $jscode = '
                (function() {
                    function hideFinishReviewButton() {
                        // Buscar enlaces con clase mod_quiz-next-nav que apunten a view.php
                        var links = document.querySelectorAll("a.mod_quiz-next-nav");
                        for (var i = 0; i < links.length; i++) {
                            var link = links[i];
                            var href = link.getAttribute("href") || link.href || "";
                            // Si el href contiene view.php, es el botón de finalizar revisión
                            if (href.indexOf("view.php") !== -1) {
                                link.style.display = "none";
                                // También ocultar el contenedor submitbtns si solo contiene este enlace
                                var container = link.closest(".submitbtns");
                                if (container) {
                                    var visibleLinks = container.querySelectorAll("a:not([style*=\"display: none\"])");
                                    if (visibleLinks.length === 0 || (visibleLinks.length === 1 && visibleLinks[0] === link)) {
                                        container.style.display = "none";
                                    }
                                }
                            }
                        }
                    }
                    
                    // Ejecutar inmediatamente
                    hideFinishReviewButton();
                    
                    // Ejecutar cuando el DOM esté listo
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", function() {
                            setTimeout(hideFinishReviewButton, 100);
                        });
                    } else {
                        setTimeout(hideFinishReviewButton, 100);
                    }
                    
                    // Observar cambios dinámicos en el DOM
                    if (typeof MutationObserver !== "undefined") {
                        var observer = new MutationObserver(function(mutations) {
                            hideFinishReviewButton();
                        });
                        
                        if (document.body) {
                            observer.observe(document.body, { 
                                childList: true, 
                                subtree: true 
                            });
                        }
                    }
                })();
            ';
            $page->requires->js_init_code($jscode, true);
        }
    }
    
    /**
     * Aplica el JavaScript para auto-enviar el formulario de finalización si está configurado.
     * Aplica a todos los usuarios (no solo estudiantes).
     *
     * @param moodle_page $page la página actual.
     */
    private function apply_autosend_js($page) {
        global $DB;
        
        $quiz = $this->quizobj->get_quiz();
        
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        $autosend = isset($autostart->autosend) ? $autostart->autosend : 0;
        if (!empty($autostart) && !empty($autosend)) {
            // Cargar el archivo JavaScript del plugin para todos los usuarios
            $jsurl = new moodle_url('/mod/quiz/accessrule/autostart/autosend.js');
            $page->requires->js($jsurl);
            $page->requires->js_init_call('M.quizaccess_autostart.initAutoSend', array(), true);
        }
    }
    
    /**
     * Oculta el drawer derecho y su botón de activación si está configurado.
     * Aplica a todos los usuarios.
     *
     * @param moodle_page $page la página actual.
     */
    private function apply_disable_right_drawer($page) {
        global $DB;
        
        $quiz = $this->quizobj->get_quiz();
        
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        $disable_right_drawer = isset($autostart->disable_right_drawer) ? $autostart->disable_right_drawer : 0;
        if (!empty($autostart) && !empty($disable_right_drawer)) {
            // Cargar el archivo CSS del plugin
            $page->requires->css('/mod/quiz/accessrule/autostart/styles.css');
            
            // JavaScript para ocultar el drawer derecho y su botón de activación
            $jscode = '
                (function() {
                    function closeRightDrawer() {
                        // Primero, cerrar el drawer si está abierto
                        var rightDrawers = document.querySelectorAll(".drawer.drawer-right");
                        for (var i = 0; i < rightDrawers.length; i++) {
                            var drawer = rightDrawers[i];
                            
                            // Remover clase "show" si existe
                            drawer.classList.remove("show");
                            
                            // Cambiar data-state a "hide-drawer-right" si está en "show-drawer-right"
                            if (drawer.getAttribute("data-state") === "show-drawer-right") {
                                drawer.setAttribute("data-state", "hide-drawer-right");
                            }
                            
                            // Remover data-forceopen si existe
                            if (drawer.hasAttribute("data-forceopen")) {
                                drawer.removeAttribute("data-forceopen");
                            }
                            
                            // Disparar evento de cierre si está disponible
                            if (typeof drawer.dispatchEvent !== "undefined") {
                                var closeEvent = new Event("drawer-closed", { bubbles: true });
                                drawer.dispatchEvent(closeEvent);
                            }
                        }
                        
                        // También buscar por ID específico del drawer
                        var drawerById = document.getElementById("theme_boost-drawers-blocks");
                        if (drawerById) {
                            drawerById.classList.remove("show");
                            if (drawerById.getAttribute("data-state") === "show-drawer-right") {
                                drawerById.setAttribute("data-state", "hide-drawer-right");
                            }
                            if (drawerById.hasAttribute("data-forceopen")) {
                                drawerById.removeAttribute("data-forceopen");
                            }
                        }
                        
                        // Remover clase del body que indica drawer abierto
                        if (document.body) {
                            document.body.classList.remove("drawer-open-right");
                            document.body.classList.remove("show-drawer-right");
                        }
                    }
                    
                    function adjustContentWidth() {
                        // Ajustar el ancho del contenido principal para que ocupe todo el espacio
                        // Buscar el contenedor principal de la página
                        var pageContent = document.querySelector("#page");
                        if (pageContent) {
                            pageContent.style.marginRight = "0";
                            pageContent.style.width = "100%";
                            pageContent.style.maxWidth = "100%";
                        }
                        
                        // Buscar el wrapper principal
                        var pageWrapper = document.querySelector("#page-wrapper");
                        if (pageWrapper) {
                            pageWrapper.style.marginRight = "0";
                            pageWrapper.style.width = "100%";
                        }
                        
                        // Buscar el drawercontent (contenido principal)
                        var drawerContent = document.querySelector(".drawercontent");
                        if (drawerContent) {
                            drawerContent.style.marginRight = "0";
                            drawerContent.style.width = "100%";
                        }
                        
                        // Buscar el contenedor del quiz
                        var quizContainer = document.querySelector("#region-main");
                        if (quizContainer) {
                            quizContainer.style.width = "100%";
                            quizContainer.style.maxWidth = "100%";
                        }
                        
                        // Remover cualquier padding o margin derecho de los contenedores principales
                        var mainContainers = document.querySelectorAll(".container-fluid, .container, .row");
                        for (var i = 0; i < mainContainers.length; i++) {
                            var container = mainContainers[i];
                            container.style.marginRight = "0";
                            container.style.paddingRight = "";
                        }
                    }
                    
                    function hideRightDrawer() {
                        // Agregar clase al body para activar estilos CSS
                        if (document.body) {
                            document.body.classList.add("quizaccess-autostart-hide-right-drawer");
                        }
                        
                        // Primero cerrar el drawer si está abierto
                        closeRightDrawer();
                        
                        // Ajustar el ancho del contenido inmediatamente
                        adjustContentWidth();
                        
                        // Esperar un momento para que se complete la animación de cierre
                        setTimeout(function() {
                            // Ocultar el drawer derecho (por clase y por ID)
                            var rightDrawers = document.querySelectorAll(".drawer.drawer-right");
                            for (var i = 0; i < rightDrawers.length; i++) {
                                rightDrawers[i].style.display = "none";
                            }
                            
                            // También buscar por ID específico del drawer
                            var drawerById = document.getElementById("theme_boost-drawers-blocks");
                            if (drawerById) {
                                drawerById.style.display = "none";
                            }
                            
                            // Ocultar el botón de activación del drawer derecho
                            var rightDrawerToggles = document.querySelectorAll(".drawer-toggler.drawer-right-toggle");
                            for (var j = 0; j < rightDrawerToggles.length; j++) {
                                rightDrawerToggles[j].style.display = "none";
                            }
                            
                            // Ajustar el ancho del contenido nuevamente después de ocultar
                            adjustContentWidth();
                        }, 300); // Esperar 300ms para la animación de cierre
                    }
                    
                    // Ejecutar inmediatamente
                    hideRightDrawer();
                    
                    // Ejecutar cuando el DOM esté listo
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", function() {
                            setTimeout(hideRightDrawer, 100);
                        });
                    } else {
                        setTimeout(hideRightDrawer, 100);
                    }
                    
                    // Observar cambios dinámicos en el DOM
                    if (typeof MutationObserver !== "undefined") {
                        var observer = new MutationObserver(function(mutations) {
                            hideRightDrawer();
                        });
                        
                        if (document.body) {
                            observer.observe(document.body, { 
                                childList: true, 
                                subtree: true 
                            });
                        }
                    }
                })();
            ';
            $page->requires->js_init_code($jscode, true);
        }
    }
    
    /**
     * Información adicional para mostrar en la página del quiz.
     * Usamos esto para inyectar JavaScript que auto-inicia el intento.
     */
    public function description() {
        global $PAGE, $DB, $USER;
        
        $quiz = $this->quizobj->get_quiz();
        $context = $this->quizobj->get_context();
        
        // Verificar si el autostart está habilitado
        $autostart = $DB->get_record('quizaccess_autostart', ['quizid' => $quiz->id]);
        
        $output = '';
        
        if (!empty($autostart)) {
            if (!empty($autostart->enabled)) {
                // Verificar si el usuario tiene intentos enviados (finished) para este quiz
                $hasfinishedattempts = $DB->record_exists('quiz_attempts', [
                    'quiz' => $quiz->id,
                    'userid' => $USER->id,
                    'state' => 'finished'
                ]);
                
                // Solo ejecutar autostart si no hay intentos enviados
                if (!$hasfinishedattempts) {
                    // Agregar JavaScript para auto-iniciar cuando el DOM esté listo
                    $jsurl = new moodle_url('/mod/quiz/accessrule/autostart/autostart.js');
                    $PAGE->requires->js($jsurl);
                    $PAGE->requires->js_init_call('M.quizaccess_autostart.init', array(), true);
                }
            }
            
            // Agregar JavaScript para auto-enviar si está habilitado
            // Aplica a todos los usuarios (no solo estudiantes)
            $autosend = isset($autostart->autosend) ? $autostart->autosend : 0;
            if (!empty($autosend)) {
                $this->apply_autosend_js($PAGE);
            }
            
            // Aplicar disable right drawer si está habilitado
            // Aplica a todos los usuarios
            $disable_right_drawer = isset($autostart->disable_right_drawer) ? $autostart->disable_right_drawer : 0;
            if (!empty($disable_right_drawer)) {
                $this->apply_disable_right_drawer($PAGE);
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
        $defaultautosendvalue = 0;
        $defaultdisablerightdrawervalue = 0;
        
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
                $autosend = isset($autostart->autosend) ? $autostart->autosend : 0;
                if (!empty($autosend)) {
                    $defaultautosendvalue = 1;
                }
                $disable_right_drawer = isset($autostart->disable_right_drawer) ? $autostart->disable_right_drawer : 0;
                if (!empty($disable_right_drawer)) {
                    $defaultdisablerightdrawervalue = 1;
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
        
        $mform->addElement(
            'advcheckbox',
            'autosend',
            get_string('autosend', 'quizaccess_autostart'),
            null,
            null,
            [0, 1]
        );

        $mform->setDefault('autosend', $defaultautosendvalue);
        $mform->addHelpButton('autosend', 'autosend', 'quizaccess_autostart');
        
        $mform->addElement(
            'advcheckbox',
            'disable_right_drawer',
            get_string('disablerightdrawer', 'quizaccess_autostart'),
            null,
            null,
            [0, 1]
        );

        $mform->setDefault('disable_right_drawer', $defaultdisablerightdrawervalue);
        $mform->addHelpButton('disable_right_drawer', 'disablerightdrawer', 'quizaccess_autostart');
    }

    /**
     * Guardar el valor al crear/editar el quiz.
     *
     * @param stdClass $quiz el objeto quiz que se está guardando.
     */
    public static function save_settings($quiz) {
        global $DB;
        
        // El valor viene del formulario en $quiz->autostart_enabled, $quiz->hide_questionsinfotostudents y $quiz->autosend
        if (!isset($quiz->id) || empty($quiz->id) || !is_numeric($quiz->id) || $quiz->id <= 0) {
            return;
        }
        
        $enabled = !empty($quiz->autostart_enabled) ? 1 : 0;
        $hidequestionsinfo = !empty($quiz->hide_questionsinfotostudents) ? 1 : 0;
        $autosend = !empty($quiz->autosend) ? 1 : 0;
        $disable_right_drawer = !empty($quiz->disable_right_drawer) ? 1 : 0;
        $now = time();
        $quizid = (int)$quiz->id;
        
        // Buscar si ya existe un registro para este quiz
        $existing = $DB->get_record('quizaccess_autostart', ['quizid' => $quizid]);
        
        if ($existing) {
            // Actualizar el registro existente
            $existing->enabled = $enabled;
            $existing->hide_questionsinfotostudents = $hidequestionsinfo;
            $existing->autosend = $autosend;
            $existing->disable_right_drawer = $disable_right_drawer;
            $existing->timemodified = $now;
            $DB->update_record('quizaccess_autostart', $existing);
        } else {
            // Crear un nuevo registro si está habilitado, si hide_questionsinfotostudents está marcado, si autosend está marcado o si disable_right_drawer está marcado
            if ($enabled || $hidequestionsinfo || $autosend || $disable_right_drawer) {
                $record = new stdClass();
                $record->quizid = $quizid;
                $record->enabled = $enabled;
                $record->hide_questionsinfotostudents = $hidequestionsinfo;
                $record->autosend = $autosend;
                $record->disable_right_drawer = $disable_right_drawer;
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
