<?

/**
 * tiny implementation of object-relational-mapping in php.
 * @author Simon Legner
 * @version 0.7 2008086
 * @license http://www.gnu.org/licenses/lgpl.html LGPL3
 */
abstract class MyORM {

	/**
	 * @var PDO
	 */
	private static $db;

	/**
	 * opens database connection.
	 * @param string $dsn Data Source Name 
	 * @param string $username user name
	 * @param string $passwd password
	 * @param array $options driver-specific connection options
	 * @link http://php.net/manual/en/pdo.construct.php PDO-constructor.
	 */
	public static function openDb($dsn, $username=null, $passwd=null, $options=null) {
		self::$db = new PDO($dsn, $username, $passwd, $options);
		if(!self::$db) throw new Exception('No db-connection available',0);
	}
	
	/**
	 * @param PDOStatement $sql
	 * @param array $fields
	 * @param array $data
	 */
	private static function bindValues($sql,$fields,$data) {
		foreach ($fields as $field) {
			$sql->bindValue(':'.$field,$data[$field]);
		}
	}

	private $data = array();

	/**
	 * flag of db-state of the object.
	 * @var string
	 */
	private $flag = 'clean'; //clean,update,insert,delete

	/**
	 * get the name of the corresponding db-table
	 * default is class-name, override this method otherwise.
	 * @return string corresponding db-table-name.
	 */
	protected function getTableName() {
		return get_class($this);
	}

	/**
	 * @var array tablename=>columnname=>{name,len,native_type,pdo_type,flags}.
	 */
	private static $fields = array();

	/**
	 * fetches column-information from database and stores them.
	 */
	private function fetchFields() {
		self::$fields[$this->getTableName()] = array();
		$sql = self::$db->prepare('SELECT * FROM '.$this->getTableName().' LIMIT 1');
		$sql->execute();
		for($i=0;$i<$sql->columnCount();$i++) {
			$meta = $sql->getColumnMeta($i);
			self::$fields[$this->getTableName()][$meta['name']] = $meta;
		}
	}

	private function getFields() {
		if(!in_array($this->getTableName(),array_keys(self::$fields))) {
			$this->fetchFields();
		}
		$res = array();
		foreach(self::$fields[$this->getTableName()] as $field) {
			$res[] = $field['name'];
		}
		return $res;
	}

	private function getPrimaryKeyFields() {
		if(!in_array($this->getTableName(),array_keys(self::$fields))) {
			$this->fetchFields();
		}
		$res = array();
		foreach(self::$fields[$this->getTableName()] as $field) {
			if(in_array('primary_key',$field['flags'])) {
				$res[] = $field['name'];
			}
		}
		return $res;
	}

	private function getFieldsForDatabaseAccess() {
		$a = array();
		foreach ($this->getFields() as $field) {
			if(in_array($field,array_keys($this->data))) {
				$a[] = $field;
			}
		}
		return $a;
	}

	private function getPrimaryKeySqlPrepareStringForFilter() {
		$a = array();
		foreach ($this->getPrimaryKeyFields() as $field) {
			$a[] = '`' . $field . '`=:'. $field;
		}
		return implode(' and ',$a);
	}

	private function checkPrimaryKey($kv) {
		return !count(array_diff($this->getPrimaryKeyFields(),array_keys($kv)));
	}

	private function existsInDatabase($kv) {
		$sql = self::$db->prepare('SELECT count(*) FROM '.$this->getTableName().' WHERE '.$this->getPrimaryKeySqlPrepareStringForFilter());
		self::bindValues($sql,$this->getPrimaryKeyFields(),$kv);
		$sql->execute();
		$result = $sql->fetch(PDO::FETCH_NUM);
		return $result[0];
	}

	/**
	 * @param array $kv primarykey=>value-pair
	 */ 
	public final function __construct($kv=null) {
		if($kv!=null && $this->checkPrimaryKey($kv)) {
			if($this->existsInDatabase($kv)) {
				$this->fromDatabase($kv);
			} else {
				$this->getInstance($kv);
			}
		}
	}

	/**
	 * loads an object from the database using the fully specified primarykey
	 * @param array $kv primarykey=>value-pair
	 * @return MyORM-sublass
	 */
	public function fromDatabase($kv) {
		//$t = new $this();
		if($this->checkPrimaryKey($kv)) {
			$sql = self::$db->prepare('SELECT * FROM '.$this->getTableName().' WHERE '.$this->getPrimaryKeySqlPrepareStringForFilter());
			self::bindValues($sql,$this->getPrimaryKeyFields(),$kv);
			$sql->execute();
			$this->data = $sql->fetch(PDO::FETCH_ASSOC);
		}
		return $this;
	}

	/**
	 * instanciates a new object using the fully specified primarykey
	 * @param array primarykey=>value-pair
	 * @return MyORM-subclass
	 */
	public function getInstance($kv) {
		//$t = new $this();
		if(!$this->checkPrimaryKey($kv)) {
			throw new Exception('Pk not specified!',0);
		}
		$this->data = array();
		foreach ($kv as $key => $value) {
			if(in_array($key,$this->getFields())) {
				$this->data[$key] = $value;
			}
		}
		$this->flag = 'insert';
		return $this;
	}

	private function fromSqlRow($row) {
		$t = new $this();
		$t->data = $row;
		return $t;
	}

	public function __toString() {
		return print_r($this->data,true);
	}

	public function __set($name, $value) {
		if(in_array($name,array_keys($this->data)) && $value == $this->data[$name]) return;
		if(in_array($name,$this->getPrimaryKeyFields())) {
			$this->data[$name] = $value;
			$this->flag = 'insert';
			throw new Exception('Change of primarykey',0);
		}
		if(in_array($name,$this->getFields())) {
			$this->data[$name] = $value;
			$this->flag = $this->flag=='clean'?'update':$this->flag;
		}
	}

	public function __get($name) {
		if(in_array($name,$this->getFields()) && in_array($name,array_keys($this->data))) {
			return $this->data[$name];
		}
		$a = $this->getAssociation($name);
		if($a && preg_match('/[1n]:1/',$a['type'])) {
			$param = array();
			foreach ($a['bind'] as $field) {
				$param[':'.$field] = $this->data[$field];
			}
			$r = new $name();
			return $r->selectFirst('WHERE '.$a['cond'],$param);
		}
		$name_sg = substr($name,0,-1);
		$a = $this->getAssociation($name_sg);
		if($a && preg_match('/[1n]:n/',$a['type'])) {
			$param = array();
			foreach ($a['bind'] as $field) {
				$param[':'.$field] = $this->data[$field];
			}
			$r = new $name_sg();
			return $r->select('WHERE '.$a['cond'],$param);
		}
		return null;
	}

	/**
	 * flag the object to be deleted. call save() to apply.
	 */
	public function delete() {
		$this->flag = 'delete';
	}

	/**
	 * selects objects from the database.
	 * @param string $statement sql-statement after the from-clause
	 * @param array $params array of parameters to bind.
	 * @param int $number number of results to consider (alternative to LIMIT).
	 * @return array of MyORM-subclasses
	 */
	public function select($statement='', $params=array(), $number=null) {
		$statement = 'SELECT '.$this->getTableName().'.* FROM '.$this->getTableName().' '.$statement; 
		$sql = self::$db->prepare($statement);
		//print_r($sql->errorInfo());
		$sql->execute($params);
		$i = 0;
		$res = '';
		$return = array();
		while ($number==null?1:$i++<$number) {
			$res = $sql->fetch(PDO::FETCH_ASSOC);
			if ($res===false) break;
			$return[] = $this->fromSqlRow($res);
		}
		return $return;
	}

	/**
	 * selects the first object from the database.
	 * @param string $statement sql-statement after the from-clause
	 * @param array $params array of parameters to bind.
	 * @return MyORM-subclass
	 */
	public function selectFirst($statement='', $params=array()) {
		$t = $this->select($statement, $params, 1);
		return $t[0];
	}

	/**
	 * writes the current state of the object to the database.
	 * @return int 0 if object was clean, 1 if insert/update/delete was performed.
	 */
	public function save() {
		$statement = $bindFields = null;
		switch ($this->flag) {
			case 'insert':
				$fields = $this->getFieldsForDatabaseAccess();
				$statement = 'INSERT INTO '.$this->getTableName();
				$statement .= ' (' . implode(',',$fields) . ') VALUES';
				$statement .= ' (:' . implode(', :',$fields) . ')';
				$bindFields = $fields;
				break;
			case 'update':
				$fields = $this->getFieldsForDatabaseAccess();
				$statement = 'UPDATE '.$this->getTableName().' SET ';
				$t = array();
				foreach ($fields as $field) {
					$t[] = '`' . $field . '`=:'. $field;
				}
				$statement .= implode(', ',$t);
				$statement .= ' WHERE '.$this->getPrimaryKeySqlPrepareStringForFilter();
				$bindFields = $fields;
				break;
			case 'delete':
				$statement = 'DELETE FROM '.$this->getTableName().' WHERE '.$this->getPrimaryKeySqlPrepareStringForFilter();
				$bindFields = $this->getPrimaryKeyFields();
				break;
			default:
				return 0;
		}
		//echo $statement;
		//self::$db->beginTransaction();
		$sql = self::$db->prepare($statement);
		self::bindValues($sql,$bindFields,$this->data);
		$sql->execute();
		//print_r($sql->errorInfo());
		/*if ($sql->rowCount() === 1) {
		self::$db->commit();
		$this->flag = 'clean';
		} else {
		self::$db->rollBack();
		throw new Exception('Problem while '.$this->flag.' - would have affected '.$sql->rowCount().' rows');
		}*/
		$this->flag = 'clean';
		return $sql->rowCount() === 1;
	}
	
	/**
	 * stores associations.
	 * @var array table=>foreigntable=>{cond,bind,type}.
	 */
	private static $associations = array();
	
	/**
	 * adds an association/relationship to the orm-model.
	 * @param string $table1 table-name.
	 * @param string $table2 table-name.
	 * @param string $type from {'1:1','1:n','n:1','n:n'}.
	 * @param string $condition sql-condition including tablename-prefixes.
	 * @param bool $symmetric adds the association the other way round as well.
	 */
	public static function addAssociation($table1,$table2,$type,$condition,$symmetric=true) {
		if(!preg_match('/^[1n]:[1n]$/',$type)) {
			throw new Exception('Wrong type specified',0);
		}
		$cond = str_replace($table1.'.',':',$condition);
		preg_match_all('/:([A-Za-z]+)/',$cond,$bind);
		self::$associations[$table1][$table2] = array('cond'=>$cond,'bind'=>$bind[1],'type'=>$type);
		if($symmetric) {
			self::addAssociation($table2,$table1,strrev($type),$condition,false);
		}
	}

	public function getAssociation($foreignTable=null) {
		if($foreignTable==null) {
			return self::$associations[$this->getTableName()];
		} else if(in_array($foreignTable,array_keys(self::$associations[$this->getTableName()]))) {
			return self::$associations[$this->getTableName()][$foreignTable];
		} else {
			return null;
		}
	}

}

?>

