<?php
class Database {

	/**
	 * Default Charset
	 */
	const CHARSET = 'UTF8';

	/**
	 * holds db connection
	 * @var resource
	 */
	private $pdo;

	/**
	 * Holds sql query
	 * @var object
	 */
	private $_query;

	/**
	 * Holds DB Settings
	 * @var array
	 */
	private $settings;

	/**
	 * Determines DB connection
	 * @var boolean
	 */
	private $dbConnected = false;

	/**
	 * Database parameters
	 * @var array
	 */
	private $parameters;

	/**
	 * Connect to database and set parameters array
	 * @access public
	 * @return object
	 */
	public function __construct() {
		$this->Connect();
		$this->parameters = array();
	}

	/**
	 * Makes Database connection
	 * - loads database configuration file
	 * - Tries to connect to database
	 * - In case of failure exception is displayed
	 * @access public
	 * @return  void
	 */
	private function Connect() {
		$this->settings = include (realpath(dirname(__FILE__) . "/../dbconfig.php"));
		$dsn            = $this->settings['dbtype'] . ':dbname=' . $this->settings["dbname"] . ';host=' . $this->settings["host"] . '';
		try {
			$attrs     = !isset($this->settings['charset']) ? array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::CHARSET) : array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $this->settings['charset']);
			$this->pdo = new PDO($dsn, $this->settings["username"], $this->settings["password"], $attrs);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$this->dbConnected = true;
		}
		 catch (PDOException $e) {
			echo $this->CustomException($e->getMessage());
			exit();
		}
	}

	/**
	 * Close Database Connection
	 * @access public
	 * @return  void
	 */
	public function CloseConnection() {

		// http://www.php.net/manual/en/pdo.connections.php
		$this->pdo = null;
	}

	/**
	 * Checks the database connection and connects if connection is not set.
	 * Prepares and parameterize Query
	 * Execute Query
	 * On Exception thows error
	 * Resets the parameters
	 * @param string $query      Query to prepare
	 * @param array $parameters Query Parameters
	 */
	private function Init($query, $parameters = "") {
		if (!$this->dbConnected):
			$this->Connect();
		endif;
		try {
			$this->_query = $this->pdo->prepare($query);
			$this->bindMore($parameters);
			if (!empty($this->parameters)):
				foreach ($this->parameters as $param):
					$parameters = explode("\x7F", $param);
					$this->_query->bindParam($parameters[0], $parameters[1]);
				endforeach;
			endif;
			$this->succes = $this->_query->execute();
		}
		 catch (PDOException $e) {
			echo $this->CustomException($e->getMessage(), $query);
			exit();
		}
		$this->parameters = array();
	}

	/**
	 * Add Parameter to Parameters Array
	 * @param  string $para
	 * @param  string $value
	 * @return void
	 */
	public function bind($para, $value) {
		$this->parameters[sizeof($this->parameters)] = ":" . $para . "\x7F" . $value;
	}

	/**
	 * Adds more parameters to Parameters array
	 * @param  array $parameters_array
	 * @return void
	 */
	public function bindMore($parameters_array) {
		if (empty($this->parameters) and is_array($parameters_array)):
			$columns = array_keys($parameters_array);
			foreach ($columns as $i => &$column):
				$this->bind($column, $parameters_array[$column]);
			endforeach;
		endif;
	}

	/**
	 * if SQL query contains SELECT, DESCRIBE, PRAGMA, OR SHOW returns array containing the result set
	 * if SQL query contains DELETE, INSERT, UPDATE method returns number of effected rows
	 * @param  string $query
	 * @param  array $params
	 * @param  object $fetchmode
	 * @return mixed
	 */
	public function query($query, $params = null, $fetchmode = PDO::FETCH_ASSOC) {
		$query = trim($query);
		$this->Init($query, $params);
		$rawStatement = explode(" ", $query);
		$statement    = strtoupper($rawStatement[0]);
		if (in_array($statement, array("SELECT", "DESCRIBE", "PRAGMA", "SHOW"))):
			return $this->_query->fetchAll($fetchmode);
		elseif (in_array($statement, array("DELETE", "INSERT", "UPDATE"))):
			return $this->_query->rowCount();
		else:
			return NULL;
		endif;
	}

	/**
	 *  Returns the last inserted id.
	 *  @return string
	 */
	public function lastInsertId() {
		return $this->pdo->lastInsertId();
	}

	/**
	 *   Returns an array which represents a column from the result set
	 *   @param  string $query
	 *   @param  array  $params
	 *   @return array
	 */
	public function column($query, $params = null) {
		$this->Init($query, $params);
		$Columns = $this->_query->fetchAll(PDO::FETCH_NUM);
		$column  = null;
		foreach ($Columns as $cells):
			$column[] = $cells[0];
		endforeach;
		return $column;
	}

	/**
	 *   Returns the value of one single field/column
	 *   @param  string $query
	 *   @param  array  $params
	 *   @return string
	 */
	public function single($query, $params = null) {
		$this->Init($query, $params);
		return $this->_query->fetchColumn();
	}

	/**
	 * Returns the Exception Error
	 * @param  string $message
	 * @param  string $sql
	 * @return string
	 */
	private function CustomException($message, $sql = "") {
		if (php_sapi_name() === 'cli'):
			$exception = $message . "\n";
		else:
			if (!empty($sql)):
				$message .= "<p>Raw SQL : " . $sql . "</p>";
			endif;
			$exception = '<div class="error error-danger">';
			$exception .= '   <h4>Ooops, There is an Error:</h4>';
			$exception .= '   <p>' . $message . '</p>';
			$exception .= '</div>';
		endif;
		return $exception;
	}
}
