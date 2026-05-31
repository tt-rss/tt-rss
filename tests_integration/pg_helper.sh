#!/usr/bin/env bash
#
# PostgreSQL lifecycle helper for integration tests.
# Manages a temporary PG instance: init, start, stop, db creation.
#
# Usage:
#   pg_helper.sh init        — initialize data directory
#   pg_helper.sh start       — start the server
#   pg_helper.sh stop        — stop the server
#   pg_helper.sh create-db   — create the test database
#   pg_helper.sh drop-db     — drop the test database
#   pg_helper.sh status      — print status
#
# Environment variables (with defaults):
#   __TTRSS_PG_PORT        — port for the test PG instance (default: 55432)
#   __TTRSS_PG_DATA_DIR    — temp data directory (default: auto-created in /tmp)
#   __TTRSS_PG_USER        — database user (default: ttrss_test)
#   __TTRSS_PG_PASS        — database password (default: ttrss_test)
#   __TTRSS_PG_DB          — database name (default: ttrss_test)
#   __TTRSS_PG_BIN_DIR     — PostgreSQL bin directory (default: detected)
#

set -euo pipefail

# ── Helpers ──────────────────────────────────────────────────────────────────

log() {
    echo "[$(date '+%H:%M:%S')] [pg_helper] $*"
}

die() {
    echo "ERROR: $*" >&2
    exit 1
}

wait_for_pg() {
    local max_wait="${1:-30}"
    local waited=0

    while ! "$PG_BIN_DIR"/pg_isready -h localhost -p "$PG_PORT" -q 2>/dev/null; do
        if (( waited >= max_wait )); then
            die "PostgreSQL did not start within ${max_wait}s"
        fi
        sleep 0.5
        (( waited++ ))
    done
}

# ── Configuration ────────────────────────────────────────────────────────────

PG_PORT="${__TTRSS_PG_PORT:-55432}"
PG_USER="${__TTRSS_PG_USER:-ttrss_test}"
PG_PASS="${__TTRSS_PG_PASS:-ttrss_test}"
PG_DB="${__TTRSS_PG_DB:-ttrss_test}"

# Superuser for admin operations (created by initdb, matches OS user)
PG_SUPERUSER="${__TTRSS_PG_SUPERUSER:-$(whoami)}"

# Auto-detect PG bin directory
if [[ -n "${__TTRSS_PG_BIN_DIR:-}" ]]; then
    PG_BIN_DIR="$__TTRSS_PG_BIN_DIR"
else
    # Try common locations for PostgreSQL
    for candidate in \
        "/usr/bin" \
        "/usr/lib/postgresql/16/bin" \
        "/usr/local/pgsql/bin" \
    ; do
        if [[ -x "$candidate/pg_ctl" ]]; then
            PG_BIN_DIR="$candidate"
            break
        fi
    done
    [[ -z "${PG_BIN_DIR:-}" ]] && die "Cannot find pg_ctl. Set __TTRSS_PG_BIN_DIR."
fi

# Create a unique temp data directory (use PID to avoid collisions)
if [[ -n "${__TTRSS_PG_DATA_DIR:-}" ]]; then
    PG_DATA_DIR="$__TTRSS_PG_DATA_DIR"
else
    # Use a deterministic name based on port so init/start/use all share the same dir
    PG_DATA_DIR="/tmp/ttrss-pg-${PG_PORT}"
fi

PG_LOG_FILE="$PG_DATA_DIR/pg.log"
PG_PID_FILE="$PG_DATA_DIR/postmaster.pid"

# ── Commands ─────────────────────────────────────────────────────────────────

cmd_init() {
    if [[ -f "$PG_DATA_DIR/PG_VERSION" ]]; then
        log "Data directory already initialized: $PG_DATA_DIR"
        return 0
    fi
    log "Initializing PostgreSQL data directory: $PG_DATA_DIR"
    "$PG_BIN_DIR"/initdb --auth=trust --auth-host=md5 --encoding=UTF8 -D "$PG_DATA_DIR" 2>&1 | tail -3
    log "Data directory initialized."
}

cmd_start() {
    if [[ -f "$PG_PID_FILE" ]] && "$PG_BIN_DIR"/pg_isready -h localhost -p "$PG_PORT" -q 2>/dev/null; then
        log "PostgreSQL already running on port $PG_PORT."
        return 0
    fi

    log "Starting PostgreSQL on port $PG_PORT ..."
    "$PG_BIN_DIR"/pg_ctl start \
        -D "$PG_DATA_DIR" \
        -l "$PG_LOG_FILE" \
        -o "-c port=$PG_PORT -c unix_socket_directories=." \
        -w

    # Trust all local connections (TCP and socket) for testing
    cat > "$PG_DATA_DIR/pg_hba.conf" <<'HBA'
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             all                                     trust
host    all             all             127.0.0.1/32            trust
host    all             all             ::1/128                 trust
HBA

    # Reload to pick up new pg_hba.conf
    "$PG_BIN_DIR"/pg_ctl reload -D "$PG_DATA_DIR" -w

    wait_for_pg 30
    log "PostgreSQL started on port $PG_PORT."
}

cmd_stop() {
    if [[ ! -f "$PG_PID_FILE" ]]; then
        log "PostgreSQL not running."
        return 0
    fi

    log "Stopping PostgreSQL ..."
    "$PG_BIN_DIR"/pg_ctl stop -D "$PG_DATA_DIR" -w 2>/dev/null || true
    rm -f "$PG_PID_FILE"
    log "PostgreSQL stopped."
}

cmd_create_user() {
    log "Creating user '$PG_USER' ..."
    "$PG_BIN_DIR"/createuser -h localhost -p "$PG_PORT" -U "$PG_SUPERUSER" --superuser "$PG_USER" 2>/dev/null || true
    log "User ready."
}

cmd_create_db() {
    log "Creating database '$PG_DB' ..."
    # Create user first if it doesn't exist
    "$PG_BIN_DIR"/createuser -h localhost -p "$PG_PORT" -U "$PG_SUPERUSER" --superuser "$PG_USER" 2>/dev/null || true
    "$PG_BIN_DIR"/createdb -h localhost -p "$PG_PORT" -U "$PG_SUPERUSER" "$PG_DB" 2>/dev/null || true
    log "Database ready."
}

cmd_drop_db() {
    log "Dropping database '$PG_DB' ..."
    "$PG_BIN_DIR"/dropdb -h localhost -p "$PG_PORT" -U "$PG_USER" "$PG_DB" --if-exists 2>/dev/null || true
}

cmd_status() {
    if [[ -f "$PG_PID_FILE" ]]; then
        echo "running (pid: $(cat "$PG_PID_FILE" | head -1))"
    else
        echo "stopped"
    fi
}

# ── Main ─────────────────────────────────────────────────────────────────────

case "${1:-help}" in
    init)       cmd_init ;;
    start)      cmd_start ;;
    stop)       cmd_stop ;;
    create-user) cmd_create_user ;;
    create-db)  cmd_create_db ;;
    drop-db)    cmd_drop_db ;;
    status)     cmd_status ;;
    cleanup)    cmd_stop; rm -rf "$PG_DATA_DIR"; log "Cleaned up $PG_DATA_DIR" ;;
    *)
        echo "Usage: $0 {init|start|stop|create-user|create-db|drop-db|status|cleanup}"
        exit 1
        ;;
esac
