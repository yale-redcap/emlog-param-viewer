<?php

namespace Yale\EMLogParamViewer;

class EMLogParamViewer extends \ExternalModules\AbstractExternalModule
{

    function redcap_every_page_top($project_id)
    {
        if ( PAGE !== "manager/logs.php" ) {
            return;
        }

        $serviceUrl = $this->getUrl('fetchEmLogItem.php'); // for AJAX requests

        $str_project_id = is_numeric($project_id) ? strval($project_id): 'null'; // for JS injection

        ?>

        <style>

            /* Styles for EM Log Parameter value cells */

            table.log-parameters tr > td:nth-child(2),
            table#DataTables_Table_0 tr > td.message-column {
                cursor: pointer;
            }

            table.log-parameters tr > td:nth-child(2):hover,
            table#DataTables_Table_0 tr > td.message-column:hover {
                text-decoration: underline;
                text-decoration-style: solid;
                text-underline-offset: 2px;
            }

            /*styles for  dialog inner div (content) */

            .emlpv-scrolling-container {

                scrollbar-color: gray;
                scrollbar-width: 10px;
                max-height: 400px;
                overflow-y: auto;
            }

            .emlpv-scrolling-container::-webkit-scrollbar-track {
                background-color: lightgray;
            }

            .emlpv-scrolling-container::-webkit-scrollbar {
                width: 10px;
                background-color: lightgray;
            }

            .emlpv-scrolling-container::-webkit-scrollbar-thumb {
                background-color: gray;
            }

            .emlpv-no-scrolling-container {
                overflow: visible !important;
            }

            /* styles for log record info header */

            .emlpv-ellipsis {  
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
            // Namespace object for use in emlpv.js
            const EMLPV = {
                serviceUrl: '<?php echo $serviceUrl ?>',
                projectId: <?php echo $str_project_id ?>,
            };
        </script>

        <script src="<?php echo $this->getUrl('js/emlpv.js'); ?>"></script>

        <?php
    }

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

    public function returnFailObject($message, $data=null){
        return json_encode( [
            'status' => 'fail',
            'status_message' => $message,
            'data' => $data
        ] );
    }

    public function returnSuccessObject($message, $data){
        return json_encode( [
            'status' => 'success',
            'status_message' => $message,
            'data' => $data
        ] );
    }
}
