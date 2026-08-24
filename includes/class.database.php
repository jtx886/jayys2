<?php
// 数据库类 - 使用mysqli兼容所有PHP版本
class Database {
    private $conn;
    private static $instance = null;

    public function __construct() {
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        $this->conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->conn->connect_error) {
            // 静默失败，在安装时提示
            $this->conn = null;
            return;
        }

        $this->conn->set_charset(DB_CHARSET);
    }

    public function getConnection() {
        return $this->conn;
    }

    public function isConnected() {
        return $this->conn !== null;
    }

    public function query($sql, $params = array(), $types = '') {
        if ($this->conn === null) {
            return false;
        }

        if (empty($params)) {
            return $this->conn->query($sql);
        }

        if (empty($types)) {
            $types = str_repeat('s', count($params));
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        array_unshift($params, $types);
        call_user_func_array(array($stmt, 'bind_param'), $this->refValues($params));
        
        $result = $stmt->execute();
        
        if ($this->isSelect($sql)) {
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        }

        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected !== -1 ? $affected : false;
    }

    public function fetchAll($sql, $params = array(), $types = '') {
        $result = $this->query($sql, $params, $types);
        if (!$result) {
            return array();
        }
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();
        return $data;
    }

    public function fetchOne($sql, $params = array(), $types = '') {
        $result = $this->query($sql, $params, $types);
        if (!$result) {
            return null;
        }
        $row = $result->fetch_assoc();
        $result->free();
        return $row;
    }

    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $types = '';
        $values = array();
        
        foreach ($data as $val) {
            if (is_int($val)) {
                $types .= 'i';
            } elseif (is_float($val)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $val;
        }

        $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $this->query($sql, $values, $types);
        return $this->conn ? $this->conn->insert_id : false;
    }

    public function update($table, $data, $where, $whereParams = array(), $whereTypes = '') {
        $setClauses = array();
        $types = '';
        $values = array();

        foreach ($data as $field => $val) {
            $setClauses[] = "`$field` = ?";
            if (is_int($val)) {
                $types .= 'i';
            } elseif (is_float($val)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $val;
        }

        $values = array_merge($values, $whereParams);
        $types .= $whereTypes;

        $sql = "UPDATE `$table` SET " . implode(', ', $setClauses) . " WHERE $where";
        return $this->query($sql, $values, $types);
    }

    public function delete($table, $where, $params = array(), $types = '') {
        $sql = "DELETE FROM `$table` WHERE $where";
        return $this->query($sql, $params, $types);
    }

    private function isSelect($sql) {
        return preg_match('/^\s*SELECT/i', $sql);
    }

    private function refValues($arr) {
        $refs = array();
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

    public function escape($str) {
        if ($this->conn === null) {
            return addslashes($str);
        }
        return $this->conn->real_escape_string($str);
    }

    public function error() {
        return $this->conn ? $this->conn->error : 'Database not connected';
    }
}
?>
