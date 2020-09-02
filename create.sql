create table tasks (
	created datetime primary key,
	updated datetime,
	completed datetime,
	username text not null,
	email text not null,
	text text not null
);

