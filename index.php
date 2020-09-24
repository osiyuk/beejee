<?php

ini_set('display_errors', true);

define('PAGINATION', 3);
define('ADMIN', 'admin:123');
define('AUTH_EXPIRES', time() + 60*60*10); // 10 hours

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
UPDATE tasks SET updated = $updated, text = :a WHERE created = $key;"
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
		global $db;

		return $db->exec("
DELETE FROM tasks WHERE created = $key;");
	}
}


// Functions

function auth_cookie(string $login) : string
{
	$credentials = $_SERVER['REMOTE_ADDR'] . ';' . $login;
	$cookie = hash('sha512', $credentials);

	unset($credentials);
	return $cookie;
}


function get_url(string $param, ?string $value) : string
{
	parse_str($_SERVER['QUERY_STRING'], $query);
	$query[$param] = $value;

	return '/?' . http_build_query($query);
}


function get_key() : int
{
	return filter_input(INPUT_POST, 'key', FILTER_VALIDATE_INT);
}


// Controller

$authorization = false;

if (isset($_COOKIE['AUTH']) && auth_cookie(ADMIN) == $_COOKIE['AUTH'])
	$authorization = true;


define('FILTER_STRIP_FLAGS',
	FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_BACKTICK);

define('SANITIZE_SPECIAL_CHARS', array(
	'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
	'flags'  => FILTER_STRIP_FLAGS));


if ('POST' == $_SERVER['REQUEST_METHOD']) switch($_POST['method']) {
case 'new_task':

	$definitions = array(
		'username' => SANITIZE_SPECIAL_CHARS,
		'email' => FILTER_VALIDATE_EMAIL,
		'text' => SANITIZE_SPECIAL_CHARS
	);

	$valid = true;
	$values = filter_input_array(INPUT_POST, $definitions);

	foreach ($values as $key => $val) {

		if (is_null($val) || trim($val) === '') {
			$valid = false;
			$errors[$key] = 1;
		}
		if ($val === false) {
			$valid = false;
			$errors[$key] = 2;
		}
		$values[$key] = trim($val);
	}

	setcookie('USER', $values['username']);
	setcookie('EMAIL', $values['email']);

	if ($valid)
		$task_created = (new Task)->create($values);
	break;

case 'login':
	$login = $_POST['login'] . ':' . $_POST['pass'];

	if (ADMIN != $login) {
		$errors['auth'] = 1;
		break;
	}

	setcookie('AUTH', auth_cookie($login), AUTH_EXPIRES);
	$authorization = true;
	break;

case 'logout':
	setcookie('AUTH', '');
	$authorization = false;
	break;

case 'task_completed':
	if ($authorization) {
		(new Task)->update(get_key(), array('type' => 'completed'));
		break;
	}

	$errors['auth_required'] = true;
	break;

case 'update_text':
	if (!$authorization) {
		$errors['auth_required'] = true;
		break;
	}

	$text = filter_input(INPUT_POST, 'text', FILTER_SANITIZE_SPECIAL_CHARS);
	$values = array('type' => 'text', 'text' => $text);

	(new Task)->update(get_key(), $values);
	break;

case 'delete_task':
	if ($authorization) {
		(new Task)->delete(get_key());
		break;
	}

	$errors['auth_required'] = true;
	break;
}

$definitions = array(
	'edit' => FILTER_VALIDATE_INT,
	'sort' => FILTER_VALIDATE_INT,
	'column' => FILTER_SANITIZE_STRING,
	'page' => FILTER_VALIDATE_INT
);

$GET = filter_input_array(INPUT_GET, $definitions);
$edit = intval($GET['edit']) ?? null;
$sort = boolval($GET['sort']);
$column = $GET['column'] ?? 'created';
$page = $GET['page'] ?? 1;

$tasks = (new Task)->read($sort, $column, $page);

$task_count = $db->querySingle("SELECT count(created) FROM tasks");
$pages = ceil($task_count / PAGINATION);

include_once "main.html";
phpinfo(INFO_VARIABLES); // INFO_CONFIGURATION
