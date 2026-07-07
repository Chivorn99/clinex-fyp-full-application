# Clinex Application — Stress & Load Testing

This directory contains comprehensive load testing tools for the Clinex API using two industry-standard tools:

| Tool | File | Best For |
|------|------|----------|
| **K6** | `k6_load_test.js` | Developer-friendly, CI/CD integration, scripted scenarios |
| **JMeter** | `ClinexLoadTest.jmx` | GUI-based, visual analysis, enterprise reporting |

---

## Prerequisites

Before running tests, ensure:

1. **Laravel API** is running at `http://localhost:8000`
2. **Database** is seeded with test data (`php artisan db:seed`)
3. **Test user** exists with credentials:
   - Email: `admin@clinex.test`
   - Password: `password`

---

## K6 Load Test

### Installation

#### Windows (Chocolatey)
```powershell
choco install k6
```

#### Windows (winget)
```powershell
winget install k6 --source winget
```

#### Windows (MSI Installer)
Download from: https://dl.k6.io/msi/k6-latest-amd64.msi

#### macOS
```bash
brew install k6
```

#### Linux (Debian/Ubuntu)
```bash
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D68
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
  | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6
```

#### Docker
```bash
docker pull grafana/k6
```

### Running K6 Tests

#### Run All Scenarios (Smoke → Load → Stress → Spike)
```powershell
cd backend\tests\Stress
k6 run k6_load_test.js
```

#### Run a Single Scenario
```powershell
# Quick sanity check (1 VU, 1 minute)
k6 run --env SCENARIO=smoke k6_load_test.js

# Normal load test (50 VUs, 5 minutes)
k6 run --env SCENARIO=load k6_load_test.js

# Stress test (100 VUs, 10 minutes)
k6 run --env SCENARIO=stress k6_load_test.js

# Spike test (200 VUs burst)
k6 run --env SCENARIO=spike k6_load_test.js
```

#### Custom Configuration
```powershell
# Override base URL (e.g., staging server)
k6 run --env BASE_URL=https://staging.clinex.app/api k6_load_test.js

# Override test credentials
k6 run --env TEST_EMAIL=user@example.com --env TEST_PASSWORD=secret k6_load_test.js

# Output JSON results for processing
k6 run --out json=results/raw_results.json k6_load_test.js
```

#### Docker Execution
```bash
docker run --rm -i --network host \
  -v $(pwd):/scripts \
  grafana/k6 run /scripts/k6_load_test.js
```

### K6 Output & Reports

After execution, reports are generated in the `results/` directory:
- `clinex_report_<timestamp>.html` — Visual HTML report (open in browser)
- `clinex_results_<timestamp>.json` — Raw JSON data for CI/CD analysis

### K6 Thresholds

| Metric | Threshold | Description |
|--------|-----------|-------------|
| `http_req_duration` | p(95) < 2s | 95th percentile response time |
| `http_req_failed` | rate < 5% | Maximum acceptable failure rate |
| Read endpoints (list) | p(95) < 500ms | Lab reports, patients, batches listing |
| `health_check_duration` | p(95) < 200ms | Health endpoint must be very fast |
| `dashboard_duration` | p(95) < 1s | Dashboard aggregation queries |
| `login_duration` | p(95) < 2s | Authentication response time |

### K6 Scenarios Summary

| Scenario | VUs | Duration | Purpose |
|----------|-----|----------|---------|
| **Smoke** | 1 | 1 min | Verify endpoints work under minimal load |
| **Load** | 50 | 5 min | Simulate typical production traffic |
| **Stress** | 100 | 10 min | Find breaking points under heavy load |
| **Spike** | 200 | ~2.5 min | Test resilience to sudden traffic surges |

---

## JMeter Load Test

### Installation

#### Windows
1. Download Apache JMeter from: https://jmeter.apache.org/download_jmeter.cgi
2. Extract the ZIP file (e.g., `C:\apache-jmeter-5.6.3`)
3. Ensure Java 8+ is installed: `java -version`
4. Add JMeter's `bin` directory to your PATH

#### macOS
```bash
brew install jmeter
```

#### Linux
```bash
# Download and extract
wget https://dlcdn.apache.org//jmeter/binaries/apache-jmeter-5.6.3.tgz
tar -xzf apache-jmeter-5.6.3.tgz
sudo mv apache-jmeter-5.6.3 /opt/jmeter

# Add to PATH
echo 'export PATH=$PATH:/opt/jmeter/bin' >> ~/.bashrc
source ~/.bashrc
```

### Running JMeter Tests

#### GUI Mode (for debugging & building tests)
```powershell
jmeter -t ClinexLoadTest.jmx
```

> **⚠️ Important:** GUI mode is for test development only. Always use CLI mode for actual load tests to avoid performance overhead.

#### CLI Mode (recommended for actual tests)
```powershell
# Default settings (50 users, 60s ramp-up, 5 loops)
jmeter -n -t ClinexLoadTest.jmx -l results/jmeter_results.jtl -e -o results/jmeter_html_report

# Custom thread count and ramp-up
jmeter -n -t ClinexLoadTest.jmx -JTHREADS=100 -JRAMP_UP=120 -JLOOP_COUNT=10 -l results/results.jtl

# Override server settings
jmeter -n -t ClinexLoadTest.jmx -JBASE_HOST=staging.clinex.app -JBASE_PORT=443 -JPROTOCOL=https
```

#### CLI Flags Explained

| Flag | Description |
|------|-------------|
| `-n` | Non-GUI (headless) mode |
| `-t` | Test plan file path |
| `-l` | Log file for results (JTL format) |
| `-e` | Generate HTML report after test |
| `-o` | Output directory for HTML report |
| `-J<VAR>=<VALUE>` | Override a user-defined variable |

### JMeter Configurable Variables

Override any of these from the command line with `-J<NAME>=<VALUE>`:

| Variable | Default | Description |
|----------|---------|-------------|
| `BASE_HOST` | `localhost` | API server hostname |
| `BASE_PORT` | `8000` | API server port |
| `PROTOCOL` | `http` | Protocol (http/https) |
| `TEST_EMAIL` | `admin@clinex.test` | Login email |
| `TEST_PASSWORD` | `password` | Login password |
| `THREADS` | `50` | Number of concurrent users |
| `RAMP_UP` | `60` | Ramp-up period in seconds |
| `LOOP_COUNT` | `5` | Number of iterations per user |

### JMeter Output

- **GUI Mode:** View Results Tree, Summary Report, Aggregate Report (live in the GUI)
- **CLI Mode:**
  - `results/jmeter_results.jtl` — Raw JTL log file
  - `results/jmeter_summary.csv` — Summary statistics
  - `results/jmeter_aggregate.csv` — Aggregate statistics with percentiles
  - `results/jmeter_html_report/` — Full HTML dashboard (open `index.html`)

---

## Test Flow

Both K6 and JMeter follow the same user journey:

```
1. GET  /api/health              (no auth — sanity check)
2. POST /api/login               (authenticate, extract token)
3. GET  /api/lab-reports          (list lab reports)
4. GET  /api/lab-reports/{id}     (view single report)
5. GET  /api/lab-reports/{id}/test-results
6. POST /api/lab-reports/{id}/verify
7. GET  /api/lab-reports/export/xlsx?report_id=X
8. GET  /api/lab-reports/export/bulk-xlsx
9. GET  /api/batches              (list batches)
10. GET  /api/batches/{id}
11. GET  /api/batches/{id}/status
12. POST /api/batches/{id}/process
13. GET  /api/patients             (list patients)
14. GET  /api/patients/search?q=test
15. GET  /api/patients/{id}
16. GET  /api/analytics/dashboard?days=30
17. GET  /api/admin/dashboard
18. GET  /api/admin/users
19. GET  /api/admin/system-health
20. POST /api/logout
```

---

## Interpreting Results

### Key Metrics to Watch

| Metric | Good | Warning | Critical |
|--------|------|---------|----------|
| **Avg Response Time** | < 200ms | 200–500ms | > 500ms |
| **p95 Response Time** | < 500ms | 500ms–2s | > 2s |
| **p99 Response Time** | < 2s | 2–5s | > 5s |
| **Error Rate** | < 1% | 1–5% | > 5% |
| **Throughput** | Stable | Fluctuating | Declining |

### Common Issues & Solutions

| Symptom | Likely Cause | Solution |
|---------|-------------|----------|
| High response times on all endpoints | Database bottleneck | Add indexes, optimize queries |
| Auth endpoints slow | Token generation overhead | Cache/pool connections |
| Export endpoints timeout | Large dataset processing | Add pagination, async processing |
| Errors spike under load | Connection pool exhaustion | Increase pool size, add queuing |
| 429 Too Many Requests | Rate limiting active | Adjust rate limiter for load tests |

---

## CI/CD Integration

### GitHub Actions Example (K6)
```yaml
- name: Run K6 Smoke Test
  uses: grafana/k6-action@v0.3.1
  with:
    filename: backend/tests/Stress/k6_load_test.js
  env:
    SCENARIO: smoke
    BASE_URL: http://localhost:8000/api
```

### GitLab CI Example (K6)
```yaml
load_test:
  image: grafana/k6
  script:
    - k6 run --env SCENARIO=load backend/tests/Stress/k6_load_test.js
  artifacts:
    paths:
      - backend/tests/Stress/results/
```

---

## Directory Structure

```
tests/Stress/
├── k6_load_test.js          # K6 test script
├── ClinexLoadTest.jmx       # JMeter test plan
├── README.md                # This file
└── results/                 # Generated reports (gitignored)
    ├── clinex_report_*.html  # K6 HTML reports
    ├── clinex_results_*.json # K6 JSON results
    ├── jmeter_summary.csv    # JMeter summary
    ├── jmeter_aggregate.csv  # JMeter aggregate
    └── jmeter_html_report/   # JMeter HTML dashboard
```

---

## Tips

1. **Always run load tests against a test/staging environment**, never production
2. **Seed the database** before testing — empty databases give unrealistic results
3. **Start with smoke tests** to verify everything works before ramping up
4. **Monitor server resources** (CPU, RAM, disk I/O) during tests alongside the test results
5. **Disable rate limiting** on the test environment or whitelist the test runner's IP
6. **Run tests multiple times** and compare results — single runs can be misleading
7. **Use the results/ directory** for output — it's gitignored by convention
