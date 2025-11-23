<?php
// Require Database Class
require_once ("Database.php");

class ClassGenerator {
	/**
	 * holds database connection
	 * @var object
	 */
	protected $db;

	/**
	 * holds selected table
	 * @var string
	 */
	protected $table;

	/**
	 * holds column names
	 * @var array
	 */
	protected $columns;

	/**
	 * holds primary key column
	 * @var [type]
	 */
	protected $primaryKey;

	/**
	 * holds foreign key relationships
	 * @var array
	 */
	protected $relationships = array();

	/**
	 * Directory to write generated class
	 * @var string
	 */
	protected $directoryForGeneratedClasses = "GeneratedClasses";

	/**
	 * Start new database class
	 * @return object
	 */
	public function __construct() {
		$this->db = new Database();
	}

	/**
	 * Checks if request is via CLI or WEB
	 * @return boolean
	 */
	public function isCommandLineInterface() {
		return (php_sapi_name() === 'cli');
	}

	/**
	 * list all tables
	 * @return array
	 */
	public function getTables() {
		$sql        = "SHOW TABLES";
		$tables     = $this->db->query($sql);
		$c          = 0;
		$tableArray = array();
		foreach ($tables as $table):
			foreach ($table as $tableName):
				$tableArray[$c]['tableName'] = $tableName;
				$sql                         = "SHOW COLUMNS FROM " . $tableName;
				$columns                     = $this->db->query($sql);
				foreach ($columns as $k => $column):
					if ($column['Key'] == 'PRI'):
						$tableArray[$c]['primaryKey'] = $column['Field'];
					endif;
				endforeach;
				$c++;
			endforeach;
		endforeach;
		return $tableArray;
	}

	/**
	 * Sets selected table and columns
	 * @param string $table
	 * @return  void
	 */
	public function setTable($table) {
		$this->table = $table;
		$this->setColumns();
	}

	/**
	 * Sets table columns to object
	 * @return void
	 */
	private function setColumns() {
		$sql     = "SHOW COLUMNS FROM " . $this->table;
		$columns = $this->db->query($sql);
		foreach ($columns as $k => $column):
			if ($column['Key'] == 'PRI'):
				$this->primaryKey = $column['Field'];
			else:
				$this->columns[] = $column['Field'];
			endif;
		endforeach;
		$this->setRelationships();
	}

	/**
	 * Detects and sets foreign key relationships
	 * @return void
	 */
	private function setRelationships() {
		// Detect belongsTo relationships (foreign keys in current table)
		$this->detectBelongsTo();
		// Detect hasMany relationships (foreign keys in other tables pointing to this one)
		$this->detectHasMany();
	}

	/**
	 * Detects belongsTo relationships (foreign keys in current table)
	 * @return void
	 */
	private function detectBelongsTo() {
		$sql = "SELECT
				COLUMN_NAME,
				REFERENCED_TABLE_NAME,
				REFERENCED_COLUMN_NAME
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = '" . $this->table . "'
				AND REFERENCED_TABLE_NAME IS NOT NULL";

		$foreignKeys = $this->db->query($sql);

		foreach ($foreignKeys as $fk):
			$this->relationships[] = array(
				'type' => 'belongsTo',
				'foreignKey' => $fk['COLUMN_NAME'],
				'relatedTable' => $fk['REFERENCED_TABLE_NAME'],
				'relatedKey' => $fk['REFERENCED_COLUMN_NAME'],
				'methodName' => $this->generateMethodName($fk['REFERENCED_TABLE_NAME'], 'belongsTo')
			);
		endforeach;
	}

	/**
	 * Detects hasMany relationships (foreign keys in other tables)
	 * @return void
	 */
	private function detectHasMany() {
		$sql = "SELECT
				TABLE_NAME,
				COLUMN_NAME,
				REFERENCED_COLUMN_NAME
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = DATABASE()
				AND REFERENCED_TABLE_NAME = '" . $this->table . "'";

		$foreignKeys = $this->db->query($sql);

		foreach ($foreignKeys as $fk):
			$this->relationships[] = array(
				'type' => 'hasMany',
				'foreignKey' => $fk['COLUMN_NAME'],
				'relatedTable' => $fk['TABLE_NAME'],
				'relatedKey' => $fk['REFERENCED_COLUMN_NAME'],
				'methodName' => $this->generateMethodName($fk['TABLE_NAME'], 'hasMany')
			);
		endforeach;
	}

	/**
	 * Generates a method name for relationship
	 * @param string $tableName
	 * @param string $type
	 * @return string
	 */
	private function generateMethodName($tableName, $type) {
		// Convert table name to singular for belongsTo, keep plural for hasMany
		if ($type === 'belongsTo'):
			// Simple singularization (can be improved)
			if (substr($tableName, -3) === 'ies'):
				$methodName = substr($tableName, 0, -3) . 'y';
			elseif (substr($tableName, -1) === 's'):
				$methodName = substr($tableName, 0, -1);
			else:
				$methodName = $tableName;
			endif;
		else:
			// Keep plural for hasMany
			$methodName = $tableName;
		endif;

		return $methodName;
	}

	/**
	 * Gets relationship information for display
	 * @return string
	 */
	public function getRelationshipInfo() {
		if (empty($this->relationships)):
			return "";
		endif;

		$info = "";
		if ($this->isCommandLineInterface()) {
			$info .= "\nDetected Relationships:\n";
			foreach ($this->relationships as $relation):
				$type = $relation['type'] === 'belongsTo' ? 'BelongsTo' : 'HasMany';
				$info .= "  - " . $type . ": " . $relation['methodName'] . "() -> " . $relation['relatedTable'] . "\n";
			endforeach;
		} else {
			$info .= "<div class='alert alert-info' role='alert'><i class='fa fa-link'></i> <strong>Detected Relationships:</strong><ul>";
			foreach ($this->relationships as $relation):
				$type = $relation['type'] === 'belongsTo' ? 'BelongsTo' : 'HasMany';
				$info .= "<li>" . $type . ": <code>" . $relation['methodName'] . "()</code> → " . $relation['relatedTable'] . "</li>";
			endforeach;
			$info .= "</ul></div>";
		endif;
		return $info;
	}

	/**
	 * Writes generated class to file
	 * @param  string $string generated class
	 * @return string
	 */
	public function writeClass($string) {
		$result                 = "";
		$directoryToCreateClass = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->directoryForGeneratedClasses . DIRECTORY_SEPARATOR;
		if (!is_writable($directoryToCreateClass)):
			if ($this->isCommandLineInterface()) {
				$result .= $directoryToCreateClass . " is not writable. \n";
			} else {
			$result .= "<div class='alert alert-danger' role='alert'><i class='fa fa-exclamation-triangle'></i> <strong>" . $directoryToCreateClass . "</strong> is not writable</div><br />";
		}
		endif;
		$file = $directoryToCreateClass . $this->table . '.php';
		if (@file_put_contents($file, $string, LOCK_EX)):
			if ($this->isCommandLineInterface()) {
				$result .= "Class file with filname: " . DIRECTORY_SEPARATOR . $this->directoryForGeneratedClasses . DIRECTORY_SEPARATOR . $this->table . '.php' . " has been Generated Sucessfully\n";
				$result .= $this->getRelationshipInfo();
			} else {
			$result .= "<div class='alert alert-success' role='alert'> <i class='fa fa-check-square'></i> Class file with filname: <strong>" . DIRECTORY_SEPARATOR . $this->directoryForGeneratedClasses . DIRECTORY_SEPARATOR . $this->table . '.php' . "</strong> has been Generated Sucessfully</div>";
			$result .= $this->getRelationshipInfo();
		} else :
			if ($this->isCommandLineInterface()) {
				$result .= $file . " is not writable, \n Sorry, file was not created for you, probably permission issue,\n Please first try: chmod 777 GeneratedClasses";
			} else {
			$result = "<div class='alert alert-danger' role='alert'><i class='fa fa-exclamation-triangle'></i> <strong>" . $file . "</strong> is not writable</div><br />";
			$result .= "Sorry, file was not created for you, probably permission issue, but you can still copy source from 'Generated Code' tab.";
		}
		endif;
		return $result;
	}

	/**
	 * Builds the generated class
	 * @return string
	 */
	public function buildClass() {

		$output = '
<?php
require_once (realpath(dirname(__FILE__)."/../")."/Classes/Database.php");
/**
 * Class: ' . $this->table . '
 * Generation Date: ' . date('Y-m-d H:i:s') . '
 */
class ' . $this->table . '{
    /**
     * Table Name
     * @var string
     */
    protected $table = "' . $this->table . '";

    /**
     * primary Key
     * @var string
     */
    protected $PRI = "' . $this->primaryKey . '";

    /**
     * Holds Database Connection
     * @var Resourse
     */
    private $db;

    /**
     * Select limit
     * @var mixed
     */
    private $limit = false;

    /**
     * Holds requested variables
     * @var Array
     */
    public $variables = array();

    /**
     * Class construct fucntion
     * calls Database class and set DB Object
     * @return void
     */
    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Magic method to set objects
     * @param mixed $name  column name
     * @param mixed $value column value
     */
    public function __set($name, $value) {
        if (strtolower($name) === strtolower($this->PRI)):
            $this->variables[$this->PRI] = $value;
        else:
            $this->variables[$name] = $value;
        endif;
    }

    /**
     * Magic method to get object
     * @param  mixed $name column name
     * @return mixed
     */
    public function __get($name) {
        if (is_array($this->variables)):
            if (array_key_exists($name, $this->variables)):
                return $this->variables[$name];
            endif;
        endif;
        $trace = debug_backtrace();
        trigger_error("Undefined property via __get(): " . $name . " in " . $trace[0]["file"] . " on line " . $trace[0]["line"], E_USER_NOTICE);
        return null;
    }

    /**
     * Set query limit
     * @param  string $limit
     * @return void
     */
    public function setLimit($limit){
    	$this->limit = $limit;
    }

    /**
     * alias to setLimit
     * @param string $limit
     * @return void
     */
    public function limit($limit){
        $this->setLimit($limit);
    }

    /**
     * Reset method for class
     * method will reset all previous variables
     * @return void
     */
    public function reset() {
        $this->variables = array();
        $this->limit = false;
    }

    /**
     * Update method
     * Updates exsist Record
     * @param  int $id primary key
     * @return int
     */
    public function update($id = "0") {
        $this->variables[$this->PRI] = (empty($this->variables[$this->PRI])) ? $id : $this->variables[$this->PRI];
        $fieldsvals = "";
        $columns = array_keys($this->variables);
        foreach ($columns as $column):
            if ($column !== $this->PRI):
            	$fieldsvals.= $column . " = :" . $column . ",";
           	endif;
        endforeach;
        $fieldsvals = substr_replace($fieldsvals, "", -1);
        if (count($columns) > 1):
            $sql = "UPDATE " . $this->table . " SET " . $fieldsvals . " WHERE " . $this->PRI . "= :" . $this->PRI;
            return $this->db->query($sql, $this->variables);
        endif;
    }

    /**
     * add method
     * Adds new record to Table
     * @return  mixed
     */
    public function add() {
        $bindings = $this->variables;
        if (!empty($bindings)):
            $fields = array_keys($bindings);
            $fieldsvals = array(implode(",", $fields), ":" . implode(",:", $fields));
            $sql = "INSERT INTO " . $this->table . " (" . $fieldsvals[0] . ") VALUES (" . $fieldsvals[1] . ")";
        else:
            return false;
        endif;
        $this->db->query($sql, $bindings);
        return $this->db->lastInsertId();
    }

    /**
     * find method
     * Method performs LIKE search
     * @param  string $key   table column
     * @param  string $value value to find
     * @param  string $limit query limit
     * @return mixed
     */
    public function find($key = "", $value = "", $limit ="") {
        $bindings = $this->variables;
        if (!empty($bindings)):
            $fields = array_keys($bindings);
            if (empty($fields[0])):
                return false;
            endif;
            $sql = "SELECT * FROM " . $this->table . " WHERE ";
            foreach ($fields as $k => $field):
                $sql.= $field . " LIKE concat(\"%\", :" . $field . ", \"%\")";
                if (count($fields) > 1 and (count($fields) - 1) != $k):
                    $sql.= " OR ";
                endif;
            endforeach;
        else:
            $this->variables[$key] = $value;
            return $this->find();
        endif;
        if (!empty($limit) OR isset($this->limit) and !empty($this->limit)):
            $sql.= " LIMIT " . ((!empty($limit)) ? $limit : $this->limit);
        endif;
        return $this->db->query($sql, $bindings);
    }

    /**
     * delete method
     * Method Deleted record from table
     * @param  int $id Records primary key
     * @return mixed
     */
    public function delete($id = "") {
        $id = (empty($this->variables[$this->PRI])) ? $id : $this->variables[$this->PRI];
        if (!empty($id)) :
            $sql = "DELETE FROM " . $this->table . " WHERE " . $this->PRI . "= :" . $this->PRI . " LIMIT 1";
            return $this->db->query($sql, array($this->PRI => $id));
        endif;
    }

    /**
     * get_' . $this->primaryKey . ' Method
     * Returns Record selected with primary key
     * @param  int $id primary key
     * @return mixed
     */
    public function get_' . $this->primaryKey . '($id = "") {
        $id = (empty($this->variables[$this->PRI])) ? $id : $this->variables[$this->PRI];
        if (!empty($id)):
            $sql = "SELECT * FROM " . $this->table . " WHERE " . $this->PRI . "= :" . $this->PRI . " LIMIT 1";
            return $this->db->query($sql, array($this->PRI => $id));
        endif;
        return false;
    }
    ';
		foreach ($this->columns as $column):
			$output .= '
    /**
     * get_' . $column . '
     * Returns Records selected with ' . $column . '
     * @param  string $val  Value
     * @param  string $limit number of records to be returned
     * @return mixed
     */
    public function get_' . $column . '($val = "", $limit = "") {
        if (!empty($val) OR isset($this->variables["' . $column . '"]) AND !empty($this->variables["' . $column . '"])):
            $sql = "SELECT * FROM " . $this->table . " WHERE ' . $column . '=:' . $column . ' ";
            if (!empty($limit) OR isset($this->limit) and !empty($this->limit)):
                $sql.= "LIMIT " . (!empty($limit)) ? $limit : $this->limit;
            endif;
            return $this->db->query($sql, array("' . $column . '" => (!empty($val)) ? $val : $this->variables["' . $column . '"]));
        endif;
        return false;
    }
		    ';
		endforeach;

		// Add relationship methods
		foreach ($this->relationships as $relation):
			if ($relation['type'] === 'belongsTo'):
				$output .= '
    /**
     * ' . $relation['methodName'] . '
     * BelongsTo relationship - gets related ' . $relation['relatedTable'] . ' record
     * @return mixed
     */
    public function ' . $relation['methodName'] . '() {
        if (!isset($this->variables["' . $relation['foreignKey'] . '"]) || empty($this->variables["' . $relation['foreignKey'] . '"])):
            return false;
        endif;

        $relatedClass = include_once(realpath(dirname(__FILE__)."/../' . $this->directoryForGeneratedClasses . '/' . $relation['relatedTable'] . '.php"));
        if (!$relatedClass):
            trigger_error("Related class ' . $relation['relatedTable'] . ' not found", E_USER_WARNING);
            return false;
        endif;

        $result = $relatedClass->get_' . $relation['relatedKey'] . '($this->variables["' . $relation['foreignKey'] . '"]);
        return !empty($result) ? $result[0] : false;
    }
		    ';
			elseif ($relation['type'] === 'hasMany'):
				$output .= '
    /**
     * ' . $relation['methodName'] . '
     * HasMany relationship - gets related ' . $relation['relatedTable'] . ' records
     * @param  string $limit Query limit
     * @return mixed
     */
    public function ' . $relation['methodName'] . '($limit = "") {
        if (!isset($this->variables["' . $relation['relatedKey'] . '"]) || empty($this->variables["' . $relation['relatedKey'] . '"])):
            return false;
        endif;

        $relatedClass = include_once(realpath(dirname(__FILE__)."/../' . $this->directoryForGeneratedClasses . '/' . $relation['relatedTable'] . '.php"));
        if (!$relatedClass):
            trigger_error("Related class ' . $relation['relatedTable'] . ' not found", E_USER_WARNING);
            return false;
        endif;

        return $relatedClass->get_' . $relation['foreignKey'] . '($this->variables["' . $relation['relatedKey'] . '"], $limit);
    }
		    ';
			endif;
		endforeach;

		$output .= '
    /**
     * all
     * returns all records from table
     * @param  $limit Query limit
     * @return mixed
     */
    public function all($limit = "") {
        $sql = "SELECT * FROM " . $this->table;
        if (!empty($limit) OR isset($this->limit) and !empty($this->limit)):
            $sql.= " LIMIT " . ((!empty($limit)) ? $limit : $this->limit);
        endif;
        return $this->db->query($sql);
    }

    /**
     * paginate
     * returns all records from table with limits
     * @param  int $page Page Number
     * @param  int $limit default = 10
     * @param  array $where expected array("FieldName" => "Value")
     * @return mixed
     */
    public function paginate($page = 1, $limit = 10, $where=array()) {
        if(!is_numeric($page)):
            return false;
        endif;
        if(!is_numeric($limit)):
            return false;
        endif;
        if(!is_array($where)):
            return false;
        endif;
        $result = array();
        $result["page"] = $page;
        $result["limit"] = $limit;
        $result["totalRecords"] = $this->count($this->PRI, $where);
        $result["totalPages"] = ceil($result["totalRecords"]/$limit);
        if($page > $result["totalPages"]):
            return false;
        endif;
        $fieldsvals = "";
        $columns = !empty($this->variables) ? array_keys($this->variables) : array_keys($where);
        foreach ($columns as $column):
            $fieldsvals.= "`". $column . "` = :" . $column . " AND ";
        endforeach;
        if(!empty($fieldsvals)):
            $fieldsvals = " WHERE ".substr($fieldsvals, 0, -4);
        endif;
        $from = ($page-1)*$limit;
        $sql = "SELECT * FROM " . $this->table . " " . $fieldsvals . " LIMIT " . $from . ",".$limit;

        if(empty($fieldsvals)):
            $result["records"] = $this->db->query($sql);
        else:
            $result["records"] = $this->db->query($sql, !empty($this->variables) ? $this->variables : $where);
        endif;
        return $result;
    }

    /**
     * MIN function is used to find out the record with minimum value among a record set.
     * @param  string $field field to count
     * @param  array $where expected array("FieldName" => "Value")
     * @return mixed
     */
    public function min($field, $where=array()) {
        $fieldsvals = "";
        $columns = !empty($this->variables) ? array_keys($this->variables) : array_keys($where);
        foreach ($columns as $column):
            $fieldsvals.= "`". $column . "` = :" . $column . " AND ";
        endforeach;
        if(!empty($fieldsvals)):
            $fieldsvals = " WHERE ".substr($fieldsvals, 0, -4);
        endif;
        if ($field):
            $sql = "SELECT min(" . $field . ")" . " FROM " . $this->table. " " . $fieldsvals;
            if(empty($fieldsvals)):
                return $this->db->single($sql);
            else:
                return $this->db->single($sql, !empty($this->variables) ? $this->variables : $where);
            endif;
        endif;
    }

    /**
     * MAX function is used to find out the record with maximum value among a record set.
     * @param  string $field field to count
     * @param  array $where expected array("FieldName" => "Value")
     * @return mixed
     */
    public function max($field, $where=array()) {
        $fieldsvals = "";
        $columns = !empty($this->variables) ? array_keys($this->variables) : array_keys($where);
        foreach ($columns as $column):
            $fieldsvals.= "`". $column . "` = :" . $column . " AND ";
        endforeach;
        if(!empty($fieldsvals)):
            $fieldsvals = " WHERE ".substr($fieldsvals, 0, -4);
        endif;
        if ($field):
            $sql = "SELECT max(" . $field . ")" . " FROM " . $this->table. " " . $fieldsvals;
            if(empty($fieldsvals)):
                return $this->db->single($sql);
            else:
                return $this->db->single($sql, !empty($this->variables) ? $this->variables : $where);
            endif;
        endif;
    }

    /**
     * AVG function is used to find out the average of a field in various records.
     * @param  string $field field to count
     * @param  array $where expected array("FieldName" => "Value")
     * @return mixed
     */
    public function avg($field, $where=array()) {
        $fieldsvals = "";
        $columns = !empty($this->variables) ? array_keys($this->variables) : array_keys($where);
        foreach ($columns as $column):
            $fieldsvals.= "`". $column . "` = :" . $column . " AND ";
        endforeach;
        if(!empty($fieldsvals)):
            $fieldsvals = " WHERE ".substr($fieldsvals, 0, -4);
        endif;
        if ($field):
            $sql = "SELECT avg(" . $field . ")" . " FROM " . $this->table. " " . $fieldsvals;
            if(empty($fieldsvals)):
                return $this->db->single($sql);
            else:
                return $this->db->single($sql, !empty($this->variables) ? $this->variables : $where);
            endif;
        endif;
    }

    /**
     * SUM function is used to find out the sum of a field in various records.
     * @param  string $field field to count
     * @param  array $where expected array("FieldName" => "Value")
     * @return mixed
     */
    public function sum($field, $where=array()) {
        $fieldsvals = "";
        $columns = !empty($this->variables) ? array_keys($this->variables) : array_keys($where);
        foreach ($columns as $column):
            $fieldsvals.= "`". $column . "` = :" . $column . " AND ";
        endforeach;
        if(!empty($fieldsvals)):
            $fieldsvals = " WHERE ".substr($fieldsvals, 0, -4);
        endif;
        if ($field):
            $sql = "SELECT sum(" . $field . ")" . " FROM " . $this->table. " " . $fieldsvals;
            if(empty($fieldsvals)):
                return $this->db->single($sql);
            else:
                return $this->db->single($sql, !empty($this->variables) ? $this->variables : $where);
            endif;
        endif;
    }

    /**
     * COUNT function is the simplest function and very useful in counting the number of records,
     * which are expected to be returned by a SELECT statement.
     * @param  string $field field to count
     * @param  array $where expected array("FieldName" => "Value")
     * @return mixed
     */
    public function count($field, $where=array()) {
        $fieldsvals = "";
        $columns = !empty($this->variables) ? array_keys($this->variables) : array_keys($where);
        foreach ($columns as $column):
            $fieldsvals.= "`". $column . "` = :" . $column . " AND ";
        endforeach;
        if(!empty($fieldsvals)):
            $fieldsvals = " WHERE ".substr($fieldsvals, 0, -4);
        endif;
        if ($field):
            $sql = "SELECT count(" . $field . ")" . " FROM " . $this->table. " " . $fieldsvals;
            if(empty($fieldsvals)):
                return $this->db->single($sql);
            else:
                return $this->db->single($sql, !empty($this->variables) ? $this->variables : $where);
            endif;
        endif;
    }
}

return new ' . $this->table . '();
';

		return $output;

	}

	/**
	 * Build Documentation
	 * checks if application is not running in demo mode, returns documentation
	 * @param  boolean $demo
	 * @return string
	 */
	public function buildHowToUse($demo = false) {
		$output = '
         <div class="col-md-3">
          <ul class="nav nav-pills nav-stacked">
            <li class="active"><a href="#tab1" role="tab" data-toggle="tab">Start</a></li>
            <li><a href="#tab2" role="tab" data-toggle="tab">Adding new Record</a></li>
            <li><a href="#tab3" role="tab" data-toggle="tab">Update Record</a></li>
            <li><a href="#tab4" role="tab" data-toggle="tab">Delete Record</a></li>
            <li><a href="#tab5" role="tab" data-toggle="tab">Select All Records</a></li>
            <li><a href="#tab6" role="tab" data-toggle="tab">Find Record</a></li>
            <li><a href="#tab7" role="tab" data-toggle="tab">Paginate</a></li>
            <li><a href="#tab8" role="tab" data-toggle="tab">Reset</a></li>
            <li><a href="#tab9" role="tab" data-toggle="tab">Set Limit</a></li>
            <li><a href="#tab10" role="tab" data-toggle="tab">Select minimum value</a></li>
            <li><a href="#tab11" role="tab" data-toggle="tab">Select maximum value</a></li>
            <li><a href="#tab12" role="tab" data-toggle="tab">Select average value</a></li>
            <li><a href="#tab13" role="tab" data-toggle="tab">Select sum of values</a></li>
            <li><a href="#tab14" role="tab" data-toggle="tab">Select count of rows</a></li>
            <li><a href="#tab15" role="tab" data-toggle="tab">Get row with ' . $this->primaryKey . '</a></li>

            ';
		$c = 16;
		foreach ($this->columns as $column):
			$output .= '<li><a href="#tab' . $c . '" role="tab" data-toggle="tab">Get Row with ' . $column . '</a></li>';
			$c++;
		endforeach;

		// Add relationship tabs
		foreach ($this->relationships as $relation):
			$relationType = $relation['type'] === 'belongsTo' ? 'BelongsTo' : 'HasMany';
			$output .= '<li><a href="#tab' . $c . '" role="tab" data-toggle="tab">' . $relationType . ': ' . $relation['methodName'] . '</a></li>';
			$c++;
		endforeach;

		$output .= '
          </ul>
        </div>
        <div class="col-md-9">
            <button onclick="window.print()" class=" pull-right btn btn-primary print_button">Print this page</button>
            <hr class="clearfix" />
            ';
		if (!$demo) {
			$output .= '
          <div class="tab-content printReady">
            <div class="tab-pane active" id="tab1">
                To start new ' . $this->table . ' class first you will need to include the class:<br />
                <pre class="prettyprint">

 $' . $this->table . ' = include ("' . $this->directoryForGeneratedClasses . '".DIRECTORY_SEPERATOR."' . $this->table . '.php");
                </pre>
                Now <code> $' . $this->table . ' </code> object  is ready and usable.
            </div>
            <div class="tab-pane " id="tab2">
                First of all set field values:
                <pre class="prettyprint">

';
			foreach ($this->columns as $column):
				$output .= '$' . $this->table . '->' . $column . ' = "Some value here";
		';
			endforeach;
			$output .= '

</pre>
            After Values are set, you can execute <code> $' . $this->table . '->add(); </code> method:<br />
             <pre class="prettyprint">

$addResult = $' . $this->table . '->add();
             </pre>
             After you can execute <code> $' . $this->table . '->reset(); </code> method.<br />
            <pre class="prettyprint">

$' . $this->table . '->reset();
            </pre>
             Read more about <code> $' . $this->table . '->reset(); </code> <a href="#tab8" role="tab" data-toggle="tab">here</a>.<br />
             <Br /><br />Whole code will look like this:
             <pre class="prettyprint">

';
			foreach ($this->columns as $column):
				$output .= '$' . $this->table . '->' . $column . ' = "Some value here";
		';
			endforeach;
			$output .= '
if($' . $this->table . '->add()){
    echo "Record was added sucessfully";
}else{
    echo "Oops, Something went wrong, record was not added";
}
$' . $this->table . '->reset();
             </pre>
            </div>
            <div class="tab-pane " id="tab3">
             Update is similar to <code>$' . $this->table . '->add();</code> method, only you have to pass Record id you want to update.<br />
             Set the filed values to update:<br />
             <pre class="prettyprint">

';
			foreach ($this->columns as $column):
				$output .= '$' . $this->table . '->' . $column . ' = "Some other value here";
		';
			endforeach;
			$output .= '

</pre>
            <br />And execute <code>$' . $this->table . '->update( $record_id = ## );</code> method:
            <pre class="prettyprint">

$' . $this->table . '->update( 10 ); // where 10 is Record id you would like to update
            </pre>
            <br />After you can execute <code> $' . $this->table . '->reset(); </code> method.<br />
            <pre class="prettyprint">

$' . $this->table . '->reset();
            </pre>
             Read more about <code> $' . $this->table . '->reset(); </code> <a href="#tab8" role="tab" data-toggle="tab">here</a>.<br />

            <Br /><br />Whole code will look like this:
            <pre class="prettyprint">

';
			foreach ($this->columns as $column):
				$output .= '$' . $this->table . '->' . $column . ' = "Some other value here";
		';
			endforeach;
			$output .= '
if($' . $this->table . '->update( 10 )){
    echo "Record has been updated";
}else{
    echo "Oops, there was error updating the record";
}
$' . $this->table . '->reset();


</pre>
          </div>
          <div class="tab-pane " id="tab4">
           <code>$' . $this->table . '->delete( $recordid = ## );</code> method expects record id as a paramaeter, example usage:
           <pre class="prettyprint">

$' . $this->table . '->delete( 10 ); // where 10 is Record id you would like to delete
            </pre>
          </div>

          <div class="tab-pane " id="tab5">
            <code>$' . $this->table . '->all();</code> method will select and return all records from database table.<Br />
            <br /><div class="alert alert-danger" role="alert">Be careful with this method if your table is big.</div>
            <pre class="prettyprint">

$yourVariable = $' . $this->table . '->all();
            </pre>
          </div>
          <div class="tab-pane " id="tab6">
          <code>$' . $this->table . '->find($field, $value);</code> will perform a search on your database table.<Br />
          There are two ways to use this method, you can either set field objects or pass parameters <br />
          first way:
<pre class="prettyprint">
';
			$rand_keys = array_rand($this->columns, 1);
			$output .= '
$' . $this->table . '->' . $this->columns[$rand_keys] . ' = "Find_Me";
$' . $this->table . '->limit(10);
$result = $' . $this->table . '->find();
$' . $this->table . '->reset();

            </pre>
            This method will search "Find_Me" string in ' . $this->columns[$rand_keys] . ' field.<br />
            Other way to use <code>$' . $this->table . '->find($field, $value);</code> method would be with parameters:
            <pre class="prettyprint">

$result = $' . $this->table . '->find($field = "' . $this->columns[$rand_keys] . '", $value = "Find_Me", $limit = "10");
            </pre><Br />
            <em>Note: Method is using `LIKE` search, which migth be slow on large tables.</em>
          </div>

        <div class="tab-pane " id="tab7">
            <code>$' . $this->table . '->paginate();</code> is easiest way to display your data as pages, <br />
            just pass page number, limit per page and where condition (if needed).<br />
            <pre class="prettyprint">

$data = $' . $this->table . '->paginate($page = 1, $limit = 10);
            </pre>
            <br />or:<br />
            <pre class="prettyprint">

$where = array("' . $this->columns[$rand_keys] . '" => "Your_Value");
$data = $' . $this->table . '->paginate($page = 1, $limit = 10, $where);
            </pre>
            <br />
            Returned result will display requested Page, Requeted Limit, Total Records, Total Pages and actual Data<br />
            Example output:<Br />
            <pre class="prettyprint">

array(5) {
  ["page"]=> int(1)
  ["limit"]=> int(10)
  ["totalRecords"]=> int(97)
  ["totalPages"]=> float(10)
  ["records"]=> array(10) {
    [0]=> array(5) {
      ["ID"]=> int(1)
      ["Name"]=> string(6) "Nick G"
      ...
      ...
    }
    [1]=> array(5) {
      ["ID"]=> int(2)
      ["Name"]=> string(6) "Greg B"
      ...
      ...
    }
  }
}
           </pre>
        </div>

        <div class="tab-pane " id="tab8">
        You will need to <code>$' . $this->table . '->reset();</code> everytime you set new objects and execute query,<br />
        otherwise set objects will carry to next query execution.

        </div>
        <div class="tab-pane " id="tab9">
            <code>$' . $this->table . '->limit( $limit=10 );</code> will set Limit object to be used in other methods.
             <pre class="prettyprint">

$' . $this->table . '->limit( 5 );


</pre>
    <br />
    Example, <a href="#tab6" role="tab" data-toggle="tab"><code>$' . $this->table . '->find()</code></a> method can be used with limit object:
    <pre class="prettyprint">

$' . $this->table . '->limit(10);
$result = $' . $this->table . '->find($field = "email", $value = "Find_Me");

    </pre>
        </div>
        <div class="tab-pane " id="tab10">
            <code>$' . $this->table . '->min($field, $where=array())</code> method is used to find out the record with minimum value among a record set.<br />

            To select with WHERE statement:<Br />
            <pre class="prettyprint">

$where = array("Field" => "Value"); // .. WHERE Field = "Value" ..
$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->min($field, $where);
            </pre>
            <br />
            To select across all records:
            <pre class="prettyprint">

$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->min($field);
            </pre>
        </div>
        <div class="tab-pane " id="tab11">
            <code>$' . $this->table . '->max($field, $where=array())</code> method is used to find out the record with maximum value among a record set.<br />

            To select with WHERE statement:<Br />
            <pre class="prettyprint">

$where = array("Field" => "Value"); // .. WHERE Field = "Value" ..
$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->max($field, $where);
            </pre>
            <br />
            To select across all records:
            <pre class="prettyprint">

$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->max($field);
            </pre>
        </div>
        <div class="tab-pane " id="tab12">
            <code>$' . $this->table . '->avg($field, $where=array())</code> method is used to find out the average of a field in various records.<br />

            To select with WHERE statement:<Br />
            <pre class="prettyprint">

$where = array("Field" => "Value"); // .. WHERE Field = "Value" ..
$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->avg($field, $where);
            </pre>
            <br />
            To select across all records:
            <pre class="prettyprint">

$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->avg($field);
            </pre>
        </div>
        <div class="tab-pane " id="tab13">
            <code>$' . $this->table . '->sum($field, $where=array())</code> method is used to find out the sum of a field in various records.<br />

            To select with WHERE statement:<Br />
            <pre class="prettyprint">

$where = array("Field" => "Value"); // .. WHERE Field = "Value" ..
$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->sum($field, $where);
            </pre>
            <br />
            To select across all records:
            <pre class="prettyprint">

$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->sum($field);
            </pre>
        </div>
        <div class="tab-pane " id="tab14">
            <code>$' . $this->table . '->count($field, $where=array())</code> method is the simplest function and very useful in counting the number of records<br />

            To count with WHERE statement:<Br />
            <pre class="prettyprint">

$where = array("Field" => "Value"); // .. WHERE Field = "Value" ..
$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->count($field, $where);
            </pre>
            <br />
            To count all records:
            <pre class="prettyprint">

$field = "Field_Name"; // Field You Would Like to Select
$result = $' . $this->table . '->count($field);
            </pre>
        </div>
        <div class="tab-pane " id="tab15">
                Records can be selected by passing "' . $this->primaryKey . '" field value to method <code>$' . $this->table . '->get_' . $this->primaryKey . '()</code>.<Br />
                Example:<Br />
                <pre class="prettyprint">

$' . $this->table . '->' . $this->primaryKey . ' = "1";
$result = $' . $this->table . '->get_' . $this->primaryKey . '();
                </pre>
                <br />
                Or even shorter:
                <pre class="prettyprint">

$result = $' . $this->table . '->get_' . $column . '($' . $column . '_value = "something", $limit = 4);
                </pre>
        </div>';
			$c = 16;
			foreach ($this->columns as $column):
				$output .= '<div class="tab-pane " id="tab' . $c . '">
		                Records can be selected by passing "' . $column . '" field value to method <code>$' . $this->table . '->get_' . $column . '()</code>.<Br />
		                Example:<Br />
		                <pre class="prettyprint">

		$' . $this->table . '->' . $column . ' = "something";
		$' . $this->table . '->limit(4);
		$result = $' . $this->table . '->get_' . $column . '();
		                </pre>
		                <br />
		                Or even shorter:
		                <pre class="prettyprint">

		$result = $' . $this->table . '->get_' . $column . '($' . $column . '_value = "something", $limit = 4);
		                </pre>
		        </div>';
				$c++;
			endforeach;

			// Add relationship documentation
			foreach ($this->relationships as $relation):
				if ($relation['type'] === 'belongsTo'):
					$output .= '<div class="tab-pane " id="tab' . $c . '">
		                <strong>BelongsTo Relationship:</strong> <code>$' . $this->table . '->' . $relation['methodName'] . '()</code><Br />
		                This method retrieves the related <strong>' . $relation['relatedTable'] . '</strong> record that this ' . $this->table . ' belongs to.<Br />
		                <br />
		                The relationship is based on the foreign key <code>' . $relation['foreignKey'] . '</code> in this table
		                referencing <code>' . $relation['relatedKey'] . '</code> in the <strong>' . $relation['relatedTable'] . '</strong> table.<br />
		                <br />
		                Example usage:<Br />
		                <pre class="prettyprint">

		// First, get a ' . $this->table . ' record
		$' . $this->table . 'Record = $' . $this->table . '->get_' . $this->primaryKey . '(1);
		if ($' . $this->table . 'Record) {
		    // Load the record into the object
		    foreach($' . $this->table . 'Record[0] as $key => $value) {
		        $' . $this->table . '->{$key} = $value;
		    }

		    // Now get the related ' . $relation['relatedTable'] . ' record
		    $related = $' . $this->table . '->' . $relation['methodName'] . '();
		    if ($related) {
		        echo "Related ' . $relation['relatedTable'] . ': ";
		        print_r($related);
		    }
		}
		                </pre>
		        </div>';
				elseif ($relation['type'] === 'hasMany'):
					$output .= '<div class="tab-pane " id="tab' . $c . '">
		                <strong>HasMany Relationship:</strong> <code>$' . $this->table . '->' . $relation['methodName'] . '($limit = "")</code><Br />
		                This method retrieves all <strong>' . $relation['relatedTable'] . '</strong> records that belong to this ' . $this->table . '.<Br />
		                <br />
		                The relationship is based on the foreign key <code>' . $relation['foreignKey'] . '</code> in the <strong>' . $relation['relatedTable'] . '</strong> table
		                referencing <code>' . $relation['relatedKey'] . '</code> in this table.<br />
		                <br />
		                Example usage:<Br />
		                <pre class="prettyprint">

		// First, get a ' . $this->table . ' record
		$' . $this->table . 'Record = $' . $this->table . '->get_' . $this->primaryKey . '(1);
		if ($' . $this->table . 'Record) {
		    // Load the record into the object
		    foreach($' . $this->table . 'Record[0] as $key => $value) {
		        $' . $this->table . '->{$key} = $value;
		    }

		    // Now get all related ' . $relation['relatedTable'] . ' records
		    $related = $' . $this->table . '->' . $relation['methodName'] . '(10); // Limit to 10 records
		    if ($related) {
		        echo "Related ' . $relation['relatedTable'] . ': ";
		        foreach($related as $item) {
		            print_r($item);
		        }
		    }
		}
		                </pre>
		        </div>';
				endif;
				$c++;
			endforeach;

		} else {// if demo
			$output .= '
            <div class="alert alert-warning" role="alert">Application is running in demo mode, Documentation is disabled </div>
        ';
		}
		$output .= '</div>
        </div>';
		return $output;
	}

}