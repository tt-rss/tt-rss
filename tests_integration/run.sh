#!/usr/bin/env bash
#
# Integration test runner for tt-rss.
#
# Orchestrates the full lifecycle:
#   1. Start temporary PostgreSQL instance
#   2. Create database and run schema migrations
#   3. Start PHP development server
#   4. Run PHPUnit integration tests
#   5. Clean up everything
#
# Usage:
#   ./tests_integration/run.sh [--cleanup-only] [--keep-db] [phpunit args...]
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# ── Helpers ──────────────────────────────────────────────────────────────────

log() {
    echo "[$(date '+%H:%M:%S')] [run] $*"
}

# ── Configuration ────────────────────────────────────────────────────────────

export PG_PORT=55432
export PHP_PORT1=9080
export PHP_PORT2=9081

export PG_USER="ttrss_test"
export PG_PASS="ttrss_test"
export PG_DB="ttrss_test"

if [[ -n "${__TTRSS_PG_DATA_DIR:-}" ]]; then
    export PG_DATA_DIR="$__TTRSS_PG_DATA_DIR"
fi

PHP_BIN=""

if [[ -n "${__TTRSS_PHP_BIN:-}" ]]; then
    PHP_BIN="${__TTRSS_PHP_BIN}"
else
    # Try common locations for php
    for candidate in \
        "php84" \
        "php8.4" \
        "php" \
    ; do
        if command -v $candidate >/dev/null; then
            PHP_BIN=$(command -v $candidate)
            break
        fi
    done
fi

CLEANUP_ONLY=false
KEEP_DB=false
PHPUNIT_ARGS=()

# Parse arguments
while [[ $# -gt 0 ]]; do
    case "$1" in
        --cleanup-only) CLEANUP_ONLY=true; shift ;;
        --keep-db)      KEEP_DB=true; shift ;;
        -*)             PHPUNIT_ARGS+=("$1"); shift ;;
        *)              PHPUNIT_ARGS+=("$1"); shift ;;
    esac
done

# ── Functions ────────────────────────────────────────────────────────────────

cleanup() {
    local RC=$?

    log "Cleaning up ..."

    # Stop PHP server
    if [[ -n "${PHP_SERVER1_PID:-}" ]] && kill -0 "$PHP_SERVER1_PID" 2>/dev/null; then
        log "Stopping PHP server 1 (pid $PHP_SERVER1_PID) ..."
        kill "$PHP_SERVER1_PID" 2>/dev/null || true
        wait "$PHP_SERVER1_PID" 2>/dev/null || true
    fi

    if [[ -n "${PHP_SERVER2_PID:-}" ]] && kill -0 "$PHP_SERVER2_PID" 2>/dev/null; then
        log "Stopping PHP server 2 (pid $PHP_SERVER2_PID) ..."
        kill "$PHP_SERVER2_PID" 2>/dev/null || true
        wait "$PHP_SERVER2_PID" 2>/dev/null || true
    fi

    # Stop PostgreSQL
    if [[ -n "${PG_HELPER:-}" ]]; then
        "$PG_HELPER" stop 2>/dev/null || true
        if [[ "$KEEP_DB" != "true" ]]; then
            "$PG_HELPER" cleanup 2>/dev/null || true
        fi
    fi

    # if [ $RC != 0 ]; then
    #   cat "$PROJECT_ROOT/tests_integration/php_server1.log"
    #   cat "$PROJECT_ROOT/tests_integration/php_server2.log"
    # fi

    rm -f -- "$PROJECT_ROOT/tests_integration/php_server1.log"
    rm -f -- "$PROJECT_ROOT/tests_integration/php_server2.log"
}

# ── Main ─────────────────────────────────────────────────────────────────────

# Register cleanup trap
trap cleanup EXIT INT TERM

# Cleanup-only mode
if [[ "$CLEANUP_ONLY" == "true" ]]; then
    log "Cleanup-only mode."
    exit 0
fi

# Ensure we're in the project root
cd "$PROJECT_ROOT"

# Make sure pg_helper is executable
PG_HELPER="$SCRIPT_DIR/pg_helper.sh"

log "═══════════════════════════════════════════════════════"
log "  tt-rss Integration Test Runner"
log "═══════════════════════════════════════════════════════"
log "PHP: $PHP_BIN"
log "PG Port: $PG_PORT"
log "PHP Server Port: $PHP_PORT1 / $PHP_PORT2"
log "PG Data Dir: ${PG_DATA_DIR:-<auto>}"
log "───────────────────────────────────────────────────────"

# Step 1: Initialize PostgreSQL
log "Step 1/5: Initializing PostgreSQL ..."
"$PG_HELPER" init
"$PG_HELPER" start

# Step 2: Create database user and database
log "Step 2/5: Creating database user and database ..."
"$PG_HELPER" create-user
"$PG_HELPER" create-db

# Step 3: Run schema migrations
log "Step 3/5: Running schema migrations ..."

export TTRSS_DB_HOST=localhost
export TTRSS_DB_PORT=${PG_PORT}
export TTRSS_DB_NAME="${PG_DB}"
export TTRSS_DB_USER="${PG_USER}"
export TTRSS_DB_PASS="${PG_PASS}"
export TTRSS_DB_SSLMODE=disable
export TTRSS_SINGLE_USER_MODE=1
export OTEL_PHP_AUTOLOAD_ENABLED=false
export OTEL_TRACES_EXPORTER=none
export OTEL_METRICS_EXPORTER=none
export OTEL_LOGS_EXPORTER=none
export OTEL_PHP_DISABLED_INSTRUMENTATIONS=pdo,guzzle,psr18,slim,io
# we have to set it here so it's also available to code running in PHP development server
export IS_INTEGRATION_TESTING=true
export XDEBUG_MODE=coverage

$PHP_BIN ./update.php --update-schema=force-yes
$PHP_BIN ./update.php --user-enable-api admin:true

# Step 3b: Seed synthetic test data
# log "Step 3b/5: Seeding test data ..."
# psql -h localhost -p "$PG_PORT" -U "$PG_USER" -d "$PG_DB" -f "$SCRIPT_DIR/seed.sql"

# Step 4: Start PHP development server
log "Step 4/5: Starting PHP development servers, ports: $PHP_PORT1 / $PHP_PORT2..."

# Start PHP server in background, redirect its output
nohup "$PHP_BIN" -S "0.0.0.0:$PHP_PORT1" -t "$PROJECT_ROOT" \
    > "$PROJECT_ROOT/tests_integration/php_server1.log" 2>&1 &
PHP_SERVER1_PID=$!

# Since it is single-threaded we need to run another one for mock outgoing requests
nohup "$PHP_BIN" -S "0.0.0.0:$PHP_PORT2" -t "$PROJECT_ROOT" \
    > "$PROJECT_ROOT/tests_integration/php_server2.log" 2>&1 &
PHP_SERVER2_PID=$!

# Wait for PHP server to be ready
log "Waiting for PHP server 1 to start ..."
for i in $(seq 1 30); do
    if curl -fs "http://localhost:$PHP_PORT1/api/" >/dev/null 2>&1; then
        log "PHP server is ready."
        break
    fi
    if (( i == 30 )); then
        log "PHP server did not start in time. Logs:"
        cat "$PROJECT_ROOT/tests_integration/php_server1.log" >&2
        exit 1
    fi
    sleep 0.5
done

log "Waiting for PHP server 2 to start ..."
for i in $(seq 1 30); do
    if curl -fs "http://localhost:$PHP_PORT2/api/" >/dev/null 2>&1; then
        log "PHP server is ready."
        break
    fi
    if (( i == 30 )); then
        log "PHP server did not start in time. Logs:"
        cat "$PROJECT_ROOT/tests_integration/php_server2.log" >&2
        exit 1
    fi
    sleep 0.5
done

# Step 5: Run PHPUnit
log "Step 5/5: Running PHPUnit integration tests ..."

# Export env vars for tests
export API_URL="http://localhost:$PHP_PORT1/api/index.php"
export APP_URL="http://localhost:$PHP_PORT2"

# Run PHPUnit with the integration testsuite
"$PHP_BIN" -d zend_extension=xdebug vendor/bin/phpunit \
    --configuration "$PROJECT_ROOT/phpunit.integration.xml" \
    "${PHPUNIT_ARGS[@]+"${PHPUNIT_ARGS[@]}"}"
