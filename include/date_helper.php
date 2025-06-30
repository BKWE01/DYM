<?php
// includes/date_helper.php

function getSystemStartDate() {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/system_config.php';
    }
    return $config['system_start_date'];
}

function getFilteredDateCondition($columnName = 'created_at') {
    return "$columnName >= '" . getSystemStartDate() . "'";
}