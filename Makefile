init:
	sqlite3 db.sqlite < create.sql
	sqlite3 db.sqlite .schema

clear:
	rm -f db.sqlite

server:
	DATABASE=db.sqlite php -S localhost:80
