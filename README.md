# Gazelle
Gazelle is a web framework geared towards private BitTorrent trackers.
Although naturally focusing on music, it can be modified for most
needs. Gazelle is written in PHP, JavaScript, and MySQL.

## Gazelle Runtime Dependencies
* [Nginx](http://wiki.nginx.org/Main) (recommended)
* [PHP 7 or newer](https://www.php.net/) (required)
* [Memcached](http://memcached.org/) (required)
* [Sphinx 2.0.6 or newer](http://sphinxsearch.com/) (required)
* [procps-ng](http://sourceforge.net/projects/procps-ng/) (recommended)

## Gazelle/Ocelot Compile-time Dependencies
* [Git](http://git-scm.com/) (required)
* [GCC/G++](http://gcc.gnu.org/) (4.7+ required; 4.8.1+ recommended)
* [Boost](http://www.boost.org/) (1.55.0+ required)

_Note: This list may not be exhaustive._

## Logchecker
To fully utilize the Logchecker, you must install the following
depedencies through `pip`:
* chardet
* eac-logchecker
* xld-logchecker

## Gazelle Development
Gazelle can be run through Docker (container) or Vagrant (Virtual
Machine). Historically, Vagrant was used, but recently Docker support
was added and is the preferred method of development.

### Docker (Recommended)
Install Docker for your preferred system and run the following
command:

`docker-compose up`

This will build and pull the needed images to run Gazelle on Debian
Buster. A volume is mounted from the base of the git repository at
`/var/www` in the container. Changes to the source code are
immediately served without rebuilding or restarting.

If you want to poke around inside the web container, open a shell:

`export WEBCONT=$(docker ps|awk '$2 ~ /web$/ {print $1}')`

`docker exec -it $WEBCONT bash`

To keep an eye on PHP errors during development:

`docker exec -it $WEBCONT tail -n 20 -f /var/log/nginx/error.log`

To create a Phinx migration:

`docker exec -it $WEBCONT vendor/bin/phinx create MyNewMigration`

Edit the resulting file and then apply it:

`docker exec -it $WEBCONT vendor/bin/phinx migrate`

You may want to install additional packages:
* `apt update`
* `apt install less procps vim`

You can run Boris directly:

`docker exec -it $WEBCONT /var/www/boris`

To access the database, save the following in `~root/.my.cnf` of
the database container:

```
    [mysql]
    user = root
    password = <sekret>
    database = gazelle
```

And then:
`docker exec -it $(docker ps|awk '$2 ~ /^mariadb/ {print $1}') mysql`

In the same vein, you can use `mysqldump` to perform a backup.

#### Production Mode (not fully baked yet)
In order to have Docker run the container using the production mode commands
for both Composer and NPM, run this when powering it up:

`ENV=prod docker-compose up`

### Vagrant (Legacy)
This repository comes pre-setup to be run through
[Vagrant](https://www.vagrantup.com/) for ease of development and
without having to modify your local machine. You can look through
the docs for how it works, but to start, you just need to download
Vagrant and VirtualBox (and it's recommended to get the
[vagrant-vbguest](https://github.com/dotless-de/vagrant-vbguest)
plugin) and then simply run: ``` vagrant up ```

This will build a Debian Jessie on a Virtual Machine and serve this
repository through `/var/www` on the machine.

### Forwarded Ports
Both Docker and Vagrant will forward the following ports:
* 80 -> 8080 (web)
* 3306 -> 36000 (mysql)
* 34000 -> 34000 (ocelot)

You can access the site by going to `http://localhost:8080`

## Contact and Discussion
Feel free to join #develop on irc.orpheus.network to discuss any
questions concerning Gazelle (or any of the repos published by
Orpheus).

## Open source
Open issues at https://github.com/OPSnet.
Patches welcome!
