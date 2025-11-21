/**
 * Auto-start quiz functionality
 * 
 * This script automatically starts a quiz attempt when the quiz page loads,
 * if the auto-start feature is enabled.
 */
M.quizaccess_autostart = M.quizaccess_autostart || {};

M.quizaccess_autostart.init = function() {
    // Esperar a que el DOM esté completamente cargado
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            M.quizaccess_autostart.autoStart();
        });
    } else {
        // Si ya está cargado, ejecutar inmediatamente
        setTimeout(function() {
            M.quizaccess_autostart.autoStart();
        }, 100);
    }
};

M.quizaccess_autostart.autoStart = function() {
    // Buscar el botón de inicio del intento
    var startButton = document.querySelector('input[type="submit"][name="startattempt"]');
    
    if (startButton && !startButton.disabled) {
        // Si encontramos el botón, hacer clic automáticamente
        startButton.click();
        return;
    }
    
    // Si no hay botón, buscar enlaces de inicio
    var startLink = document.querySelector('a[href*="startattempt"]');
    if (startLink) {
        window.location.href = startLink.href;
        return;
    }
    
    // Buscar cualquier formulario que tenga startattempt
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
        var form = forms[i];
        if (form.action && form.action.indexOf('startattempt') !== -1) {
            form.submit();
            return;
        }
    }
    
    // Buscar botones con texto relacionado a "Iniciar" o "Start"
    var buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
    for (var j = 0; j < buttons.length; j++) {
        var btn = buttons[j];
        var text = (btn.textContent || btn.value || '').toLowerCase();
        if ((text.indexOf('start') !== -1 || text.indexOf('iniciar') !== -1) && 
            !btn.disabled && btn.type !== 'button') {
            btn.click();
            return;
        }
    }
};

