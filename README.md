# watrbx-fixed
Fixed version of the watrbx 1 frontend (public Roblox 2016 revival that's dead)


You still need to find the api server (that handles joining, etc.)


# Make sure to update .env

# Steps to start:
1. Install php, composer and mariadb using `sudo apt install php composer mariadb`
| Note:  Do not update using `composer update` since it breaks the fixes for Pixie.
2. Run `./init.sh` for first-time setup (dont run it again after you already ran it)
3. Run `./generate-key.sh` for first-time setup too
4. Run `./start.sh` to start the server.
5. Run `./start-db.sh` to start the MariaDB server.
6. profit

# To shutdown:
- Stop PHP server (`pkill php`)
- Stop MariaDB server (`mysqladmin -u <DATABASE_USER> shutdown && pkill mariadbd && pkill mysqld`)
