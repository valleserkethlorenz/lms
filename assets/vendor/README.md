# Vendored front-end libraries

Every CSS/JS library the LMS used to load from `cdn.jsdelivr.net`,
`cdn.datatables.net`, `cdnjs.cloudflare.com` and `cdn.canvasjs.com` lives here.
The templates under `includes/` and `app/*/includes/` reference these files
through `$lmsBase . '/assets/vendor/...'`.

Why self-hosted:

* the Content-Security-Policy no longer trusts four third-party script origins;
* the site keeps working when a CDN is blocked or down;
* upstream drift is gone — `sweetalert2@11` and `cdn.canvasjs.com/canvasjs.min.js`
  were unpinned ("latest"), so a new release could change the UI overnight.

## Inventory

| Folder | Version | Upstream package |
| --- | --- | --- |
| `bootstrap/` | 5.3.3 | npm `bootstrap` (dist) |
| `bootstrap-icons/` | 1.11.3 | npm `bootstrap-icons` (font build + `fonts/`) |
| `jquery/` | 3.7.1 | npm `jquery` |
| `jquery-easing/` | 1.4.1 | npm `jquery.easing` |
| `select2/` | 4.1.0-rc.0 | npm `select2` |
| `select2-bootstrap4-theme/` | 1.5.2 | npm `@ttskch/select2-bootstrap4-theme` |
| `sb-admin-2/` | 4.1.4 | npm `startbootstrap-sb-admin-2` |
| `datatables/` | 1.13.8 | cdn.datatables.net core |
| `datatables-buttons/` | 2.4.2 | cdn.datatables.net Buttons |
| `datatables-select/` | 1.7.0 | cdn.datatables.net Select |
| `datatables-fixedheader/` | 3.4.0 | cdn.datatables.net FixedHeader |
| `datatables-rowgroup/` | 1.4.1 | cdn.datatables.net RowGroup |
| `jszip/` | 3.10.1 | npm `jszip` |
| `pdfmake/` | 0.2.7 | npm `pdfmake` (`build/` + `vfs_fonts.js`) |
| `sweetalert2/` | 11.26.25 | npm `sweetalert2` (`sweetalert2.all.min.js`) |
| `fullcalendar/` | 6.1.10 | npm `fullcalendar` (global bundle) |
| `alertifyjs/` | 1.13.1 | npm `alertifyjs` |
| `canvasjs/` | 3.18.0 Beta 1 | cdn.canvasjs.com (commercial licence, watermark build) |
| `font-awesome/` | 6.4.0 | cdnjs Font Awesome Free (`css/` + `webfonts/`) |

Two upstream URLs used by the old templates were dead and are replaced here:

* `jquery-easing@0.0.1/jquery.easing.min.js` → **404**; vendored `jquery.easing`
  1.4.1 instead (same API used by SB Admin 2).
* `cdn.datatables.net/fixedheader/3.4.1/...` → **404**; FixedHeader was never
  published as 3.4.1, vendored **3.4.0** instead.

Font Awesome and Bootstrap Icons only work while the `webfonts/` and `fonts/`
siblings stay next to their CSS file — do not flatten the folders.

## Source maps

The trailing `sourceMappingURL` comment is stripped from every downloaded file
(`scripts/fetch_vendor_assets.ps1` does this). DevTools therefore never requests
a `*.map`, which is what produced the old console noise:

```
Connecting to 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/...bootstrap.min.css.map'
violates the following Content Security Policy directive: "connect-src 'self'"
```

If you ever want readable original sources in DevTools, download the matching
`.map` next to the file and keep the `sourceMappingURL` comment — the fetch is
same-origin and allowed by `connect-src 'self'`.

## Updating a library

1. Pick the new version, then update the URL/path pair in
   `scripts/vendor-assets.csv`.
2. Change the destination folder to the new version (`<lib>/<new-version>/...`)
   so cached files cannot be mixed across versions.
3. Run:

   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/fetch_vendor_assets.ps1
   ```

   Existing files are skipped; add `-Force` to re-download everything.
4. Update the URL literals in `includes/header.php`, `includes/footer.php`, the
   four `app/*/includes/{header,footer}.php` pairs and `live.php` /
   `forgot-password.php` / `reset-password.php` as needed.
5. Delete the previous version folder, update this table, and smoke-test the
   DataTables export buttons (jszip + pdfmake), the calendars (FullCalendar) and
   the organizer/TR charts (CanvasJS).
