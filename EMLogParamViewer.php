<?php

namespace Yale\EMLogParamViewer;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

class EMLogParamViewer extends \ExternalModules\AbstractExternalModule
{

    function redcap_every_page_top($project_id)
    {
        if ( PAGE !== "manager/logs.php" ) {
            return;
        }

        $serviceUrl = $this->getUrl('services.php');

        $str_project_id = is_numeric($project_id) ? strval($project_id): 'null';

        ?>

        <style>

            table.log-parameters tr > td:nth-child(2) {
                cursor: pointer;
                /*text-decoration: underline dotted;*/
            }

            table.log-parameters tr > td:nth-child(2):hover {
                text-decoration: underline;
                text-decoration-style: solid;
                text-underline-offset: 2px;
            }

            table.log-parameters tr:nth-child(1) > th:nth-child(2)::after {
                content: " (click to view full value)";
                color: gray;
            }

            .emlpv-scrolling-container {

                scrollbar-color: gray;
                scrollbar-width: 10px;
                max-height: 400px;
                overflow-y: auto;
            }

            .emlpv-scrolling-container::-webkit-scrollbar-track
            {
                background-color: lightgray;
            }

            .emlpv-scrolling-container::-webkit-scrollbar
            {
                width: 10px;
                background-color: lightgray;
            }

            .emlpv-scrolling-container::-webkit-scrollbar-thumb
            {
                background-color: gray;
            }

            .emlpv-no-scrolling-container {
                overflow: visible !important;
            }

            .emlpv-ellipsis 
            {  
                white-space: nowrap;
                -ms-text-overflow: ellipsis;
                text-overflow: ellipsis;
                overflow: hidden;
            }

            table#emlpv-log-info-table {
                border-collapse: collapse;
                margin-bottom: 10px;
                width: 100%;
                table-layout: fixed;
            }

            table#emlpv-log-info-table td {
                border:0;
                padding-top: 0;
                padding-bottom: 0;
                padding-left: 4px;
                padding-right: 4px;
                font-size: 0.9em;
                line-height: 1.3em;
                white-space: nowrap;
                -ms-text-overflow: ellipsis;
                text-overflow: ellipsis;
                overflow: hidden;
            }

            table#emlpv-log-info-table td:first-child {
                width: 100px;
            }

        </style>

        <script>

            const EMLPV = {
                serviceUrl: '<?php echo $serviceUrl; ?>',
                logData: null,
            };

            EMLPV.ajax = function( request, data, callback ) {

                data.request = request;
                data.redcap_csrf_token = redcap_csrf_token;

                $.ajax({
                    url: EMLPV.serviceUrl,
                    type: "POST",
                    dataType: "json",                     
                    data: data
                })
                .done( callback )
                .fail(function(jqXHR, textStatus, errorThrown) 
                {

                    // Glean what we can from textStatus
                    let msg;
                    if (textStatus === "parsererror") msg = "Error parsing the server response.";
                    else if (textStatus === "timeout") msg = "The request timed out. Please try again.";
                    else if (textStatus === "abort") msg = "The request was cancelled.";
                    else msg = "A network/server (AJAX) error occurred.";

                    msg += `\n\nError details: ${errorThrown || 'Unknown error'}`;

                    alert(msg);

                    console.error('AJAX request failed:', textStatus, errorThrown, jqXHR);
                });
            };

            /**
             * Callback for fetchLogParameterValue AJAX request.
             * Creates and displays a dialog with the full parameter value.
             * Gets a little hacky to deal with dialog sizing and scrolling.
             */
            EMLPV.fetchLogParameterValueCallback = function( response ){

                console.log('fetchLogParameterValue response:', response);

                // destroy any leftover dialog content divs to avoid DOM clutter
                $('div[id^="log_id-"][id*="-param-"]').remove();

                let content = '';

                let dlgId = '';

                let title = '';

                //response.data.record = 'fooDeluxe'; // for testing

                let tableHtml = EMLPV.logInfoTableHtml( response.data );

                if ( response.status !== 'success' ){

                    dlgId = `log_id-unknown-param-${EMLPV.logData.param_name}`;

                    content = response.status_message;

                    title = 'Error retrieving log parameter: ' + EMLPV.logData.param_name;
                }
                else {

                    dlgId = `log_id-${response.data.log_id}-param-${response.data.param_name}`;
                    content = response.data.param_value;
                    title = response.data.param_name;
                }

                // wrap the content in a scrolling element to help with sizing
                content = '<pre class="emlpv-scrolling-container" style="white-space: pre-wrap; word-break: normal; overflow-wrap: anywhere;">' 
                    + content 
                    + '</pre>';

                simpleDialog(
                    tableHtml + content, // inner HTML content
                    title, // title
                    dlgId, // content wrapper ID
                    1200 // width
                );

                /**
                 * The jQuery UI dialog, at least as implemented by the simpleDialog() function in REDCap,
                 * initially sizes the dialog based on the content length, potentially exceeding the viewport.
                 * 
                 * Therefore, the content is wrapped in a scrolling pre/div, with a max height set (400px).
                 * The dialog does not stretch vertically beyond the viewport, and the content wrapper is sized accordingly.
                 * 
                 * However, the resizing behavior does not automatically adjust the content area to fit within the dialog 
                 * in such a way as to avoid nested scrollbars.
                 * 
                 * Here we add a resize handler to adjust the content area to fit properly within the dialog
                 * whenever the dialog wrapper is resized. 
                 * 
                 * As a further measure, the content wrapper div is set to not scroll, in case my content resizing arithmetic fails.
                 */
                
                const $contentWrapper = $(`div#${dlgId}`);
                const $dialog = $contentWrapper.closest('.ui-dialog');
                const $innerContentWrapper = $contentWrapper.find('div, pre').first();

                $contentWrapper.addClass('emlpv-no-scrolling-container'); // disable outer div scrolling

                $dialog.on('resize', function() {
                    const tableHt = $contentWrapper.find('table.emlpv-log-info-table').outerHeight(true) || 0;
                    const contentWrapperHt = $contentWrapper.innerHeight(); // seems to resize correctly, so we fit the content inside it
                    const contentHt = contentWrapperHt - tableHt - 30; // padding/margin/fudge factor
                    $innerContentWrapper.css('max-height', contentHt + 'px');
                });

                $dialog.trigger('resize'); // initial sizing
            };

            EMLPV.logInfoTableHtml = function( logData ){

                let html = '<table id="emlpv-log-info-table" class="emlpv-log-info-table"><tbody>';

                // row 1: log_id
                html += `<tr><td>log_id</td><td>${logData.log_id}</td></tr>`;
                // row 2: timestamp
                html += `<tr><td>timestamp</td><td>${logData.timestamp}</td></tr>`;
                // row 3: module_name
                html += `<tr><td>module</td><td>${logData.module_name}</td></tr>`;

                // add a row for project_id if available
                if ( logData.project_id !== null && parseInt(logData.project_id) > 0 ){
                    html += `<tr><td>project_id</td><td>${logData.project_id}</td></tr>`;
                }

                // add a row for record if available
                if ( logData.record && logData.record !== 'undefined' ){
                    html += `<tr><td>record</td><td>${logData.record}</td></tr>`;
                }

                // add a row for user_name if available
                if ( logData.user_name && logData.user_name !== 'undefined' ){
                    html += `<tr><td>user</td><td>${logData.user_name}</td></tr>`;
                }

                // row K: message
                html += `<tr><td>message</td><td>${logData.message}</td></tr>`;
        

                html += '</tbody></table>';

                return html;
            };

            $( function () {

                //console.log('EMLogParamViewer active on logs.php');

                /**
                 * Click handler for "Show Parameters" buttons.
                 * 
                 * When a 'show parameters' button is clicked, the log data from that row is stored
                 * in EMLPV.logData for use by the parameter value click handler.
                 */

                 $(document)
                    .off('click.emlpv.show-parameters', 'table tr td button.show-parameters')
                    .on('click.emlpv.show-parameters', 'table tr td button.show-parameters', function(e) {

                    const button = $(this)[0]; // using the DOM element directly in this handler

                    // bail if the row does not have 6 or 7 columns (not a log entry row)
                    const cells = button.closest('tr').querySelectorAll('td');
                    if (cells.length !== 6 && cells.length !== 7) return;

                    if (cells.length === 7) {
                        EMLPV.logData = {
                            timestamp: cells[0].innerText,
                            module_name: cells[1].innerText,
                            project_id: cells[2].innerText,
                            record: cells[3].innerText,
                            message: cells[4].innerText,
                            user_name: cells[5].innerText,
                            param_name: null,
                            param_value: null
                        };
                    } else {
                        EMLPV.logData = {
                            timestamp: cells[0].innerText,
                            module_name: cells[1].innerText,
                            project_id: <?php echo $str_project_id ?>,
                            record: cells[2].innerText,
                            message: cells[3].innerText,
                            user_name: cells[4].innerText,
                            param_name: null,
                            param_value: null
                        };
                    }
                });

                /**
                 * Click handler for parameter value cells.
                 * 
                 * When a parameter value cell is clicked, an AJAX request is made to fetch the full
                 * parameter value from the server, and display it in a new dialog.
                 */

                $(document)
                    .off('click.emlpv.log-parameter', 'table.log-parameters tr td:nth-child(2)')
                    .on('click.emlpv.log-parameter', 'table.log-parameters tr td:nth-child(2)', function(e) {

                    const td = $(this)[0]; // using the DOM element directly in this handler

                    const cells = td.closest('tr').querySelectorAll('td'); // get all TDs in the row

                    EMLPV.logData.param_name = cells[0].innerText; // first TD is param_name
                    EMLPV.logData.param_value = cells[1].innerText; // second TD is param_value (possibly truncated)

                    EMLPV.ajax( 'fetchLogParameterValue', 
                        EMLPV.logData, 
                        EMLPV.fetchLogParameterValueCallback 
                    );
                });
            });

        </script>
        <?php
    }

    /**
     * Normalize text for concordance between DOM textContent and stored DB values.
     *
     * Goals:
     * - Make line endings consistent
     * - Remove invisible / zero-width junk that often sneaks in
     * - Normalize Unicode (NFC) when possible
     * - Optionally collapse whitespace runs (OFF by default)
     * - Normalize non-breaking spaces to regular spaces
     *
     * Requires: ext-mbstring (recommended). ext-intl optional for Unicode normalization.
     * 
     * Credit: GPT 5.2
     */
    function normalize_for_compare(string $s, array $opt = []): string
    {
        $opt = array_merge([
            'trim' => true,
            'normalize_line_endings' => true,
            'unicode_normalize' => true,   // uses Normalizer if available
            'remove_zero_width' => true,
            'nbsp_to_space' => true,
            'collapse_whitespace' => false, // keep OFF to match textContent semantics
            'collapse_newlines' => false,   // only relevant if collapse_whitespace = true
        ], $opt);

        // Ensure it's valid UTF-8; if not, best effort convert.
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        if ($opt['normalize_line_endings']) {
            // Convert CRLF and CR to LF
            $s = str_replace(["\r\n", "\r"], "\n", $s);
        }

        if ($opt['remove_zero_width']) {
            // Remove common invisible characters:
            // - ZERO WIDTH SPACE (200B)
            // - ZERO WIDTH NON-JOINER (200C)
            // - ZERO WIDTH JOINER (200D)
            // - WORD JOINER (2060)
            // - BOM / ZERO WIDTH NO-BREAK SPACE (FEFF)
            $s = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $s);
        }

        if ($opt['nbsp_to_space']) {
            // Replace NBSP (U+00A0) with regular space
            $s = str_replace("\xC2\xA0", " ", $s);
        }

        if ($opt['unicode_normalize'] && class_exists('\Normalizer')) {
            // NFC tends to be best for human text equality
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C) ?? $s;
        }

        if ($opt['collapse_whitespace']) {
            if ($opt['collapse_newlines']) {
                // Collapse all whitespace including newlines/tabs into single spaces
                $s = preg_replace('/\s+/u', ' ', $s);
            } else {
                // Collapse spaces/tabs etc but keep newlines meaningful
                // 1) collapse horizontal whitespace
                $s = preg_replace('/[ \t\f\v]+/u', ' ', $s);
                // 2) normalize multiple blank lines to single blank line (optional-ish)
                $s = preg_replace("/\n{3,}/u", "\n\n", $s);
            }
        }

        if ($opt['trim']) {
            // Trim normal whitespace plus NBSP just in case
            $s = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $s);
        }

        return $s;
    }

    // Helper functions to map between user IDs/usernames and module IDs/modulenames

    public function getUsernameFromId( $ui_id ): ?string
    {
        $sql = "SELECT ui.username FROM redcap_user_information ui WHERE ui.ui_id = ? LIMIT 1";
        $result = $this->query($sql, [$ui_id]);
        if ($result) {
            $row = $result->fetch_assoc();
            return (string)$row['username'] ?? null;
        }
        return null;
    }

    public function getUserIdFromUsername( $username ): ?int
    {
        $sql = "SELECT ui.ui_id FROM redcap_user_information ui WHERE ui.username = ? LIMIT 1";
        $result = $this->query($sql, [$username]);
        if ($result) {
            $row = $result->fetch_assoc();
            return isset($row['ui_id']) ? (int)$row['ui_id'] : null;
        }
        return null;
    }

    public function getModulenameFromId( $external_module_id ): ?string
    {
        $sql = "SELECT em.directory_prefix FROM redcap_external_modules em WHERE em.external_module_id = ? LIMIT 1";
        $result = $this->query($sql, [$external_module_id]);
        if ($result) {
            $row = $result->fetch_assoc();
            return (string)$row['directory_prefix'] ?? null;
        }
        return null;
    }

    public function getModuleIdFromModulename( $module_name ): ?int
    {
        $sql = "SELECT em.external_module_id FROM redcap_external_modules em WHERE em.directory_prefix = ? LIMIT 1";
        $result = $this->query($sql, [$module_name]);
        if ($result) {
            $row = $result->fetch_assoc();
            return isset($row['external_module_id']) ? (int)$row['external_module_id'] : null;
        }
        return null;
    }

    /**
     * Compares two strings, the first of which may be truncated.
     * Both strings are normalized before comparison.
     * 
     * @param mixed $truncatedString 
     * @param mixed $compareString 
     * @return bool 
     */
    public function truncatedStringMatch( $truncatedString, $compareString ): bool
    {
        // remove any trailing ellipsis from the truncated string
        if ( substr( $truncatedString, -3 ) === '...' ){

            $truncatedString = substr( $truncatedString, 0, -3 );
        }
        // also handle Unicode ellipsis character (just anticipating possible future issues)
        if ( substr( $truncatedString, -1 ) === '…' ){
            $truncatedString = substr( $truncatedString, 0, -1 );
        }

        // normalize both strings
        $truncatedString = $this->normalize_for_compare( $truncatedString );
        $compareString = $this->normalize_for_compare( $compareString );

        // if we're lucky, they are identical
        if (strcmp( $truncatedString, $compareString ) === 0) return true;

        // reject if truncatedString is longer than compareString
        if ( strlen( $truncatedString ) > strlen( $compareString ) ) return false;

        // accept if compareString starts with truncatedString
        if ( strcmp( substr( $compareString, 0, strlen( $truncatedString ) ), $truncatedString ) === 0 ) return true;

        return false;
    }

    /**
     * Examine a string, and if it is valid JSON then return a pretty-printed JSON string for better readability.
     * Otherwise return the original value.
     * 
     * @param string $input 
     * @return null|string 
     */
    public function prettyPrintJsonIfValid(string $input): ?string
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return $input;
        }

        // Decode as associative arrays (true). You can use false for stdClass objects.
        // JSON_THROW_ON_ERROR ensures we don't rely on json_last_error() state.
        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $input;
        }

        // Revert to original value if not an array (i.e., is a scalar value)
        if (!is_array($decoded)) {
            return $input;
        }

        // Pretty print. UNESCAPED_* options keep it readable for URLs and unicode.
        return json_encode(
            $decoded,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
