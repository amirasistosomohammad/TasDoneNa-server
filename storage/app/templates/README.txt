Accomplishment report Excel (DepEd AO template)
===============================================

Expected file (default):

  server/storage/app/templates/Accomplishment-Report_AO_Final.xlsx

The workbook must include a sheet named "Accomplishment" (or set
ACCOMPLISHMENT_REPORT_SHEET in .env).

Optional .env overrides:

  ACCOMPLISHMENT_REPORT_TEMPLATE=/absolute/path/to/file.xlsx
  ACCOMPLISHMENT_REPORT_TEMPLATE_RELATIVE=templates/YourOtherName.xlsx
  ACCOMPLISHMENT_REPORT_SHEET=Accomplishment
  ACCOMPLISHMENT_REPORT_DATA_START_ROW=18
  ACCOMPLISHMENT_REPORT_TEMPLATE_DATA_LAST_ROW=33
  ACCOMPLISHMENT_REPORT_ROWS_PER_ENTRY=2

Layout is defined in config/accomplishment_report_export.php (cells A9, E11–E13,
KRA area: two-row merged blocks A:L from row 18 through template_data_last_row).
“Prepared by” name/position default to C36/C37; “Certified by” to I43/I44; client note cell M43 is cleared when SH data is supplied.
Officer period export (async — avoids proxy 504):
  1) POST /api/accomplishment-reports/export  → 202 JSON { export_token, poll_interval_ms, … }
  2) GET  /api/accomplishment-reports/export/{token}/status  → { status: pending|ready|failed }
  3) GET  /api/accomplishment-reports/export/{token}/download  → .xlsx (one-time)
  Body for (1): year, month, school_head_name, school_head_designation.
  If you run multiple web instances, use a shared CACHE_STORE (database/redis), not file-only.
Adjust config if your template differs.

After deploy, ensure PHP can read this directory (same as storage/app).

Deployment notes
----------------
- Period export builds the workbook after the initial HTTP response; short requests should
  not hit gateway timeouts. Ensure CACHE_DRIVER works across your setup and
  storage/framework/cache + storage/app/temp are writable.
- Saved report download: GET /api/accomplishment-reports/{id}/export still streams in one
  request (can be slow on very small instances).
