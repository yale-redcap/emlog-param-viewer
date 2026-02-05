<?php

namespace Yale\EMLogParamViewer;

$module = new EMLogParamViewer();

$log_id = $_POST['log_id'] ?? null;

// bail if log_id is not provided or is not numeric
if ( !$log_id || !is_numeric($log_id) ){
    exit($module->returnFailObject("error: log_id is required and must be numeric."));
}

$item_type = $_POST['item_type'] ?? '';

if ( $item_type !== 'message' && $item_type !== 'parameter' ){

    exit($module->returnFailObject("error: invalid item_type provided."));
}

if ( $item_type === 'parameter' ){

    $param_name = $_POST['param_name'] ?? '';

    if ( !$param_name ){

        exit($module->returnFailObject("error: param_name is required when item_type is 'parameter'."));
    }
}
            
$params = [ $log_id ];

if ( $item_type === 'message' ){

    $sql = "SELECT log.*
            FROM redcap_external_modules_log log
            WHERE log.log_id=?";
}
else {
    $sql = "SELECT log.*, prm.`value` as param_value
            FROM redcap_external_modules_log log
            INNER JOIN redcap_external_modules_log_parameters prm
            ON prm.log_id=log.log_id
            WHERE log.log_id=? AND prm.`name`=?";

    $params[] = $param_name;
}

$result = $module->query( $sql, $params );

$data = $result->fetch_assoc();

if ( !$data ){

    exit($module->returnFailObject("The requested log item value could not be found."));
}

$data['item_type'] = $item_type;

// Pretty print the param_value if it is valid JSON
$data['param_value'] = $module->prettyPrintJsonIfValid( $data['param_value'] ?? '' );

$data['param_name'] = $param_name ?? '';

$data['user_name'] = $module->getUsernameFromId( $data['ui_id'] );

$data['module_name'] = $module->getModulenameFromId( $data['external_module_id'] );

exit($module->returnSuccessObject("The requested log {$item_type} was retrieved.", $module->escape($data) ));
