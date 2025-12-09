#!/usr/bin/env bash
set -euo pipefail

# Graceful shutdown on signals
cleanup() {
    echo "[pascal] received shutdown signal, cleaning up..."
    exit 0
}
trap cleanup SIGTERM SIGINT

# Configuration validation
echo "[pascal] validating environment variables..."
: "${PGHOST:?PGHOST not set}"
: "${PGPORT:?PGPORT not set}"
: "${PGUSER:?PGUSER not set}"
: "${PGDATABASE:?PGDATABASE not set}"
: "${GEN_PERIOD_SEC:=300}"
: "${CSV_OUT_DIR:=/data/csv}"

echo "[pascal] PGHOST=$PGHOST PGPORT=$PGPORT PGUSER=$PGUSER PGDATABASE=$PGDATABASE"
echo "[pascal] GEN_PERIOD_SEC=$GEN_PERIOD_SEC CSV_OUT_DIR=$CSV_OUT_DIR"

# Ensure CSV output directory exists
mkdir -p "$CSV_OUT_DIR"
if [ ! -d "$CSV_OUT_DIR" ]; then
    echo "[pascal] ERROR: cannot create CSV_OUT_DIR=$CSV_OUT_DIR"
    exit 1
fi

# Compile step
echo "[pascal] compiling legacy.pas..."
if ! fpc -O2 -S2 legacy.pas 2>&1; then
    echo "[pascal] ERROR: compilation failed"
    exit 1
fi

# Verify executable was created
if [ ! -f ./legacy ]; then
    echo "[pascal] ERROR: compilation succeeded but binary not found"
    exit 1
fi

echo "[pascal] compilation successful"
echo "[pascal] starting CSV generator daemon (period: ${GEN_PERIOD_SEC}s)..."
exec ./legacy
