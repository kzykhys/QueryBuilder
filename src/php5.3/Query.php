<?php
/**
 * Query Builder for MySQL
 * 
 * <code>
 * $query = Query::select()
 *  ->from('table_name')
 *   ->columns(array('*', 'max(member)')
 *    ->where('user_id = ?', $user_id)
 *     ->groupBy('id')
 *      ->orderBy('date desc');
 * if(condition) {
 *   $query->andWhere('category_id = ?', $category_id);
 * }
 * $data = $query->fetchAll();
 * </code>
 * 
 * @author valnur
 * @version 1.0.1
 * @license LGPLv3
 * @copyright (C) 2001 valnur. All rights reserved.
 * @filesource
 */
namespace QueryBuilder {
  
  use \PDO;
  
  class Query {
  	
  	const
  		TYPE_SELECT = 0,
  		TYPE_INSERT = 1,
  		TYPE_UPDATE = 2,
  		TYPE_DELETE = 3
  	;
  
  	private static
  		$pdo                 = false,
  		$transaction_began   = false,
  		$instance_stack      = array(),
  		$sql_history         = array()
  	;
  
  	private
  		$query_type         = self::TYPE_SELECT,
  		$is_explain         = false,
  		$is_calc_found_rows = false,
  		$is_count           = false,
  		$is_rollup          = false,
  		$sql_from           = false,
  		$sql_where          = array(),
  		$sql_columns        = array('*'),
  		$sql_join           = array(),
  		$sql_group_by       = false,
  		$sql_having         = false,
  		$sql_order_by       = false,
  		$sql_limit          = false,
  		$bind_where         = array(),
  		$bind_values        = array(),
  		$bind_order_by      = array(),
  		//$helper_token       = array(),
  		$helper_bound_value = array(),
  		$helper_sql         = false,
  		$errors             = array(),
  		$query_string       = null
  	;
  
  	/**
  	 * Represent a connection to a database
  	 * 
  	 * @param string $host
  	 * @param string $user
  	 * @param string $password
  	 * @param string $schema
  	 * @param string $set_names_utf8
  	 * @return boolean TRUE on success or FALSE on failure
  	 */
  	public static function connect($host, $user, $password, $schema, $set_names_utf8 = false) {
  		try {
  			self::$pdo = new \PDO(sprintf('mysql:host=%s;dbname=%s', $host, $schema), $user, $password);
  			if($set_names_utf8) self::$pdo->query('SET NAMES utf8');
  			return true;
  		} catch(Exception $e) {
  			return false;
  		}
  	}
  	
  	/**
  	 * Initiates a transaction
  	 * 
  	 * @return boolean TRUE on success or FALSE on failure
  	 */
  	public static function begin() {
  		if($result = self::$pdo->beginTransaction()) {
  			self::$transaction_began = true;
  		}
  		return $result;
  	}
  	
  	/**
  	 * Rolls back a transaction
  	 * 
  	 * @return boolean TRUE on success or FALSE on failure
  	 */
  	public static function rollback() {
  		return self::$pdo->rollBack();
  	}
  	
  	/**
  	 * Commits a transaction
  	 * 
  	 * @return boolean TRUE on success or FALSE on failure
  	 */
  	public static function commit() {
  		return self::$pdo->commit();
  	}
  	
  	/**
  	 * Returns the ID of the last inserted row or sequence value
  	 * 
  	 * @return string
  	 */
  	public static function lastInsertId() {
  		return self::$pdo->lastInsertId();
  	}
  
  	/**
  	 * Creates a Query instance: SELECT statement
  	 * 
  	 * @return Query
  	 */
  	public static function select() {
  		return self::instance(self::TYPE_SELECT);
  	}
  	
  	/**
  	 * for backward compability : alias of select
  	 * @deprecated method will be removed
  	 * @return Query
  	 */
  	public static function create() {
  		return self::select();
  	}
  	
  	/**
  	 * Creates a Query instance: INSERT statement
  	 * 
  	 * @return Query
  	 */
  	public static function insert() {
  		return self::instance(self::TYPE_INSERT);
  	}
  	
  	/**
  	 * Creates a Query instance: UPDATE statement
  	 * 
  	 * @return Query
  	 */
  	public static function update() {
  		return self::instance(self::TYPE_UPDATE);
  	}
  	
  	/**
  	 * Creates a Query instance: DELETE statement
  	 * @return Query
  	 */
  	public static function delete() {
  		return self::instance(self::TYPE_DELETE);
  	}
  
  	/**
  	 * Returns a instance last created
  	 * 
  	 * @return Query
  	 */
  	public static function lastInstance() {
  		$index = count(self::$instance_stack);
  		if($index) {
  			return self::$instance_stack[($index - 1)];
  		}
  		return false;
  	}
  	
  	/**
  	 * Destroys all created instance
  	 * 
  	 * @return void
  	 */
  	public static function destroyAllInstance() {
  		self::$instance_stack = array();
  	}
  
  	/**
  	 * Executes an SQL statement, returning a result set as a PDOStatement object
  	 * 
  	 * @param string $sql SQL statement
  	 * @param array $assignment [optional] Bound parameters in the SQL statement
  	 * @return Query
  	 */
  	public static function sql($sql, $assignment = array()) {
  		if(!self::$pdo) return false;
  		self::$sql_history[] = array('sql' => $sql, 'assignment' => $assignment);
  		$statement = self::$pdo->prepare($sql);
  		if($statement->execute($assignment)) {
  			return $statement;
  		} else {
  			return false;
  		}
  	}
  
  	public static function getSQLHistory() {
  		return self::$sql_history;
  	}
  
  	/**
  	 * Returns number of rows the statement would have returned without the LIMIT
  	 *
  	 * @return integer
  	 */
  	public static function getFoundRows() {
  		$statement = self::sql('select found_rows()');
  		if($statement) {
  			return (int) $statement->fetchColumn();
  		}
  	}
  
  	/*
  	 * Helper Method: select(), insert(), update(), delete()
  	 */
  	private static function instance($query_type) {
  		if(self::$pdo) {
  			$instance = new self($query_type);
  			self::$instance_stack[] = $instance;
  			return $instance;
  		}
  		throw new Exception('Call '.__CLASS__.'::connect() first before use');
  	}
  
  	/**
  	 * Constructor
  	 * 
  	 * @param int $query_type 
  	 * @return 
  	 */
  	private function __construct($query_type) {
  		$this->query_type = $query_type;
  	}
  
  	/**
  	 * Sets CALS_FOUND_ROWS
  	 * 
  	 * @param boolean $calc
  	 * @return Query
  	 */
  	public function calcFoundRows($calc = true) {
  		$this->is_calc_found_rows = (boolean) $calc;
  		return $this;
  	}
  	
  	/**
  	 * Sets EXPLAIN Syntax
  	 * 
  	 * @param boolean $is_explain [optional] TRUE to use explain or FALSE to not
  	 * @return Query
  	 */
  	public function explain($is_explain = true) {
  		$this->is_explain = (boolean) $is_explain;
  		return $this;
  	}
  	
  	/**
  	 * Sets FROM Syntax
  	 * 
  	 * @param object $table
  	 * @return Query
  	 */
  	public function from($table) {
  		$this->sql_from = $table;
  		return $this;
  	}
  	
  	/**
  	 * Sets INTO Syntax (Alias of Query::from)
  	 * 
  	 * @param object $table
  	 * @return Query
  	 */
  	public function into($table) {
  		return $this->from($table);
  	}
  	
  	/**
  	 * Sets name of table (Alias of Query::from)
  	 * 
  	 * @param object $table
  	 * @return Query
  	 */
  	public function table($table) {
  		return $this->from($table);
  	}
  	
  	/**
  	 * Sets columns
  	 * 
  	 * @param mixed $columns an Array of columns or string
  	 * @param mixed $...
  	 * @return Query
  	 */
  	public function columns($columns) {
  		if(func_num_args() == 1) {
  			$this->sql_columns = (is_array($columns)) ? $columns : array($columns);
  		} else {
  			$this->sql_columns = func_get_args();
  		}
  		return $this;
  	}
  	
  	/**
  	 * Sets values
  	 * 
  	 * @param array $values
  	 * @return Query
  	 */
  	public function values(array $values) {
  		$this->bind_values = array_merge($this->bind_values, $values);
  		return $this;
  	}
  	
  	/**
  	 * Sets WHERE Syntax
  	 * 
  	 * @param mixed $where an Array of values or string
  	 * @param mixed $...
  	 * @return 
  	 */
  	public function where($where) {
  		$num_args = func_num_args();
  		$args = func_get_args();
  		return $this->setupWhere($num_args, $args, true);
  	}
  	
  	/**
  	 * Adds WHERE Syntax: AND
  	 * 
  	 * @param mixed $where an Array of values or string
  	 * @param mixed $...
  	 * @return 
  	 */
  	public function andWhere($where) {
  		$num_args = func_num_args();
  		$args = func_get_args();
  		return $this->setupWhere($num_args, $args, false, 'and');
  	}
  	
  	/**
  	 * Adds WHERE Syntax: OR
  	 * 
  	 * @param mixed $where an Array of values or string
  	 * @param mixed $...
  	 * @return 
  	 */
  	public function orWhere($where) {
  		$num_args = func_num_args();
  		$args = func_get_args();
  		return $this->setupWhere($num_args, $args, false, 'or');
  	}
  	
  	/**
  	 * Sets JOIN Syntax
  	 * 
  	 * @param string $join JOIN statement
  	 * @return Query
  	 */
  	public function join($join) {
  		$this->sql_join[] = sprintf('join %s', $join);
  		return $this;
  	}
  	
  	/**
  	 * Sets LEFT JOIN Syntax
  	 * 
  	 * @param string $join JOIN statement
  	 * @return Query
  	 */
  	public function leftJoin($join) {
  		$this->sql_join[] = sprintf('left join %s', $join);
  		return $this;
  	}
  	
  	/**
  	 * Sets RIGHT JOIN Syntax
  	 * 
  	 * @param string $join JOIN statement
  	 * @return Query
  	 */
  	public function rightJoin($join) {
  		$this->sql_join[] = sprintf('right join %s', $join);
  		return $this;
  	}
  	
  	/**
  	 * Sets INNER JOIN Syntax
  	 * 
  	 * @param string $join JOIN statement
  	 * @return Query
  	 */
  	public function innerJoin($join) {
  		$this->sql_join[] = sprintf('inner join %s', $join);
  		return $this;
  	}
  	
  	/**
  	 * Sets GROUP Syntax
  	 * 
  	 * @param string $groupBy GROUP statement
  	 * @param boolean $with_rollup TRUE to add WITH ROLLUP modifier
  	 * @return 
  	 */
  	public function groupBy($groupBy, $with_rollup = false) {
  		$this->sql_group_by = $groupBy;
  		$this->is_rollup = $with_rollup;
  		return $this;
  	}
  	
  	/**
  	 * Sets HAVING Syntax
  	 * 
  	 * @param string $having
  	 * @return 
  	 */
  	public function having($having) {
  		$this->sql_having = $having;
  		return $this;
  	}
  	
  	/**
  	 * Sets ORDER Syntax
  	 * 
  	 * @param string $orderBy
  	 * @return 
  	 */
  	public function orderBy($orderBy) {
  		$this->sql_order_by = $orderBy;
  		return $this;
  	}
  	
  	/**
  	 * Sets LIMIT Syntax
  	 * 
  	 * @param int $offset [optional]
  	 * @param int $rowCount
  	 * @return 
  	 */
  	public function limit($offset = 0, $rowCount = 0) {
  		if(func_num_args() == 2) {
  			$this->sql_limit = $offset . ',' . $rowCount;
  		} else {
  			$this->sql_limit = $offset;
  		}
  		return $this;
  	}
  	
  	/**
  	 * Sets LIMIT Syntax: Pager Implementation
  	 * 
  	 * @param int $page
  	 * @param int $limit
  	 * @return 
  	 */
  	public function page($page, $limit) {
  		$start = $limit * ($page - 1);
  		$this->limit($start, $limit);
  		return $this;
  	}
  	
  	/**
  	 * Execute an SQL statement
  	 * 
  	 * @return PDOStatement
  	 */
  	public function execute() {
  		$this->compileSqlStatement();
  		try {
  			if(!$statement = self::sql($this->helper_sql, $this->helper_bound_value)) {
  				$this->errors[] = self::$pdo->errorInfo();
  			} else {
  				$this->query_string = $statement->queryString;
  			}
  			return $statement;
  		} catch(Exception $e) {
  			$this->errors[] = $e;
  			return false;
  		}
  	}
  	
  	/**
  	 * Execute an SQL statement and Fetches a row from a result set
  	 * 
  	 * @param int $pdoFetchOption [optional]
  	 * @return array
  	 */
  	public function fetch($pdoFetchOption = PDO::FETCH_ASSOC) {
  		if($statement = $this->execute()) {
  			$result = $statement->fetch($pdoFetchOption);
  			return $result;
  		}
  		return false;		
  	}
  	
  	/**
  	 * Execute an SQL statement and Returns an array containing all of the result set rows 
  	 * 
  	 * @param int $pdoFetchOption [optional]
  	 * @return array
  	 */
  	public function fetchAll($pdoFetchOption = PDO::FETCH_ASSOC) {
  		if($statement = $this->execute()) {
  			$result = $statement->fetchAll($pdoFetchOption);
  			return $result;
  		}
  		return false;
  	}
  	
  	/**
  	 * Returns a single column from the next row of a result set
  	 * 
  	 * @param int $index [optional]
  	 * @return string|mixed
  	 */
  	public function fetchColumn($index = 0) {
  		if($statement = $this->execute()) {
  			$result = $statement->fetchColumn($index);
  			return $result;
  		}
  		return false;
  	}
  	
  	/**
  	 * Return a count of the number of rows returned
  	 * 
  	 * @return int|false
  	 */
  	public function count() {
  		$this->is_count = true;
  		if($statement = $this->execute()) {
  			$count = $statement->fetchColumn();
  			return (int) $count;
  		}
  		return false;		
  	}
  	
  	/**
  	 * Returns used query string
  	 * 
  	 * @return string
  	 */
  	public function getQueryString() {
  		return $this->query_string;
  	}
  
  	/**
  	 * Returns errors
  	 *
  	 * @return array
  	 */
  	public function getErrors() {
  		return $this->errors;
  	}
  
  	/*
  	 * Helper Method: setupWhere()
  	 */
  	private function resetWhere() {
  		$this->sql_where = array();
  		$this->bind_where = array();
  	}
  	
  	/*
  	 * Helper Method: where(), andWhere(), orWhere()
  	 */
  	private function setupWhere($num_args, $args, $reset = false, $prefix = '') {
  		if($reset) $this->resetWhere();
  		if($prefix) $prefix = $prefix . ' ';
  		if($num_args == 1) {
  			if($reset and is_array($args[0])) {
  				$this->sql_where = $args[0];
  			} else {
  				$this->sql_where[] = $prefix . $args[0];
  			}
  		} elseif($num_args == 2 && is_array($args[1])) {
  			$this->sql_where[] = $prefix . $args[0];
  			$this->bind_where = array_merge($this->bind_where, $args[1]);
  		} else {
  			$this->sql_where[] = $prefix . array_shift($args);
  			$this->bind_where = array_merge($this->bind_where, $args);
  		}
  		return $this;
  	}
  	
  	/*
  	 * 
  	 */
  	private function compileSqlStatement() {
  		switch($this->query_type) {
  			case self::TYPE_SELECT:
  				$this->buildSelectQuery(); break;
  			case self::TYPE_INSERT:
  				$this->buildInsertQuery(); break;
  			case self::TYPE_UPDATE:
  				$this->buildUpdateQuery(); break;
  			case self::TYPE_DELETE:
  				$this->buildDeleteQuery(); break;
  			default: return;
  		}
  		//$this->compileToken();
  	}
  	
  	/*
  	 * 
  	 */
  	private function buildSelectQuery() {
  		$this->initializeToken();
  		$this->is_explain && $this->addExplainToken();
  		$this->addToken('select');
  		$this->is_calc_found_rows && $this->addCalcFoundRowsToken();
  		$this->addColumnsToken();
  		$this->addTableToken('from');
  		$this->sql_join && $this->addJoinToken();
  		$this->sql_where && $this->addWhereToken();
  		$this->sql_group_by && $this->addGroupByToken();
  		$this->sql_order_by && $this->addOrderByToken();
  		$this->sql_limit && $this->addLimitToken();
  		$this->helper_bound_value = array_merge(
  			$this->bind_where, $this->bind_order_by
  		);
  	}
  	
  	/*
  	 * 
  	 */
  	private function buildInsertQuery() {
  		$this->initializeToken('insert');
  		$this->addTableToken('into');
  		$this->addColumnsToken(true);
  		$this->addToken('values');
  		$this->addValuesToken();
  		$this->helper_bound_value = $this->bind_values;
  	}
  	
  	/*
  	 * 
  	 */
  	private function buildUpdateQuery() {
  		$this->initializeToken('update');
  		$this->addTableToken();
  		$this->addSetToken();	
  		$this->addWhereToken();
  		$this->helper_bound_value = array_merge(
  			$this->bind_values, $this->bind_where
  		);
  	}
  	
  	/*
  	 * 
  	 */
  	private function buildDeleteQuery() {
  		$this->initializeToken('delete');
  		$this->addTableToken('from');
  		$this->addWhereToken();
  		$this->helper_bound_value = $this->bind_where;
  	}
  	
  	/*
  	 * 
  	 */
  	private function initializeToken($token = null) {
  		//$this->helper_token = array();
  		//$this->helper_token2 = '';
  		$this->helper_sql = '';
  		if(!is_null($token)) {
  			//$this->helper_token[] = $token;
  			//$this->helper_token2 = $token;
  			$this->helper_sql = $token;
  		}
  	}
  	
  	/*
  	 * 
  	 */
  	private function addToken($tokens) {
  		$tokens = func_get_args();
  		foreach($tokens as $token) {
  			//$this->helper_token[] = $token;
  			//$this->helper_token2 .= ' ' . $token;
  			$this->helper_sql .= ' ' . $token;			// fastest
  		}
  	}
  
  	/*
  	 * 
  	 */
  	private function compileToken() {
  		//$this->helper_sql = implode(' ', $this->helper_token);
  		//$this->helper_sql = $this->helper_token2;
  	}
  
  	/*
  	 * SQL Syntax: EXPLAIN
  	 */
  	private function addExplainToken() {
  		if($this->is_explain) $this->addToken('explain');
  	}
  	
  	/*
  	 * SQL Syntax: SQL_CALC_FOUND_ROWS
  	 */
  	private function addCalcFoundRowsToken() {
  		if($this->is_calc_found_rows) $this->addToken('SQL_CALC_FOUND_ROWS');
  	}
  	
  	/*
  	 * SQL Syntax: Columns
  	 */
  	private function addColumnsToken($brase = false) {
  		if($this->is_count) {
  			$this->addToken(sprintf('count(%s)', $this->sql_columns[0]));
  			return;
  		}
  		if($brase) {
  			foreach($this->sql_columns as $_col) $_columns[] = '`' . $_col . '`';
  			$token = implode(',', $_columns);
  			$this->addToken('(', $token, ')');
  		} else {
  			$token = implode(',', $this->sql_columns);
  			$this->addToken($token);
  		}
  	}
  	
  	/*
  	 * SQL Syntax: Values
  	 */
  	private function addValuesToken() {
  		$count = count($this->bind_values);
  		$this->addToken('(', implode(',', array_fill(0, $count, '?')), ')');
  	}
  	
  	/*
  	 * SQL Syntax: SET
  	 */
  	private function addSetToken() {
  		$this->addToken('set');
  		$set = array();
  		foreach($this->sql_columns as $column) {
  			$set[] = '`' . $column . '` = ?';
  		}
  		$this->addToken(implode(', ', $set));
  	}
  	
  	/*
  	 * SQL Syntax: FROM, INTO
  	 */
  	private function addTableToken($prefix = '') {
  		if($this->sql_from) {
  			if($prefix) $this->addToken($prefix);
  			$this->addToken($this->sql_from);
  		}
  	}
  	
  	/*
  	 * SQL Syntax: JOIN, INNER JOIN, LEFT JOIN, RIGHT JOIN
  	 */
  	private function addJoinToken() {
  		if($this->sql_join) $this->addToken(implode(' ', $this->sql_join));
  	}
  
  	/*
  	 * SQL Syntax: WHERE
  	 */
  	private function addWhereToken() {
  		if($this->sql_where and is_array($this->sql_where)) {
  			$this->addToken('where', implode(' ', $this->sql_where));
  		}
  	}
  	
  	/*
  	 * SQL Syntax: GROUP BY [WITH ROLLUP]
  	 */
  	private function addGroupByToken() {
  		if($this->sql_group_by) {
  			$this->addToken('group by', $this->sql_group_by);
  			if($this->is_rollup) {
  				$this->addToken('WITH ROLLUP');
  			}
  		}
  	}
  	
  	/*
  	 * SQL Syntax: HAVING
  	 */
  	private function addHavingToken() {
  		if($this->sql_group_by and $this->sql_having) {
  			$this->addToken('having', $this->sql_having);
  		}
  	}
  	
  	/*
  	 * SQL Syntax: ORDER BY
  	 */
  	private function addOrderByToken() {
  		if($this->sql_order_by) $this->addToken('order by', $this->sql_order_by);
  	}
  	
  	/*
  	 * SQL Syntax: LIMIT
  	 */
  	private function addLimitToken() {
  		if($this->sql_limit) $this->addToken('limit', $this->sql_limit);
  	}
  
  }
}