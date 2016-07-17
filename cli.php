<?php
include (dirname(__FILE__) . "/Classes/ClassGenerator.php");
$generator = new ClassGenerator();
if ($generator->isCommandLineInterface()) {
    
    /**
     * Start the Generation
     */
    
    $DBSetting = include (dirname(__FILE__) . "/dbconfig.php");
    
    $tables = $generator->getTables();
    echo "Available tables in " . $DBSetting['dbname'] . " database: \n";
    $c = 1;
    $tableArray = array();
    foreach ($tables as $table):
        if (isset($table['primaryKey']) and !empty($table['primaryKey'])) {
            echo $c . " : " . $table['tableName'] . "\n";
            $tableArray[$c] = $table['tableName'];
            $c++;
        } else {
            echo "- : " . $table['tableName'] . " - Primary Key not found\n";
        }
    endforeach;
    echo "Please select table number you would like to generate class for: ";
    
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (!is_numeric(trim($line))) {
        echo "Wrong selection, Please start over. \n";
        echo "ABORTING!\n";
        exit;
    } else {
        $selected_DB_ID = trim($line);
        echo "Selected '" . $tableArray[$selected_DB_ID] . "' table \n";
        echo "Would you like to generate PHP Class for " . $tableArray[$selected_DB_ID] . " [Y/n]: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (strtolower(trim($line)) == "y" || strtolower(trim($line)) == "yes") {
            echo "Generating class file... \n";
            $generator->setTable($tableArray[$selected_DB_ID]);
            echo $generator->writeClass($generator->buildClass());
        } else {
            echo "ABORTING!\n";
        }
    }
    
    echo "\n";
    echo "Thank you\n";
} else {
    echo "Please run this script command line";
    exit();
}
