# LocalDuo theme-based login progress (design)

## Verified integration point

OPNsense core `src/www/authgui.inc` renders the login form and conditionally loads the selected theme's `/js/theme.js` using `get_themed_filename('/js/theme.js', true)`. This provides a frontend integration point without modifying `/usr/local/www/authgui.inc`.

The existing LocalDuo script is `/ui/js/localduo-login.js`. The existing privileged patch operations in `LoginHint.php` must remain available and unchanged.

## Manual operation

Add a separate **Create Duo Theme** action alongside (not replacing) the current patch controls. Never run it during package installation or upgrade.

1. Read and validate the configured theme name from OPNsense configuration.
2. Resolve the installed theme path using OPNsense's theme conventions; reject invalid paths, symlinks escaping theme root, and an already-generated Duo theme.
3. Copy the selected theme to a uniquely named managed theme (e.g. `<source>-localduo`), preserving the source theme without changes.
4. Append an idempotent loader to the copied theme's `js/theme.js` (create if absent):

   ```javascript
   // LocalDuo managed loader
   (function () {
       if (document.querySelector('script[data-localduo-theme-loader]')) return;
       var script = document.createElement('script');
       script.src = '/ui/js/localduo-login.js';
       script.dataset.localduoThemeLoader = '1';
       document.head.appendChild(script);
   }());
   ```

5. Ensure OPNsense recognizes the generated theme, then offer an explicit **Activate Duo Theme** action; do not silently switch themes.
6. Store original theme identity and generated theme identity so **Restore Original Theme** can switch back without removing other user configuration.

## Requirements before implementing privileged operations

- Verify the exact OPNsense 26.7 installed theme directories, registration/discovery mechanism, and theme configuration write API against upstream source and a test firewall.
- Ensure copied theme retains assets and permissions and does not recursively copy an already-generated theme.
- Refuse overwriting a preexisting theme that is not managed by LocalDuo; make recreation idempotent.
- Never remove a theme while active. Do not delete an original or user-customized theme.
- Handle existing core login patch: loading the same script twice must not install duplicate submit handlers. Prefer requiring the administrator to disable the old patch before activating the Duo theme, or implement a script-level singleton guard.
- Check that theme switching cannot strand administrators without a functioning login page; preserve fallback access.
- Avoid automatic theme deletion or changes on plugin uninstall until lifecycle behavior has been verified.
- Preserve existing Patch / Unpatch / Status actions, authentication backend, and Duo modal behavior.

## Implementation status

This document records the verified login-page hook and proposed workflow. No runtime theme-management implementation has been committed yet. Testing on OPNsense 26.7 is required before exposing the new privileged action.
