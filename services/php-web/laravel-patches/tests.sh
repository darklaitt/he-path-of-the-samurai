#!/usr/bin/env bash

# Laravel Testing Script
# Runs all tests with various options

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TESTS_DIR="${PROJECT_DIR}/tests"
VENDOR_DIR="${PROJECT_DIR}/vendor"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
show_usage() {
    echo "Usage: $0 [OPTION]"
    echo ""
    echo "Options:"
    echo "  all          Run all tests (default)"
    echo "  unit         Run unit tests only"
    echo "  feature      Run feature tests only"
    echo "  integration  Run integration tests only"
    echo "  coverage     Run tests with coverage report"
    echo "  watch        Run tests in watch mode (requires entr)"
    echo "  filter PATTERN  Run tests matching pattern"
    echo "  help         Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 all"
    echo "  $0 unit"
    echo "  $0 coverage"
    echo "  $0 filter testGetLastIss"
}

run_tests() {
    local test_path="$1"
    local description="$2"
    
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Running $description${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    if [ -z "$test_path" ]; then
        "${VENDOR_DIR}/bin/phpunit" --testdox
    else
        "${VENDOR_DIR}/bin/phpunit" "$test_path" --testdox
    fi
}

run_coverage() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Running Tests with Coverage Report${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    "${VENDOR_DIR}/bin/phpunit" \
        --coverage-html="${PROJECT_DIR}/coverage" \
        --coverage-text \
        --coverage-clover="${PROJECT_DIR}/coverage.xml" \
        --testdox
    
    echo ""
    echo -e "${GREEN}✓ Coverage report generated at: coverage/index.html${NC}"
}

run_filter() {
    local pattern="$1"
    
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Running Tests Matching: $pattern${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    "${VENDOR_DIR}/bin/phpunit" --filter "$pattern" --testdox
}

run_watch() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Running Tests in Watch Mode${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    if ! command -v entr &> /dev/null; then
        echo -e "${RED}✗ 'entr' is required for watch mode${NC}"
        echo "Install it with: brew install entr (macOS) or apt-get install entr (Linux)"
        exit 1
    fi
    
    # Watch for changes in app and tests directories
    find "${PROJECT_DIR}/app" "${TESTS_DIR}" -name "*.php" | \
        entr "${VENDOR_DIR}/bin/phpunit" --testdox
}

show_summary() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}Test run completed successfully!${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "Test Breakdown:"
    echo "  Unit Tests:       $("${VENDOR_DIR}/bin/phpunit" tests/Unit --testdox 2>&1 | grep -c "✓" || echo "0") tests"
    echo "  Feature Tests:    $("${VENDOR_DIR}/bin/phpunit" tests/Feature --testdox 2>&1 | grep -c "✓" || echo "0") tests"
    echo "  Integration Tests: $("${VENDOR_DIR}/bin/phpunit" tests/Integration --testdox 2>&1 | grep -c "✓" || echo "0") tests"
    echo ""
}

# Main script
cd "$PROJECT_DIR"

# Check if vendor directory exists
if [ ! -d "$VENDOR_DIR" ]; then
    echo -e "${RED}✗ Vendor directory not found. Run 'composer install' first.${NC}"
    exit 1
fi

# Parse command line argument
COMMAND="${1:-all}"

case "$COMMAND" in
    all)
        run_tests "" "All Tests"
        show_summary
        ;;
    unit)
        run_tests "tests/Unit" "Unit Tests"
        ;;
    feature)
        run_tests "tests/Feature" "Feature Tests"
        ;;
    integration)
        run_tests "tests/Integration" "Integration Tests"
        ;;
    coverage)
        run_coverage
        ;;
    watch)
        run_watch
        ;;
    filter)
        if [ -z "$2" ]; then
            echo -e "${RED}✗ Pattern required for filter command${NC}"
            exit 1
        fi
        run_filter "$2"
        ;;
    help|--help|-h)
        show_usage
        ;;
    *)
        echo -e "${RED}✗ Unknown command: $COMMAND${NC}"
        show_usage
        exit 1
        ;;
esac
