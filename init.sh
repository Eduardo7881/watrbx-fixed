mkdir -p /var/lib/mysql
mariadb-initialize-db --datadir=$PREFIX/var/lib/mysql < structure.sql
