# masscan-web-ui
MASSCAN Web UI

https://www.offensive-security.com/offsec/masscan-web-interface/

Here's a quick guide to get started:

* Install and setup your web server, copy over the MASSCAN web-ui files:

```
root@kali:~# apt-get install apache2 php libapache2-mod-php php-mysqli php-xml mysql-server
root@kali:~# git clone https://github.com/offensive-security/masscan-web-ui
root@kali:~# mv masscan-web-ui/* /var/www/html/
root@kali:~# cd /var/www/html/
```

* Create a database, user, then import database.

```
root@kali:/var/www/html# mysql -u root -p
Enter password: 
Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

mysql> create database masscan;
Query OK, 1 row affected (0.01 sec)

mysql> CREATE USER 'masscan'@'localhost' IDENTIFIED BY 'changem3';
Query OK, 0 rows affected (0.00 sec)

mysql> GRANT ALL PRIVILEGES ON masscan.* TO 'masscan'@'localhost';
Query OK, 0 rows affected (0.01 sec)

mysql> exit
Bye
root@kali:/var/www/html# mysql -u root -p masscan < db-structure.sql 
Enter password: 
root@kali:/var/www/html# rm db-structure.sql index.html README.md
```

Update the web config file with the database information:

```
nano config.php
define('DB_DRIVER',	    'mysql');
define('DB_HOST',       'localhost');
define('DB_USERNAME',   'masscan');
define('DB_PASSWORD',   'changem3');
define('DB_DATABASE',   'masscan');
```

Now you can use masscan to scan your targets, while specifying an XML output. More information about banner grabbing with masscan can be found at https://github.com/robertdavidgraham/masscan#banner-checking.

```
masscan 10.0.0.0/8 -p80,21,53 --banners --source-ip 10.0.0.2 --max-rate 1000000 -oX scan-01.xml
```

Now let's import some scan results. In this example we imported the results of two class A scans:

```
root@kali:/var/www/html# ls -l scan*
-rw-r--r-- 1 root root 212929324 Dec  1 13:23 scan-01.xml
-rw-r--r-- 1 root root 700816226 Dec  1 13:55 scan-02.xml
root@kali:/var/www/html# php import.php scan-01.xml 

Do you want to clear the database before importing (yes/no)?: yes

Clearing the db
Reading file
Parsing file
Processing data (This may take some time depending on file size)

Summary:
Total records:738279
Inserted records:738279
Took about:3 minutes,18 seconds
root@kali:/var/www/html# php import.php scan-02.xml 

Do you want to clear the database before importing (yes/no)?: no
Reading file
Parsing file
Processing data (This may take some time depending on file size)

Summary:
Total records:2411974
Inserted records:2411974
Took about:9 minutes,41 seconds
root@kali:/var/www/html# 
```
A total of more than 3 million results are now easily searchable. 

## Security maintenance and supported deployment

URL filter values for protocol, state, service, banner and text now use PDO
prepared statements in both the result and count queries. PostgreSQL text
search treats input as plain search terms rather than executable tsquery syntax.
PHP 8 compatibility also requires a valid PDO fetch mode; the database health
check now uses `PDO::FETCH_ASSOC`. Web requests receive bounded execution time,
bounded memory and generic query errors instead of database exception details.

The importer accepts only the exact `cli` SAPI. PHP's built-in development web
server uses `cli-server` and receives HTTP 403 before database configuration.
Run `python3 tests/import_boundary.py` to verify this boundary with a loopback
server. Import only trusted scan files. This project has no
built-in authentication: place it behind an authenticated private gateway and
limit database access to the required tables. Do not expose it directly to the
internet or use the example `changem3` password in a real deployment. Keep real
configuration outside publicly readable web content and source control.

### PHP runtime RCE patches

The PHP project fixed CVE-2026-109618 and CVE-2026-109619 in PHP 8.2.35,
8.3.36, 8.4.27 and 8.5.12. Use the current vendor-patched release in a supported
branch, or a distribution package with documented backports. A Composer audit
cannot establish that the hosting engine contains these fixes. Verify the PHP
version used by the web server, not only the command-line binary, and restart
the relevant workers after upgrading.

The separate [October 23 advisory](https://www.php.net/archive/2026.php#2026-10-23-1)
tracks CVE-2026-115560. It requires upload progress enabled together with a
configuration serializer of `php_binary`/`php_serialize` that application code
changes to `php` before `session_start()`. The October 21 patch floors above
do **not** establish a fix for this later issue; a full advisory and regular
bugfix release were still pending at the November 3 review.

Load `session.upload_progress.enabled = 0` at PHP **process startup**, using
`php.ini`, a startup-scanned file such as `deploy/php-security.ini`, or the
appropriate server/FPM configuration. Restart every relevant worker and verify
the actual web SAPI. The earlier `.user.ini` precaution was removed: PHP
explicitly says that FPM `.user.ini` and application `ini_set()` are too late
for the confirmed request-parsing attack paths. Copying the example into the
web root does not activate it. Confirm application upload-progress features
before rollout; normal file uploads and ordinary sessions remain available.
This is temporary containment, and does not replace vendor runtime patches.
`python3 tests/php_startup_configuration.py` validates the example using an
isolated configuration, ordinary sessions, and an unmitigated negative control.
It does not verify a deployed web worker or perform an exploit.

For Windows PHP, the [October 26 rebuild](https://www.php.net/archive/2026.php#2026-10-26-1)
adds a separate Brotli fix (CVE-2026-111497). Use the vendor's `-1` packages
(8.2.35-1/8.3.36-1/8.4.27-1/8.5.12-1), or later patched packages, and verify
bundled Brotli 1.2.1 or later. The rebuild keeps the same `PHP_VERSION`, so
`php -v` alone cannot distinguish the old bundle from the patched one.

Run `php tests/browse_security.php` with PDO SQLite for isolated regression
checks. CI also exercises the same browsing code against MySQL and PostgreSQL
using disposable local databases and checks the patched PHP 8.5 runtime floor.
The tests use SQL injection strings as data; they do not scan a network or
contact a production database. Existing code outside these filters still needs
review, including XML import limits and deployment authentication.

Primary sources:
[PHP OOM corruption advisory](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w),
[PHP stale global state advisory](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp),
[PDO prepared statements](https://www.php.net/manual/en/pdo.prepared-statements.php),
and [supported PHP versions](https://www.php.net/supported-versions.php).
