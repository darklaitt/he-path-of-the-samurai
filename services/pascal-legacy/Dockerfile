FROM debian:12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    fp-compiler \
    postgresql-client \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/legacy

# Copy source files
COPY legacy.pas ./
COPY run.sh /usr/local/bin/run_legacy.sh

# Make script executable and ensure proper line endings
RUN chmod +x /usr/local/bin/run_legacy.sh

# Create CSV output directory
RUN mkdir -p /data/csv

# Use exec form to ensure proper signal handling
CMD ["/usr/local/bin/run_legacy.sh"]
