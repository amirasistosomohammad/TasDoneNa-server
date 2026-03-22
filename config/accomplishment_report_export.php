<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excel template (DepEd AO accomplishment form)
    |--------------------------------------------------------------------------
    |
    | Default file: storage/app/templates/Accomplishment-Report_AO_Final.xlsx
    | Override path with ACCOMPLISHMENT_REPORT_TEMPLATE (absolute) if needed.
    |
    */
    'template_absolute' => env('ACCOMPLISHMENT_REPORT_TEMPLATE', ''),

    'template_relative' => env(
        'ACCOMPLISHMENT_REPORT_TEMPLATE_RELATIVE',
        'templates/Accomplishment-Report_AO_Final.xlsx'
    ),

    /*
    |--------------------------------------------------------------------------
    | Worksheet
    |--------------------------------------------------------------------------
    |
    | Tab name in Accomplishment-Report_AO_Final.xlsx
    |
    */
    'sheet_name' => env('ACCOMPLISHMENT_REPORT_SHEET', 'Accomplishment'),

    /*
    |--------------------------------------------------------------------------
    | Static cells (Accomplishment-Report_AO_Final.xlsx layout)
    |--------------------------------------------------------------------------
    |
    | Period line is column A row 9; name / designation / period cover use column E.
    | “Prepared by” signature block: C36 name, C37 position (rows shift if extra KRA rows are inserted).
    |
    */
    'cells' => [
        'period_subtitle' => 'A9',
        'name' => 'E11',
        'designation' => 'E12',
        'period_cover' => 'E13',
        'prepared_by_name' => env('ACCOMPLISHMENT_REPORT_PREPARED_BY_NAME_CELL', 'C36'),
        'prepared_by_position' => env('ACCOMPLISHMENT_REPORT_PREPARED_BY_POSITION_CELL', 'C37'),
        'certified_by_name' => env('ACCOMPLISHMENT_REPORT_CERTIFIED_BY_NAME_CELL', 'I43'),
        'certified_by_position' => env('ACCOMPLISHMENT_REPORT_CERTIFIED_BY_POSITION_CELL', 'I44'),
        'certified_instruction_note_clear' => env('ACCOMPLISHMENT_REPORT_CERTIFIED_NOTE_CELL', 'M43'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Accomplishment table
    |--------------------------------------------------------------------------
    |
    | The DepEd template uses two-row-high merged blocks (e.g. A18:L19, A20:L21, …)
    | through template_data_last_row. Each exported task fills one block. Extra
    | template slots below the used rows are re-merged empty so no “split” cells show.
    |
    */
    'data_start_row' => (int) env('ACCOMPLISHMENT_REPORT_DATA_START_ROW', 18),
    'template_data_last_row' => (int) env('ACCOMPLISHMENT_REPORT_TEMPLATE_DATA_LAST_ROW', 33),
    'rows_per_accomplishment' => max(1, (int) env('ACCOMPLISHMENT_REPORT_ROWS_PER_ENTRY', 2)),
    'merge_from_column' => 'A',
    'merge_to_column' => 'L',

];
