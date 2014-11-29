<?php

namespace Mysql;

use Amp\Future;
use Amp\Success;

class Stmt {
	private $columnCount;
	private $paramCount;
	private $columns = [];
	private $params = [];
	private $stmtId;
	private $reactor;
	private $columnsToFetch;
	private $futures = [];
	private $conn;

	private $state = ResultSet::UNFETCHED;

	public function __construct(\Amp\Reactor $reactor, Connection $conn, $stmtId, $columns, $params) {
		$this->reactor = $reactor;
		$this->conn = $conn;
		$this->stmtId = $stmtId;
		$this->columnCount = $columns;
		$this->paramCount = $this->columnsToFetch = $params;
	}

	public function bind($paramId, $data) {
		// @TODO validate $paramId
		$this->conn->bindParam($this->stmtId, $paramId, $data);
	}

	public function execute($data = null) {
		// @TODO validate $data here
		return $this->conn->execute($this->stmtId, $data);
	}

	public function close() {
		return $this->conn->closeStmt($this->stmtId);
	}

	public function getFields() {
		if ($this->state >= ResultSet::COLUMNS_FETCHED) {
			return new Success($this->columns);
		} else {
			return $this->futures[] = new Future($this->reactor);
		}
	}

	private function updateState() {
		foreach ($this->futures as $future) {
			$future->succeed($this->columns);
		}
		$this->futures = [];
		$this->state = ResultSet::COLUMNS_FETCHED;
	}

	public function __debugInfo() {
		$tmp = clone $this;
		unset($tmp->reactor, $tmp->conn);
		foreach ($tmp->futures as &$future) {
			$future = null;
		}

		return $tmp;
	}
}