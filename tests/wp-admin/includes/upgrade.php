<?php
function dbDelta($sql){
    $GLOBALS['dbdelta_sql']=$sql;
    if (($GLOBALS['schema_mode'] ?? '') === 'dbdelta_error' && isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb']->last_error = 'dbDelta simulated failure';
    }
    return ['mock'=>'created'];
}
