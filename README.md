# PunBB

[![check](https://github.com/mageown/punbb/actions/workflows/check.yml/badge.svg)](https://github.com/mageown/punbb/actions/workflows/check.yml)

PunBB is a fast and lightweight PHP-powered discussion board. It is released under the GNU General Public License. Its primary goals are to be faster, smaller and less graphically intensive as compared to other discussion boards. PunBB has fewer features than many other discussion boards, but is generally faster and outputs smaller, semantically correct XHTML-compliant pages.

## Quick install
 1. [Download the latest revision of PunBB](https://punbb.informer.com/downloads.php). Decompress the PunBB archive to a directory.
 2. Copy (or upload) all the files contained in this archive into the directory where you want to run your forums. (e.g. /home/user/www/punbb/)
 3. Run `composer install --no-dev` in that directory to generate `vendor/autoload.php`. PunBB does not start without it.
 4. Copy `.htaccess.dist` to `.htaccess` (merge into it if you already have one). It denies access to `vendor/`, `.dev/` and the tooling manifests, and holds the SEF rewrite rules.
 5. Run install.php from the forum admin directory (e.g. open http://example.com/punbb/admin/install.php in your browser). Follow the instructions.

## Requirements
 - A webserver
 - PHP 8.4 or later, with the `mbstring`, `intl`, `json` and `xml` extensions, plus `openssl` on a host that has neither cURL nor `allow_url_fopen` (the socket fallback fetches over TLS)
 - [Composer 2](https://getcomposer.org/)
 - A database where forum data is to be stored, created in one of: MySQL 8.0 or later, PostgreSQL 13 or later or SQLite 3 (verified on MySQL 8.4 and PostgreSQL 17)

`$base_url` in `config.php` must be the forum's public address (scheme, host and port). Every self-referential URL is built from it; the request `Host` header is never used.

Set `$cookie_secure = 1` in `config.php` on an https forum. The login cookie carries the account's password hash, and forums installed before 2.0.0 have it hardcoded to `0`. A fresh install now derives it from `$base_url`.

The forum ships `.htaccess` files that deny access to `cache/` and to everything in `img/avatars/` that is not a `.gif`, `.jpg` or `.png`. A webserver that ignores `.htaccess` — nginx, Caddy — needs the equivalent rules of its own; `cache/` holds generated PHP including the SMTP password.

Supported `$db_type` values in `config.php`: `mysqli`, `mysqli_innodb`, `pgsql`, `sqlite3`. The `mysql`, `mysql_innodb` and `sqlite` (SQLite2) drivers were removed together with the PHP extensions they needed — an existing forum on one of them must change `$db_type` before running `admin/db_update.php`.

## Upgrade
Back up the forum directory and the database before you start.

 1. If `$db_type` in `config.php` is `mysql`, `mysql_innodb` or `sqlite`, change it to `mysqli`, `mysqli_innodb` or `sqlite3` first. `admin/db_update.php` stops on a removed driver and names the replacement.
 2. Turn maintenance mode on and disable every extension in the administration console.
 3. Overwrite the old files with the new ones, keeping `config.php`, `img/avatars/` and `extensions/`.
 4. Run `composer install --no-dev` and empty the `cache/` directory.
 5. Open `admin/db_update.php` in your browser and follow it to the end.
 6. Delete `admin/db_update.php`. It has no permission check: while it is there, any visitor can drive the migration.
 7. Update the extensions, re-enable them and turn maintenance mode off.

Everyone is logged out once when upgrading to 2.0.0: the login cookie's format changed and cookies issued by an earlier version no longer validate.

Extensions written for PunBB 1.4 may need changes: `ChangeLog` lists the breaking ones.

## Extension installation
 1. Download an extension's archive from the PunBB extensions repository or any other place. Extract it into your forum’s extensions directory. (e.g. /home/user/example.com/punbb/extensions)
 2. Log into the forum and go to "Administration" console, "Extensions" section, choose "Install extensions" tab (e.g. http://example.com/punbb/admin/extensions.php?section=install). The downloaded extension will be listed there.
 3. Click the "Install extension" link to install the extension.

NOTE: You may use the pun_repository official PunBB extension to download and install extensions from PunBB repository with one click.

## Performance
 - Enable OPcache. It ships with PHP and only needs turning on.
 - Enable gzip output compression in "Administration", "Settings", or let the webserver do it with mod_deflate.
 - Disable the forum features you do not use in the administration interface.

## Contributing

Please report issues on the [Github issue tracker](https://github.com/punbb/punbb/issues).
Personal email addresses are not appropriate for bug reports.

Vulnerabilities go through private reporting instead — see [SECURITY.md](SECURITY.md).

## Links
 - [Documentation](https://punbb.informer.com/wiki/)
 - [Internationalization](https://punbb.informer.com/wiki/punbb13/language_packs)
 - [Styles](https://punbb.informer.com/wiki/punbb13/syles)
 - [Extensions repository](https://punbb.informer.com/extensions/)
 - [Community Forums](https://punbb.informer.com/forums/)
 - [Twitter](https://twitter.com/punbb_forum)
 - [Development](https://github.com/punbb/punbb/)

## Copyright and disclaimer
This package and its contents are (C) 2002-2012 PunBB, all rights reserved.
Partially based on code (C) 2008-2009 FluxBB.org.

PunBB is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

PunBB is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA.

Good luck.
