<?php
declare(strict_types=1);

namespace DatabaseToClass;

class Database {

	/**
	 * Default Charset
	 */
	const CHARSET = 'UTF8';

	/**
	 * holds db connection
	 * @var \PDO|null
	 */
	private ?\PDO $pdo = null;

	/**
	 * Holds sql query
	 * @var \PDOStatement|null
	 */
	private ?\PDOStatement $_query = null;

	/**
	 * Holds DB Settings
	 * @var array
	 */
	private array $settings = [];

	/**
	 * Determines DB connection
	 * @var boolean
	 */
	private bool $dbConnected = false;

	/**
	 * Database parameters
	 * @var array
	 */
	private array $parameters = [];

	/**
	 * Connect to database and set parameters array
	 * @access public
	 */
	public function __construct() {
		$this->Connect();
	}

	/**
	 * Makes Database connection
	 * - loads database configuration file
	 * - Tries to connect to database
	 * - In case of failure exception is displayed
	 * @access public
	 * @return  void
	 */
	private function Connect(): void {
		$configPath = dirname(__DIR__) . '/dbconfig.php';
		if (!file_exists($configPath)) {
			// Fallback for when used as a library
			$configPath = getcwd() . '/dbconfig.php';
		}
		$this->settings = include ($configPath);
		$dsn = $this->settings['dbtype'] . ':dbname=' . $this->settings["dbname"] . ';host=' . $this->settings["host"];
		try {
			$charset = $this->settings['charset'] ?? self::CHARSET;
			$attrs = [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $charset];
			$this->pdo = new \PDO($dsn, $this->settings["username"], $this->settings["password"], $attrs);
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
			$this->dbConnected = true;
		} catch (\PDOException $e) {
			echo $this->CustomException($e->getMessage());
			exit();
		}
	}

	/**
	 * Close Database Connection
	 * @access public
	 * @return  void
	 */
	public function CloseConnection(): void {
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
	 * @param array|null $parameters Query Parameters
	 */
	private function Init(string $query, array|null $parameters = null): void {
		if (!$this->dbConnected) {
			$this->Connect();
		}
		try {
			$this->_query = $this->pdo->prepare($query);
			$this->bindMore($parameters);
			if (!empty($this->parameters)) {
				foreach ($this->parameters as $param) {
					$parameters = explode("\x7F", $param);
					$this->_query->bindParam($parameters[0], $parameters[1]);
				}
			}
			$this->_query->execute();
		} catch (\PDOException $e) {
			echo $this->CustomException($e->getMessage(), $query);
			exit();
		}
		$this->parameters = [];
	}

	/**
	 * Add Parameter to Parameters Array
	 * @param  string $para
	 * @param  mixed $value
	 * @return void
	 */
	public function bind(string $para, mixed $value): void {
		$this->parameters[sizeof($this->parameters)] = ":" . $para . "\x7F" . $value;
	}

	/**
	 * Adds more parameters to Parameters array
	 * @param  array|null $parameters_array
	 * @return void
	 */
	public function bindMore(array|null $parameters_array): void {
		if (empty($this->parameters) && is_array($parameters_array)) {
			$columns = array_keys($parameters_array);
			foreach ($columns as $i => &$column) {
				$this->bind($column, $parameters_array[$column]);
			}
		}
	}

	/**
	 * if SQL query contains SELECT, DESCRIBE, PRAGMA, OR SHOW returns array containing the result set
	 * if SQL query contains DELETE, INSERT, UPDATE method returns number of effected rows
	 * @param  string $query
	 * @param  array|null $params
	 * @param  int $fetchmode
	 * @return mixed
	 */
	public function query(string $query, array|null $params = null, int $fetchmode = \PDO::FETCH_ASSOC): mixed {
		$query = trim($query);
		$this->Init($query, $params);
		$rawStatement = explode(" ", $query);
		$statement = strtoupper($rawStatement[0]);
		if (in_array($statement, ["SELECT", "DESCRIBE", "PRAGMA", "SHOW"])) {
			return $this->_query->fetchAll($fetchmode);
		} elseif (in_array($statement, ["DELETE", "INSERT", "UPDATE"])) {
			return $this->_query->rowCount();
		} else {
			return null;
		}
	}

	/**
	 *  Returns the last inserted id.
	 *  @return string
	 */
	public function lastInsertId(): string {
		return $this->pdo->lastInsertId();
	}

	/**
	 *   Returns an array which represents a column from the result set
	 *   @param  string $query
	 *   @param  array|null  $params
	 *   @return array
	 */
	public function column(string $query, array|null $params = null): array {
		$this->Init($query, $params);
		$Columns = $this->_query->fetchAll(\PDO::FETCH_NUM);
		$column = [];
		foreach ($Columns as $cells) {
			$column[] = $cells[0];
		}
		return $column;
	}

	/**
	 *   Returns the value of one single field/column
	 *   @param  string $query
	 *   @param  array|null  $params
	 *   @return mixed
	 */
	public function single(string $query, array|null $params = null): mixed {
		$this->Init($query, $params);
		return $this->_query->fetchColumn();
	}

	/**
	 * Returns the Exception Error
	 * @param  string $message
	 * @param  string $sql
	 * @return string
	 */
	private function CustomException(string $message, string $sql = ""): string {
		if (php_sapi_name() === 'cli') {
			$exception = $message . "\n";
		} else {
			if (!empty($sql)) {
				$message .= "<p>Raw SQL : " . $sql . "</p>";
			}
			$exception = '<div class="error error-danger">';
			$exception .= '   <h4>Ooops, There is an Error:</h4>';
			$exception .= '   <p>' . $message . '</p>';
			$exception .= '</div>';
		}
		return $exception;
	}
}
