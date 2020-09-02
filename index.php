<?php

ini_set('display_errors', true);

define('PAGINATION', 3);
define('ADMIN', 'admin:123');

$db = new SQLite3('db.sqlite', SQLITE3_OPEN_READWRITE);


interface CRUD {
	public function create(array $fields): int;
	public function read(int $page, int $perPage): array;
	public function update(int $key, array $fields): int;
	public function delete(int $key): int;
}


final class Task implements CRUD
{
	protected $field_names = array('username', 'email', 'text');

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

	public function read(int $page = 1, int $limit = PAGINATION): array
	{
		global $db;

		$offset = ($page - 1) * $limit;
		$result = $db->query("
SELECT created as key, username, email, text
FROM tasks LIMIT $limit OFFSET $offset;"
		);

		$tasks = array();
		while ($row = $result->fetchArray(SQLITE3_ASSOC))
			$tasks[] = $row;

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


phpinfo(INFO_VARIABLES); // INFO_CONFIGURATION
