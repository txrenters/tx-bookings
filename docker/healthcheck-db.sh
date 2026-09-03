#!/bin/sh
# Health check for the database connection.
#
# Exists because the FPM check next door proves only that PHP-FPM answers, and
# on this box that is not the same thing as "the app works". MySQL runs on the
# VPS host and is reached over a socket bind-mounted from /run/mysqld. Docker
# resolves a bind mount to its target inode at container start, and the
# packaged mysql.service sets RuntimeDirectory=mysqld, so systemd deletes and
# recreates that directory on every mysqld restart -- leaving every container
# started beforehand holding the deleted one. It sees an empty
# /var/run/mysqld, every route 500s with SQLSTATE[HY000] [2002], and without
# this check every container still reports healthy right through the outage.
# See the DB_SOCKET_DIR comment in docker-compose.yml.
#
# Deliberately does NOT boot Laravel: a bare PDO connect costs a few
# milliseconds where `artisan` would load the whole framework every 30s, and
# booting more code only adds ways for the check itself to fail.
set -e

# The socket case is the one that breaks here, and checking the path first
# turns the common failure into an immediate, obvious exit rather than a PDO
# exception that has to be read to be understood.
if [ -n "${DB_SOCKET:-}" ] && [ ! -S "${DB_SOCKET}" ]; then
    echo "healthcheck-db: ${DB_SOCKET} is missing or not a socket." >&2
    echo "healthcheck-db: mysqld likely restarted and this container is holding" >&2
    echo "healthcheck-db: a stale bind mount. Recreate it to recover." >&2
    exit 1
fi

# SELECT 1 rather than a bare connect: it proves the credentials work and the
# server will actually serve this database, not merely that something is
# listening on the socket.
exec php -r '
    $socket   = getenv("DB_SOCKET") ?: "";
    $database = getenv("DB_DATABASE") ?: "";
    $dsn = $socket !== ""
        ? sprintf("mysql:unix_socket=%s;dbname=%s", $socket, $database)
        : sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST") ?: "127.0.0.1", getenv("DB_PORT") ?: "3306", $database);

    try {
        $pdo = new PDO($dsn, getenv("DB_USERNAME") ?: "", getenv("DB_PASSWORD") ?: "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Bounded so a wedged server fails the check instead of hanging
            // until Docker kills it on the (longer) health check timeout.
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $pdo->query("SELECT 1");
    } catch (Throwable $e) {
        // getMessage() carries the DSN but never the password.
        fwrite(STDERR, "healthcheck-db: ".$e->getMessage().PHP_EOL);
        exit(1);
    }
'
