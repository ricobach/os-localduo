<div class="content-box">
    <div class="col-md-12">
        <div class="panel panel-default" style="max-width: 900px; margin: 0 0 16px;">
            <div class="panel-heading">
                <h3 class="panel-title">Duo MFA Settings</h3>
            </div>
            <div class="panel-body">
                <p class="text-muted" style="margin: 0 0 18px;">
                    Require Duo Push after successful authentication against the OPNsense local user database.
                </p>

                <form id="duo_settings_form" class="form-horizontal" autocomplete="off">
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="duo_ikey">Integration key</label>
                        <div class="col-sm-7">
                            <input class="form-control" id="duo_ikey" type="text" autocomplete="off" spellcheck="false"
                                   placeholder="DIXXXXXXXXXXXXXXXXXX">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="duo_skey">Secret key</label>
                        <div class="col-sm-7">
                            <input class="form-control" id="duo_skey" type="password" autocomplete="new-password"
                                   placeholder="Leave blank to keep the current secret">
                            <span class="help-block" id="duo_secret_status" style="margin-bottom:0;"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="duo_host">API hostname</label>
                        <div class="col-sm-7">
                            <input class="form-control" id="duo_host" type="text" spellcheck="false"
                                   placeholder="api-xxxxxxxx.duosecurity.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">Firewall hostname</label>
                        <div class="col-sm-7">
                            <p class="form-control-static" id="duo_firewall">Loading...</p>
                            <span class="help-block" style="margin-top:0;">Automatically read from the firewall configuration.</span>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <div class="col-sm-offset-3 col-sm-7">
                            <button id="duo_save" type="submit" class="btn btn-primary">
                                <i class="fa fa-save" aria-hidden="true"></i> Save settings
                            </button>
                            <button id="duo_test" type="button" class="btn btn-default">
                                <i class="fa fa-check-circle" aria-hidden="true"></i> Test Duo API
                            </button>
                        </div>
                    </div>

                    <div class="form-group" id="duo_message_group" style="display:none; margin-top:16px; margin-bottom:0;">
                        <div class="col-sm-offset-3 col-sm-7">
                            <div id="duo_message" class="alert" role="status" aria-live="polite" style="margin-bottom:0;"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="panel-footer">
                <small class="text-muted">
                    <strong>Security:</strong> The existing secret is never returned to the browser. Leaving the
                    secret field empty keeps the current key. The key is stored in OPNsense configuration
                    and can be included in backups.
                </small>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    'use strict';
    const api = '/api/localduo/settings/';

    function showMessage(message, success) {
        $('#duo_message')
            .removeClass('alert-success alert-danger')
            .addClass(success ? 'alert-success' : 'alert-danger')
            .text(message);
        $('#duo_message_group').show();
    }

    function loadSettings() {
        ajaxGet(api + 'get', {}, function (data) {
            $('#duo_ikey').val(data.ikey || '');
            $('#duo_host').val(data.host || '');
            $('#duo_firewall').text(data.firewall || 'Unknown');
            $('#duo_secret_status').text(
                data.skey_configured
                    ? 'Secret configured. Leave this field blank to keep it.'
                    : 'No secret configured yet.'
            );
            $('#duo_skey').val('');
        });
    }

    $('#duo_settings_form').on('submit', function (event) {
        event.preventDefault();
        $('#duo_save').prop('disabled', true);
        ajaxCall(api + 'set', {
            ikey: $('#duo_ikey').val(),
            skey: $('#duo_skey').val(),
            host: $('#duo_host').val()
        }, function (response) {
            $('#duo_save').prop('disabled', false);
            if (response.status === 'ok') {
                loadSettings();
                showMessage('Duo settings saved successfully.', true);
            } else {
                showMessage(response.message || 'Failed to save Duo settings.', false);
            }
        }).fail(function () {
            $('#duo_save').prop('disabled', false);
            showMessage('Unable to save Duo settings.', false);
        });
    });

    $('#duo_test').on('click', function () {
        $('#duo_test').prop('disabled', true);
        ajaxCall(api + 'check', {}, function (response) {
            $('#duo_test').prop('disabled', false);
            showMessage(
                response.message || (response.status === 'ok' ? 'Duo API connection verified.' : 'Duo API test failed.'),
                response.status === 'ok'
            );
        }).fail(function () {
            $('#duo_test').prop('disabled', false);
            showMessage('Unable to test Duo API credentials.', false);
        });
    });

    loadSettings();
});
</script>
