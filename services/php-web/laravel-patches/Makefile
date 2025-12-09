.PHONY: help test test-unit test-feature test-integration test-coverage test-watch test-filter clean

# Colors
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[1;33m
NC := \033[0m # No Color

help:
	@echo "$(BLUE)Laravel Testing Commands$(NC)"
	@echo ""
	@echo "Usage: make [target]"
	@echo ""
	@echo "Testing Targets:"
	@echo "  $(GREEN)test$(NC)                  Run all tests (default)"
	@echo "  $(GREEN)test-unit$(NC)            Run unit tests only"
	@echo "  $(GREEN)test-feature$(NC)         Run feature tests only"
	@echo "  $(GREEN)test-integration$(NC)     Run integration tests only"
	@echo "  $(GREEN)test-coverage$(NC)        Run tests with coverage report"
	@echo "  $(GREEN)test-watch$(NC)           Run tests in watch mode"
	@echo "  $(GREEN)test-filter$(NC) pattern  Run tests matching pattern"
	@echo "  $(GREEN)clean$(NC)                Remove coverage reports"
	@echo ""
	@echo "Examples:"
	@echo "  make test"
	@echo "  make test-unit"
	@echo "  make test-coverage"
	@echo "  make test-filter PATTERN=IssService"

# Run all tests
test:
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "$(BLUE)Running All Tests$(NC)"
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	php ./vendor/bin/phpunit --testdox

# Run unit tests
test-unit:
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "$(BLUE)Running Unit Tests$(NC)"
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	php ./vendor/bin/phpunit tests/Unit --testdox

# Run feature tests
test-feature:
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "$(BLUE)Running Feature Tests$(NC)"
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	php ./vendor/bin/phpunit tests/Feature --testdox

# Run integration tests
test-integration:
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "$(BLUE)Running Integration Tests$(NC)"
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	php ./vendor/bin/phpunit tests/Integration --testdox

# Run tests with coverage
test-coverage:
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "$(BLUE)Running Tests with Coverage Report$(NC)"
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	php ./vendor/bin/phpunit \
		--coverage-html=coverage \
		--coverage-text \
		--coverage-clover=coverage.xml \
		--testdox
	@echo ""
	@echo "$(GREEN)✓ Coverage report generated at: coverage/index.html$(NC)"

# Run tests in watch mode
test-watch:
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "$(BLUE)Running Tests in Watch Mode$(NC)"
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "Watching for changes... (Press Ctrl+C to exit)"
	@find app tests -name "*.php" | entr php ./vendor/bin/phpunit --testdox

# Run tests matching pattern
test-filter:
	@if [ -z "$(PATTERN)" ]; then \
		echo "$(YELLOW)Error: PATTERN not provided$(NC)"; \
		echo "Usage: make test-filter PATTERN=testName"; \
		exit 1; \
	fi
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "$(BLUE)Running Tests Matching: $(PATTERN)$(NC)"
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	php ./vendor/bin/phpunit --filter "$(PATTERN)" --testdox

# Clean coverage reports
clean:
	@echo "$(BLUE)Cleaning coverage reports...$(NC)"
	@rm -rf coverage
	@rm -f coverage.xml
	@echo "$(GREEN)✓ Done$(NC)"

# Show test statistics
stats:
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "$(BLUE)Test Statistics$(NC)"
	@echo "$(BLUE)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "Unit Tests:"
	@find tests/Unit -name "*Test.php" | wc -l | xargs echo "  Files:"
	@grep -r "function test" tests/Unit | wc -l | xargs echo "  Methods:"
	@echo ""
	@echo "Feature Tests:"
	@find tests/Feature -name "*Test.php" | wc -l | xargs echo "  Files:"
	@grep -r "function test" tests/Feature | wc -l | xargs echo "  Methods:"
	@echo ""
	@echo "Integration Tests:"
	@find tests/Integration -name "*Test.php" | wc -l | xargs echo "  Files:"
	@grep -r "function test" tests/Integration | wc -l | xargs echo "  Methods:"
