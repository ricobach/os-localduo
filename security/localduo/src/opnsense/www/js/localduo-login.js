/* LocalDuo login progress indicator for OPNsense's standard login form. */
(function () {
    'use strict';

    function setup() {
        var form = document.getElementById('iform');
        var username = document.getElementById('usernamefld');
        var password = document.getElementById('passwordfld');
        if (!form || !username || !password) {
            return;
        }

        var submitted = false;
        form.addEventListener('submit', function (event) {
            if (submitted) {
                event.preventDefault();
                return;
            }
            // Preserve ordinary native validation and empty-field behavior.
            if (!username.value.trim() || !password.value || !form.checkValidity()) {
                return;
            }

            event.preventDefault();
            submitted = true;

            var hint = document.createElement('div');
            hint.id = 'localduo-login-progress';
            hint.setAttribute('role', 'status');
            hint.setAttribute('aria-live', 'polite');
            hint.style.cssText = 'clear:both;padding-top:16px;line-height:1.5;';
            hint.textContent = 'Signing in… Check Duo Mobile if prompted.';
            form.appendChild(hint);

            var submitButton = form.querySelector('button[type="submit"][name="login"]');
            if (submitButton) {
                submitButton.textContent = 'Signing in…';
                submitButton.setAttribute('aria-disabled', 'true');
                // Do NOT disable the submit control: OPNsense uses name=login.
            }

            // The native form submit() API does not include a submit button's
            // name/value. Preserve the expected POST parameter explicitly.
            if (!form.querySelector('input[type="hidden"][name="login"]')) {
                var marker = document.createElement('input');
                marker.type = 'hidden';
                marker.name = 'login';
                marker.value = '1';
                form.appendChild(marker);
            }

            // Allow a browser paint before OPNsense begins its blocking Duo
            // authentication. The message doesn't assert password validity.
            window.setTimeout(function () {
                HTMLFormElement.prototype.submit.call(form);
            }, 80);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
}());
