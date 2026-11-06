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

            // Lightweight self-contained overlay: no UI framework dependencies,
            // no authentication status polling and no password-validity disclosure.
            var style = document.createElement('style');
            style.id = 'localduo-login-modal-style';
            style.textContent =
                '@keyframes localduo-spin {to{transform:rotate(360deg)}}' +
                '#localduo-login-progress{' +
                    'position:fixed;inset:0;z-index:2147483640;' +
                    'display:flex;align-items:center;justify-content:center;' +
                    'padding:20px;background:rgba(12,20,30,.68)}' +
                '#localduo-login-progress .localduo-modal{' +
                    'box-sizing:border-box;width:100%;max-width:400px;' +
                    'padding:30px 26px;border-radius:10px;text-align:center;' +
                    'background:#fff;color:#263238;' +
                    'box-shadow:0 14px 45px rgba(0,0,0,.3);' +
                    'font-family:inherit}' +
                '#localduo-login-progress .localduo-spinner{' +
                    'width:42px;height:42px;margin:0 auto 22px;' +
                    'border:4px solid #dce3e8;border-top-color:#3275a8;' +
                    'border-radius:50%;animation:localduo-spin .85s linear infinite}' +
                '#localduo-login-progress .localduo-title{' +
                    'font-weight:600;font-size:20px;margin:0 0 12px}' +
                '#localduo-login-progress .localduo-message{' +
                    'margin:0;line-height:1.5;font-size:14px}' +
                '#localduo-login-progress .localduo-note{' +
                    'margin:12px 0 0;color:#63717a;font-size:12px}' +
                '@media(prefers-reduced-motion:reduce){' +
                    '#localduo-login-progress .localduo-spinner{animation:none}}';
            document.head.appendChild(style);

            var overlay = document.createElement('div');
            overlay.id = 'localduo-login-progress';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'localduo-login-title');
            overlay.setAttribute('aria-describedby', 'localduo-login-message');
            var panel = document.createElement('div');
            panel.className = 'localduo-modal';
            var spinner = document.createElement('div');
            spinner.className = 'localduo-spinner';
            spinner.setAttribute('aria-hidden', 'true');
            var title = document.createElement('h2');
            title.id = 'localduo-login-title';
            title.className = 'localduo-title';
            title.textContent = 'Signing in…';
            var message = document.createElement('p');
            message.id = 'localduo-login-message';
            message.className = 'localduo-message';
            message.textContent = 'Check Duo Mobile and approve the request if prompted.';
            var note = document.createElement('p');
            note.className = 'localduo-note';
            note.textContent = 'This may take a few moments.';
            panel.appendChild(spinner);
            panel.appendChild(title);
            panel.appendChild(message);
            panel.appendChild(note);
            overlay.appendChild(panel);
            document.body.appendChild(overlay);

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

            // Allow the overlay to paint before OPNsense begins its blocking Duo
            // authentication. This does not assert password validity.
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
