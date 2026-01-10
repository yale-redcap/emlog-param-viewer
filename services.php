<?php

namespace Yale\EMLogParamViewer;

$request = $_POST['request'] ?? '';

$module = new EMLogParamViewer();

execRequest($request);

/**
 * Handle the reported error by going toes-up.
 */
function toesUp($errmsg)
{
    throw new \Exception("EMLogParamViewer Services reports ".$errmsg);  
}

function execRequest( $request ){
    /**
     * list of allowed function requests.
     * Can be updated using output from listNamespaceFunctions()
     */
    $function_registry = [
        'fetchLogParameterValue'
    ];

    $fnIndex = array_search( trim($request), $function_registry );

    if ( $fnIndex===false ){

        toesUp("error: invalid request.");
    }

    // execute the requested function
    exit( call_user_func( __NAMESPACE__ . "\\". $function_registry[ $fnIndex ] ) );
}

/**
 * Fetch the value of a log parameter based on provided criteria.
 * 
 * In the absence of log_id, we have to do our best to find the correct log entry:
 * 
 * (1) Find log entries matching param_name, timestamp, project_id (or IS NULL), ui_id (or IS NULL), external_module_id (or IS NULL)
 * (2) From those results, find matches on message and param_value (exact or truncated)
 * (3) If exactly one match is found, return the param_value
 * (4) If multiple matches are found, return an error indicating multiple matches
 * (5) If no matches are found, return an error indicating no matches
 */
function fetchLogParameterValue(){
    global $module;

    $logData = [
        'timestamp' => $_POST['timestamp'] ?? '',
        'module_name' => $_POST['module_name'] ?? '',
        'project_id' => $_POST['project_id'] ?? '',
        'record' => $_POST['record'] ?? '',
        'message' => $_POST['message'] ?? '',
        'user_name' => $_POST['user_name'] ?? '',
        'param_name' => $_POST['param_name'] ?? '',
        'param_value' => $_POST['param_value'] ?? '',
    ];

    /**
     * Build the SQL query to find matching log entries.
     * Initial matches are done on:
     * - param_name
     * - timestamp
     * - project_id (or IS NULL for system-level logs)
     * - ui_id (or IS NULL if user has been deleted)
     * - external_module_id (or IS NULL if EM has been uninstalled)
     * 
     * Then we can look for a match on message and param_value in the results.
     * These will be partial matches for truncated strings.
     */

    $sql = "
SELECT prm.name as param_name, prm.`value` as param_value, log.* 
FROM redcap_external_modules_log_parameters prm
INNER JOIN redcap_external_modules_log log
ON prm.log_id=log.log_id
WHERE prm.`name`=? AND log.timestamp=?
    ";

    // Always have param_name and timestamp 
    $qparams = [
        $logData['param_name'],
        $logData['timestamp']
    ];

    // project_id is provided in the log display?
    if ( $logData['project_id'] !== '' && is_numeric( $logData['project_id'] ) ){

        $sql .= " AND log.project_id=?";
        $qparams[] = $logData['project_id'];
    } else {

        $sql .= " AND log.project_id IS NULL";
    }

    // user_name is provided in the log display?
    // note: user may have been deleted, in which case it cannot be a match criterion
    $ui_id = null;
    if ( $logData['user_name'] ){

        $ui_id = $module->getUserIdFromUsername( $logData['user_name'] );

        if ( $ui_id && is_numeric( $ui_id ) ){

            $sql .= " AND log.ui_id=?";
            $qparams[] = $ui_id;
        }
    } else {

        $sql .= " AND log.ui_id IS NULL";
    }

    // module_name is provided in the log display?
    // note: module may have been uninstalled (I think), in which case it cannot be a match criterion
    $external_module_id = null;
    if ( $logData['module_name'] ){

        $external_module_id = $module->getModuleIdFromModulename( $logData['module_name'] );

        if ( $external_module_id && is_numeric( $external_module_id ) ){

            $sql .= " AND log.external_module_id=?";
            $qparams[] = $external_module_id;
        }
    } else {

        $sql .= " AND log.external_module_id IS NULL";
    }

    $results = $module->query( $sql, $qparams );

    $rows = []; // array of all rows returned from the db
    
    while ( $row = $results->fetch_assoc() ){

        $ui_id = (int)($row['ui_id'] ?? null);
        $user_name = null;
        if ( $ui_id ){

            $user_name = $module->getUsernameFromId( $ui_id );
        }

        $external_module_id = (int)($row['external_module_id'] ?? null);
        $module_name = null;
        if ( $external_module_id ){ 

            $module_name = $module->getModulenameFromId( $external_module_id );
        }
        
        $rows[] = [
            'log_id' => (int)$row['log_id'],
            'external_module_id' => $external_module_id,
            'project_id' => (int)$row['project_id'],
            'user_name' => $user_name,
            'module_name' => $module_name,
            'timestamp' => $row['timestamp'],
            'message' => $row['message'],
            'param_name' => $module->escape($row['param_name']),
            'param_value' => $row['param_value'],
        ];
    }

    $status = "fail";
    $matches = []; // array of matching rows from the db

    /**
     * Now determine a match based on message and param_value.
     * 
     * If there's only one row returned from the db, no need to check further.
     * 
     * If there are multiple rows, we need to check each one for a match.
     * A match is defined as:
     * - message matches (exact or truncated)
     * - param_value matches (exact or truncated)
     * 
     * If multiple matches are found, we return an error indicating multiple matches.
     * If no matches are found, we return an error indicating no matches.
     * If exactly one match is found, we return the param_value.
     */

    if ( count( $rows ) == 1 ){

        $matches = $rows;
    }
    elseif ( count( $rows ) > 1 ){
        $debug = [];
        foreach ( $rows as $row ){

            $debug[] = [
                'log_message' => $logData['message'],
                'row_message' => $row['message'],
                'log_param_value' => $logData['param_value'],
                'row_param_value' => $row['param_value']
            ];

            $match_message = $module->truncatedStringMatch( $logData['message'], $row['message'] );
            $match_value = $module->truncatedStringMatch( $logData['param_value'], $row['param_value'] );
            if ( $match_message && $match_value ){

                $matches[] = $row;
            }
        }
    }

    $status_message = '';
    $data = null;

    if ( count($matches) === 1 ){

        $status = "success";
        $status_message = 'The requested log parameter value was retrieved.';

        // reformat JSON value if applicable, to make it more readable in the viewer
        $matches[0]['param_value'] = $module->prettyPrintJsonIfValid($matches[0]['param_value']);

        $data = $module->escape( $matches[0] );
    }
    else if ( count($matches) > 1 ){

        $status_message = 'Multiple matching log entries were found.';
    }
    else {

        $status_message = 'No matching log entries were found.';
    }

    return json_encode( [
        'status' => $status, // success|fail
        'status_message' => $status_message, // human-readable message. If fail, indicates reason.
        'data' => $data // the matched row, or null
    ] );
}