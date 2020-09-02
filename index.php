<?php

ini_set('display_errors', true);

define('PAGINATION', 3);
define('ADMIN', 'admin:123');

$db = new SQLite3('db.sqlite', SQLITE3_OPEN_READWRITE);

interface CRUD {
	public function create(array $fields): int;
	public function read(bool $sort, string $column,
				int $page, int $perPage): array;
	public function update(int $key, array $fields): int;
	public function delete(int $key): int;
}


// Model

final class Task implements CRUD
{
	protected $field_names = array('created', 'username', 'email', 'text');

	public function create(array $fields): int
	{
		global $db;

		$created = time(); // unix timestamp
		$query = $db->prepare("
INSERT INTO tasks (created, username, email, text)
VALUES ($created, :a, :b, :c);"
		);
		$query->bindValue(':a', $fields['username'], SQLITE3_TEXT);
		$query->bindValue(':b', $fields['email'], SQLITE3_TEXT);
		$query->bindValue(':c', $fields['text'], SQLITE3_TEXT);

		$query->execute();
		return $db->lastInsertRowID();
	}

	public function read(bool $sort, string $column,
				int $page, int $limit = PAGINATION): array
	{
		global $db;

		$sort = $sort ? 'ASC' : 'DESC';
		$column = in_array($column, $this->field_names)
				? $column : 'created';

		$offset = ($page - 1) * $limit;
		$result = $db->query("
SELECT created as key, username, email, text, completed, updated
FROM tasks ORDER BY $column $sort LIMIT $limit OFFSET $offset;"
		);

		$tasks = array();
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			if ($row['completed'] > 0)
				$status[] = array('success' => 'выполнено');
			unset($row['completed']);

			if ($row['updated'] > 0)
				$status[] = array('light' =>
					'отредактировано администратором');
			unset($row['updated']);

			$row['status'] = $status;
			$tasks[] = $row;
		}

		return $tasks;
	}

	public function update(int $key, array $fields) : int
	{
		global $db;

		switch ($fields['type']) {
		case 'completed':
			$completed = time();
			return $db->exec("
UPDATE tasks SET completed = $completed WHERE created = $key;"
			);

		case 'text':
			$updated = time();
			$query = $db->prepare("
UPDATE tasks SET updated = $updated, text = :text WHERE created = $key;"
			);
			$query->bindValue(':a', $fields['text'], SQLITE3_TEXT);

			$result = $query->execute();
			return boolval($result);

		default:
			return false;
		}
	}

	public function delete(int $key) : int
	{
		return 0;
	}
}


// Controller

$sort = boolval($_GET['sort'] ?? 0);
$column = $_GET['column'] ?? 'created';

$page = intval($_GET['page']) ?? 1;
$tasks = (new Task)->read($sort, $column, $page);

$task_count = $db->querySingle("SELECT count(created) FROM tasks");
$pages = ceil($task_count / PAGINATION);

phpinfo(INFO_VARIABLES); // INFO_CONFIGURATION
