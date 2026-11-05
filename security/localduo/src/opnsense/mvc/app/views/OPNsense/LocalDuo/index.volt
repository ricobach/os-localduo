<div class="content-box">
  <div class="col-md-12">
    <div class="panel panel-default" style="max-width: 850px;">
      <div class="panel-heading"><h3 class="panel-title">Duo Credential Manager</h3></div>
      <div class="panel-body">
        <p>Manage a write-only Duo secret for the IKEY used in <strong>System → Access → Servers</strong>.
           This is a protected configuration page, not a separate authentication server.</p>
        <form id="duo_secret_form" class="form-horizontal" autocomplete="off">
          <div class="form-group">
            <label for="duo_ikey" class="col-sm-3 control-label">Integration key (IKEY)</label>
            <div class="col-sm-7"><input id="duo_ikey" type="text" class="form-control" spellcheck="false"
                 placeholder="DIXXXXXXXXXXXXXXXXXX"></div>
          </div>
          <div class="form-group">
            <label for="duo_skey" class="col-sm-3 control-label">Secret key (SKEY)</label>
            <div class="col-sm-7">
              <input id="duo_skey" type="password" class="form-control" autocomplete="new-password"
                placeholder="Enter a secret to set or replace it">
              <span class="help-block" id="duo_secret_status">Enter an integration key to check its secret status.</span>
            </div>
          </div>
          <div class="form-group">
            <label for="duo_host" class="col-sm-3 control-label">API hostname (for test)</label>
            <div class="col-sm-7"><input id="duo_host" class="form-control" type="text" spellcheck="false"
              placeholder="api-xxxxxxxx.duosecurity.com"></div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <div class="col-sm-offset-3 col-sm-7">
              <button class="btn btn-primary" type="submit" id="duo_save">Save secret</button>
              <button class="btn btn-default" type="button" id="duo_test">Test Duo connection</button>
            </div>
          </div>
          <div class="form-group" id="duo_message_row" style="display:none;margin-top:15px;margin-bottom:0">
            <div class="col-sm-offset-3 col-sm-7"><div id="duo_message" class="alert" role="status"></div></div>
          </div>
        </form>
      </div>
      <div class="panel-footer">
        <small class="text-muted">The SKEY is never returned to the browser. It is stored in the protected OPNsense
         configuration and may be included in backups. <strong>Save the secret before testing.</strong></small>
      </div>
    </div>
  </div>
</div>
<script>
$(function () {
  'use strict';
  var api = '/api/localduo/settings/';
  // IKEY and hostname are non-secret settings. Prefill from the server editor
  // when the credential manager is opened through its shortcut.
  var query = new URLSearchParams(window.location.search);
  if (query.has('ikey')) {
    $('#duo_ikey').val(query.get('ikey') || '');
  }
  if (query.has('host')) {
    $('#duo_host').val(query.get('host') || '');
  }
  function message(text, ok) {
    $('#duo_message').removeClass('alert-success alert-danger')
      .addClass(ok ? 'alert-success' : 'alert-danger').text(text);
    $('#duo_message_row').show();
  }
  function checkConfigured() {
    var ikey = $('#duo_ikey').val().trim();
    if (!ikey) { $('#duo_secret_status').text('Enter an integration key.'); return; }
    ajaxGet(api + 'get', {ikey: ikey}, function (r) {
      $('#duo_secret_status').text(r.status === 'ok' && r.skey_configured
        ? 'Secret configured. Enter a new value only to replace it.'
        : 'No stored secret for this integration key.');
    });
  }
  $('#duo_ikey').on('change', checkConfigured);
  $('#duo_secret_form').on('submit', function (e) {
    e.preventDefault();
    var secret = $('#duo_skey').val();
    if (!secret) { message('Enter a secret key to save.', false); return; }
    $('#duo_save').prop('disabled', true);
    ajaxCall(api + 'set', {ikey: $('#duo_ikey').val(), skey: secret}, function (r) {
      $('#duo_save').prop('disabled', false);
      if (r.status === 'ok') {
        $('#duo_skey').val('');
        checkConfigured();
      }
      message(r.status === 'ok' ? 'Secret saved.' : (r.message || 'Save failed.'), r.status === 'ok');
    });
  });
  $('#duo_test').on('click', function () {
    $('#duo_test').prop('disabled', true);
    ajaxCall(api + 'check', {ikey: $('#duo_ikey').val(), host: $('#duo_host').val()}, function (r) {
      $('#duo_test').prop('disabled', false);
      message(r.message || r.status, r.status === 'ok');
    });
  });
});
</script>
