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

            table.log-parameters tr > td:nth-child(2),
            table#DataTables_Table_0 tr > td.message-column {
                cursor: pointer;
                /*text-decoration: underline dotted;*/
            }

            table.log-parameters tr > td:nth-child(2):hover,
            table#DataTables_Table_0 tr > td.message-column:hover {
                text-decoration: underline;
                text-decoration-style: solid;
                text-underline-offset: 2px;
            }
            /*
            table.log-parameters tr:nth-child(1) > th:nth-child(2)::after {
                content: " (click to view full value)";
                color: gray;
            }
            */

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
                serviceUrl: '<?php echo $serviceUrl ?>',
                logData: null,
                projectId: <?php echo $str_project_id ?>,
            };
        </script>

        <script src="<?php echo $this->getUrl('js/emlpv.js'); ?>"></script>

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
    function normalize_for_compare($s, array $opt = []): string
    {
        if (!is_string($s)) {
            return '';
        }
    
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
    public function prettyPrintJsonIfValid($input): ?string
    {
        $trimmed = trim($input);
        if ( !$trimmed ) {
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
