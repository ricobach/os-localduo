<div class="content-box" style="padding:20px;max-width:760px">
 <h2>Duo MFA for OPNsense local users</h2>
 <p>Configure the Duo Auth API integration used after native OPNsense password verification.</p>
 <div class="form-group"><label for="duo_ikey">Integration key</label><input class="form-control" id="duo_ikey" autocomplete="off"></div>
 <div class="form-group"><label for="duo_skey">Secret key</label><input class="form-control" type="password" id="duo_skey" autocomplete="new-password" placeholder="Leave blank to keep existing secret"><small id="duo_secret_status"></small></div>
 <div class="form-group"><label for="duo_host">Duo API hostname</label><input class="form-control" id="duo_host" placeholder="api-xxxxxxxx.duosecurity.com" autocomplete="off"></div>
 <div class="form-group"><label>Firewall hostname</label><p id="duo_firewall" class="form-control-static"></p></div>
 <button id="duo_save" class="btn btn-primary">Save</button>
 <button id="duo_test" class="btn btn-default">Check saved API credentials</button>
 <p id="duo_message" role="status" style="margin-top:15px"></p>
 <hr><p><strong>Security:</strong> The secret is never returned to the browser. An empty secret field preserves the existing value. The secret is stored in OPNsense config.xml and therefore may be present in configuration backups.</p>
</div>
<script>
$(function(){
  const api='/api/localduo/settings/';
  function message(s){ $('#duo_message').text(s); }
  function load(){
    ajaxGet(api+'get', {}, function(r){
      $('#duo_ikey').val(r.ikey || '');
      $('#duo_host').val(r.host || '');
      $('#duo_firewall').text(r.firewall || 'Unknown');
      $('#duo_secret_status').text(r.skey_configured ? 'Secret configured; leave blank to keep it' : 'Secret not configured');
      $('#duo_skey').val('');
    });
  }
  $('#duo_save').on('click',function(){
    ajaxCall(api+'set', {
      ikey: $('#duo_ikey').val(), host: $('#duo_host').val(), skey: $('#duo_skey').val()
    },function(r){message(r.status==='ok' ? 'Settings saved' : (r.message || 'Save failed')); if(r.status==='ok') load();});
  });
  $('#duo_test').on('click',function(){
    ajaxCall(api+'check', {}, function(r){ message(r.message || r.status); });
  });
  load();
});
</script>
