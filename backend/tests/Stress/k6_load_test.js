/**
 * ============================================================================
 * Clinex Application — K6 Load & Stress Test Suite
 * ============================================================================
 *
 * This script tests the Clinex Laravel API under various load profiles:
 *   • Smoke   — 1 VU   for 1 minute   (sanity check)
 *   • Load    — 50 VUs for 5 minutes   (normal traffic)
 *   • Stress  — 100 VUs for 10 minutes (peak traffic)
 *   • Spike   — 200 VUs burst          (sudden traffic surge)
 *
 * Usage:
 *   # Run all scenarios (default)
 *   k6 run k6_load_test.js
 *
 *   # Run a single scenario
 *   k6 run --env SCENARIO=smoke k6_load_test.js
 *   k6 run --env SCENARIO=load  k6_load_test.js
 *   k6 run --env SCENARIO=stress k6_load_test.js
 *   k6 run --env SCENARIO=spike  k6_load_test.js
 *
 *   # Override base URL
 *   k6 run --env BASE_URL=https://staging.clinex.app/api k6_load_test.js
 *
 *   # Generate HTML report (requires k6-reporter extension or --out json)
 *   k6 run --out json=results.json k6_load_test.js
 *
 * Environment Variables:
 *   BASE_URL          — API base URL         (default: http://localhost:8000/api)
 *   FRONTEND_URL      — Frontend base URL    (default: http://localhost:3000)
 *   TEST_EMAIL        — Login email           (default: admin@clinex.test)
 *   TEST_PASSWORD     — Login password        (default: password)
 *   SCENARIO          — Run a single scenario (smoke|load|stress|spike)
 *
 * ============================================================================
 */

import http from 'k6/http';
import { check, group, sleep, fail } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { htmlReport } from 'https://raw.githubusercontent.com/benc-uk/k6-reporter/main/dist/bundle.js';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.1/index.js';

// ============================================================================
// Custom Metrics
// ============================================================================

// Track specific endpoint performance with custom trends
const loginDuration       = new Trend('login_duration', true);
const labReportsDuration  = new Trend('lab_reports_list_duration', true);
const patientsDuration    = new Trend('patients_list_duration', true);
const batchesDuration     = new Trend('batches_list_duration', true);
const dashboardDuration   = new Trend('dashboard_duration', true);
const healthDuration      = new Trend('health_check_duration', true);

// Error tracking
const errorRate  = new Rate('errors');
const errorCount = new Counter('error_count');

// Auth tracking
const authFailures = new Counter('auth_failures');

// ============================================================================
// Configuration
// ============================================================================

const BASE_URL     = __ENV.BASE_URL     || 'http://localhost:8000/api';
const FRONTEND_URL = __ENV.FRONTEND_URL || 'http://localhost:3000';
const TEST_EMAIL   = __ENV.TEST_EMAIL   || 'admin@clinex.test';
const TEST_PASSWORD = __ENV.TEST_PASSWORD || 'password';

// Think time range (seconds) — simulates realistic user pauses
const THINK_TIME_MIN = 1;
const THINK_TIME_MAX = 3;

/**
 * Helper: random sleep between min and max seconds to simulate user think time
 */
function thinkTime() {
  sleep(Math.random() * (THINK_TIME_MAX - THINK_TIME_MIN) + THINK_TIME_MIN);
}

/**
 * Helper: pick a random element from an array
 */
function randomItem(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}

// ============================================================================
// Scenario Definitions
// ============================================================================

// Determine which scenarios to run based on the SCENARIO env var
function buildScenarios() {
  const selected = (__ENV.SCENARIO || '').toLowerCase();

  const allScenarios = {
    // Smoke Test — minimal load, sanity check that endpoints respond
    smoke: {
      executor: 'constant-vus',
      vus: 1,
      duration: '1m',
      tags: { test_type: 'smoke' },
      exec: 'mainFlow',
    },

    // Load Test — simulate typical production traffic
    load: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '1m',  target: 25 },   // ramp up to 25 users
        { duration: '3m',  target: 50 },   // hold at 50 users
        { duration: '1m',  target: 0 },    // ramp down
      ],
      tags: { test_type: 'load' },
      exec: 'mainFlow',
    },

    // Stress Test — push beyond expected capacity
    stress: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m',  target: 50 },   // ramp to normal
        { duration: '3m',  target: 100 },  // push to stress level
        { duration: '3m',  target: 100 },  // hold at stress
        { duration: '2m',  target: 0 },    // ramp down
      ],
      tags: { test_type: 'stress' },
      exec: 'mainFlow',
    },

    // Spike Test — sudden burst of traffic
    spike: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '10s', target: 5 },    // baseline
        { duration: '20s', target: 200 },  // spike!
        { duration: '1m',  target: 200 },  // hold spike
        { duration: '30s', target: 5 },    // recover
        { duration: '30s', target: 0 },    // ramp down
      ],
      tags: { test_type: 'spike' },
      exec: 'mainFlow',
    },
  };

  // If a specific scenario is requested, return only that one
  if (selected && allScenarios[selected]) {
    return { [selected]: allScenarios[selected] };
  }

  // Otherwise run all scenarios sequentially
  // Add gracefulStop and startTime offsets so they don't overlap
  allScenarios.smoke.gracefulStop  = '30s';
  allScenarios.load.startTime     = '2m';
  allScenarios.load.gracefulStop   = '30s';
  allScenarios.stress.startTime   = '8m';
  allScenarios.stress.gracefulStop = '30s';
  allScenarios.spike.startTime    = '19m';
  allScenarios.spike.gracefulStop  = '30s';

  return allScenarios;
}

// ============================================================================
// K6 Options — Thresholds, Scenarios, Output
// ============================================================================

export const options = {
  scenarios: buildScenarios(),

  thresholds: {
    // Global HTTP thresholds
    'http_req_duration':          ['p(95)<2000', 'p(99)<5000'],  // 95th < 2s, 99th < 5s
    'http_req_failed':            ['rate<0.05'],                  // < 5% failure rate

    // Read endpoints — should be fast (p95 < 500ms)
    'lab_reports_list_duration':  ['p(95)<500'],
    'patients_list_duration':     ['p(95)<500'],
    'batches_list_duration':      ['p(95)<500'],
    'health_check_duration':      ['p(95)<200'],   // Health check should be very fast
    'dashboard_duration':         ['p(95)<1000'],   // Dashboard aggregation allowed more time

    // Write/auth endpoints — allowed more time (p95 < 2s)
    'login_duration':             ['p(95)<2000'],

    // Custom error metrics
    'errors':                     ['rate<0.1'],     // Overall error rate < 10%
    'auth_failures':              ['count<10'],     // Fewer than 10 auth failures total
  },

  // Discard response bodies by default (save memory at high VU counts)
  discardResponseBodies: false,

  // DNS caching
  dns: {
    ttl: '5m',
    select: 'roundRobin',
  },
};

// ============================================================================
// Setup — Runs once before the test to prepare shared data
// ============================================================================

export function setup() {
  console.log(`🔧 Clinex K6 Test Suite`);
  console.log(`   API Base URL:      ${BASE_URL}`);
  console.log(`   Frontend URL:      ${FRONTEND_URL}`);
  console.log(`   Test User:         ${TEST_EMAIL}`);
  console.log(`   Scenarios:         ${Object.keys(options.scenarios).join(', ')}`);

  // Verify the API is reachable via health check
  const healthRes = http.get(`${BASE_URL}/health`, {
    tags: { name: 'setup_health_check' },
  });

  if (healthRes.status !== 200) {
    console.error(`❌ Health check failed (status ${healthRes.status}). Is the API running?`);
    console.error(`   Response: ${healthRes.body}`);
    // Don't fail — the test will reveal the issues
  } else {
    console.log(`✅ API health check passed`);
  }

  return {
    baseUrl: BASE_URL,
    frontendUrl: FRONTEND_URL,
  };
}

// ============================================================================
// Authentication Helper
// ============================================================================

/**
 * Authenticate and return { token, headers } or null on failure.
 * Each VU calls this once at the start of its iteration.
 */
function authenticate() {
  const payload = JSON.stringify({
    email: TEST_EMAIL,
    password: TEST_PASSWORD,
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    tags: { name: 'POST /api/login', endpoint: 'login' },
  };

  const res = http.post(`${BASE_URL}/login`, payload, params);
  loginDuration.add(res.timings.duration);

  const success = check(res, {
    'login: status is 200': (r) => r.status === 200,
    'login: response has token': (r) => {
      try {
        const body = JSON.parse(r.body);
        return !!(body.token || body.access_token || (body.data && body.data.token));
      } catch {
        return false;
      }
    },
  });

  if (!success) {
    authFailures.add(1);
    errorRate.add(1);
    errorCount.add(1);
    console.error(`❌ Login failed for ${TEST_EMAIL} — status: ${res.status}`);
    return null;
  }

  errorRate.add(0);

  // Extract token — handle multiple response shapes
  let token;
  try {
    const body = JSON.parse(res.body);
    token = body.token || body.access_token || (body.data && body.data.token);
  } catch {
    authFailures.add(1);
    return null;
  }

  return {
    token,
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  };
}

// ============================================================================
// Endpoint Test Functions
// ============================================================================

/**
 * Health check — no auth required
 */
function testHealthCheck() {
  group('Health Check', () => {
    const res = http.get(`${BASE_URL}/health`, {
      tags: { name: 'GET /api/health', endpoint: 'health' },
    });

    healthDuration.add(res.timings.duration);

    const ok = check(res, {
      'health: status is 200': (r) => r.status === 200,
    });

    errorRate.add(!ok);
    if (!ok) errorCount.add(1);
  });
}

/**
 * Lab Reports — list, detail, test results, verify, export
 */
function testLabReports(authHeaders) {
  group('Lab Reports', () => {
    // List all lab reports
    const listRes = http.get(`${BASE_URL}/lab-reports`, {
      headers: authHeaders,
      tags: { name: 'GET /api/lab-reports', endpoint: 'lab_reports_list' },
    });

    labReportsDuration.add(listRes.timings.duration);

    const listOk = check(listRes, {
      'lab-reports list: status is 200': (r) => r.status === 200,
      'lab-reports list: returns array or data': (r) => {
        try {
          const body = JSON.parse(r.body);
          return Array.isArray(body) || Array.isArray(body.data);
        } catch {
          return false;
        }
      },
    });

    errorRate.add(!listOk);
    if (!listOk) errorCount.add(1);

    thinkTime();

    // Get a specific report if available
    let reportId = null;
    try {
      const body = JSON.parse(listRes.body);
      const reports = Array.isArray(body) ? body : (body.data || []);
      if (reports.length > 0) {
        reportId = randomItem(reports).id;
      }
    } catch { /* ignore */ }

    if (reportId) {
      // Get single report
      const detailRes = http.get(`${BASE_URL}/lab-reports/${reportId}`, {
        headers: authHeaders,
        tags: { name: 'GET /api/lab-reports/{id}', endpoint: 'lab_report_detail' },
      });

      check(detailRes, {
        'lab-report detail: status is 200': (r) => r.status === 200,
      });

      thinkTime();

      // Get test results for the report
      const resultsRes = http.get(`${BASE_URL}/lab-reports/${reportId}/test-results`, {
        headers: authHeaders,
        tags: { name: 'GET /api/lab-reports/{id}/test-results', endpoint: 'lab_report_results' },
      });

      check(resultsRes, {
        'lab-report test-results: status is 200 or 404': (r) =>
          r.status === 200 || r.status === 404,
      });

      thinkTime();

      // Verify report (POST — write operation)
      const verifyRes = http.post(
        `${BASE_URL}/lab-reports/${reportId}/verify`,
        null,
        {
          headers: authHeaders,
          tags: { name: 'POST /api/lab-reports/{id}/verify', endpoint: 'lab_report_verify' },
        }
      );

      check(verifyRes, {
        'lab-report verify: status is 200 or 422 or 403': (r) =>
          [200, 422, 403].includes(r.status),
      });

      thinkTime();

      // Export single report as XLSX
      const exportRes = http.get(`${BASE_URL}/lab-reports/export/xlsx?report_id=${reportId}`, {
        headers: authHeaders,
        tags: { name: 'GET /api/lab-reports/export/xlsx', endpoint: 'lab_report_export' },
        responseType: 'binary',
      });

      check(exportRes, {
        'lab-report export: status is 200 or 404': (r) =>
          r.status === 200 || r.status === 404,
      });
    }

    thinkTime();

    // Bulk export
    const bulkExportRes = http.get(`${BASE_URL}/lab-reports/export/bulk-xlsx`, {
      headers: authHeaders,
      tags: { name: 'GET /api/lab-reports/export/bulk-xlsx', endpoint: 'lab_report_bulk_export' },
      responseType: 'binary',
    });

    check(bulkExportRes, {
      'lab-report bulk export: status is 200 or 404 or 422': (r) =>
        [200, 404, 422].includes(r.status),
    });
  });
}

/**
 * Batches — list, detail, status, process
 */
function testBatches(authHeaders) {
  group('Batches', () => {
    // List batches
    const listRes = http.get(`${BASE_URL}/batches`, {
      headers: authHeaders,
      tags: { name: 'GET /api/batches', endpoint: 'batches_list' },
    });

    batchesDuration.add(listRes.timings.duration);

    const listOk = check(listRes, {
      'batches list: status is 200': (r) => r.status === 200,
    });

    errorRate.add(!listOk);
    if (!listOk) errorCount.add(1);

    thinkTime();

    // Get a specific batch if available
    let batchId = null;
    try {
      const body = JSON.parse(listRes.body);
      const batches = Array.isArray(body) ? body : (body.data || []);
      if (batches.length > 0) {
        batchId = randomItem(batches).id;
      }
    } catch { /* ignore */ }

    if (batchId) {
      // Get batch detail
      const detailRes = http.get(`${BASE_URL}/batches/${batchId}`, {
        headers: authHeaders,
        tags: { name: 'GET /api/batches/{id}', endpoint: 'batch_detail' },
      });

      check(detailRes, {
        'batch detail: status is 200': (r) => r.status === 200,
      });

      thinkTime();

      // Get batch processing status
      const statusRes = http.get(`${BASE_URL}/batches/${batchId}/status`, {
        headers: authHeaders,
        tags: { name: 'GET /api/batches/{id}/status', endpoint: 'batch_status' },
      });

      check(statusRes, {
        'batch status: status is 200': (r) => r.status === 200,
      });

      thinkTime();

      // Process batch (POST — write operation)
      const processRes = http.post(
        `${BASE_URL}/batches/${batchId}/process`,
        null,
        {
          headers: authHeaders,
          tags: { name: 'POST /api/batches/{id}/process', endpoint: 'batch_process' },
        }
      );

      check(processRes, {
        'batch process: status is 200 or 422 or 409': (r) =>
          [200, 422, 409].includes(r.status),
      });
    }
  });
}

/**
 * Patients — list, search, detail
 */
function testPatients(authHeaders) {
  group('Patients', () => {
    // List patients
    const listRes = http.get(`${BASE_URL}/patients`, {
      headers: authHeaders,
      tags: { name: 'GET /api/patients', endpoint: 'patients_list' },
    });

    patientsDuration.add(listRes.timings.duration);

    const listOk = check(listRes, {
      'patients list: status is 200': (r) => r.status === 200,
    });

    errorRate.add(!listOk);
    if (!listOk) errorCount.add(1);

    thinkTime();

    // Search patients with various terms
    const searchTerms = ['John', 'Smith', 'test', 'A', 'patient'];
    const searchTerm = randomItem(searchTerms);

    const searchRes = http.get(`${BASE_URL}/patients/search?q=${encodeURIComponent(searchTerm)}`, {
      headers: authHeaders,
      tags: { name: 'GET /api/patients/search', endpoint: 'patients_search' },
    });

    check(searchRes, {
      'patients search: status is 200': (r) => r.status === 200,
    });

    thinkTime();

    // Get a specific patient if available
    let patientId = null;
    try {
      const body = JSON.parse(listRes.body);
      const patients = Array.isArray(body) ? body : (body.data || []);
      if (patients.length > 0) {
        patientId = randomItem(patients).id;
      }
    } catch { /* ignore */ }

    if (patientId) {
      const detailRes = http.get(`${BASE_URL}/patients/${patientId}`, {
        headers: authHeaders,
        tags: { name: 'GET /api/patients/{id}', endpoint: 'patient_detail' },
      });

      check(detailRes, {
        'patient detail: status is 200': (r) => r.status === 200,
      });
    }
  });
}

/**
 * Analytics Dashboard
 */
function testAnalytics(authHeaders) {
  group('Analytics', () => {
    const dayOptions = [7, 14, 30, 60, 90];
    const days = randomItem(dayOptions);

    const res = http.get(`${BASE_URL}/analytics/dashboard?days=${days}`, {
      headers: authHeaders,
      tags: { name: 'GET /api/analytics/dashboard', endpoint: 'analytics_dashboard' },
    });

    dashboardDuration.add(res.timings.duration);

    const ok = check(res, {
      'analytics dashboard: status is 200 or 403': (r) =>
        r.status === 200 || r.status === 403,
    });

    errorRate.add(!ok);
    if (!ok) errorCount.add(1);
  });
}

/**
 * Admin Endpoints — dashboard, users, system health
 * These require specific permissions — 403 is an acceptable response
 */
function testAdmin(authHeaders) {
  group('Admin', () => {
    // Admin dashboard
    const dashRes = http.get(`${BASE_URL}/admin/dashboard`, {
      headers: authHeaders,
      tags: { name: 'GET /api/admin/dashboard', endpoint: 'admin_dashboard' },
    });

    check(dashRes, {
      'admin dashboard: status is 200 or 403': (r) =>
        r.status === 200 || r.status === 403,
    });

    thinkTime();

    // Admin users list
    const usersRes = http.get(`${BASE_URL}/admin/users`, {
      headers: authHeaders,
      tags: { name: 'GET /api/admin/users', endpoint: 'admin_users' },
    });

    check(usersRes, {
      'admin users: status is 200 or 403': (r) =>
        r.status === 200 || r.status === 403,
    });

    thinkTime();

    // System health
    const healthRes = http.get(`${BASE_URL}/admin/system-health`, {
      headers: authHeaders,
      tags: { name: 'GET /api/admin/system-health', endpoint: 'admin_system_health' },
    });

    check(healthRes, {
      'admin system-health: status is 200 or 403': (r) =>
        r.status === 200 || r.status === 403,
    });
  });
}

/**
 * Logout — end the session
 */
function testLogout(authHeaders) {
  group('Logout', () => {
    const res = http.post(`${BASE_URL}/logout`, null, {
      headers: authHeaders,
      tags: { name: 'POST /api/logout', endpoint: 'logout' },
    });

    check(res, {
      'logout: status is 200 or 204': (r) =>
        r.status === 200 || r.status === 204,
    });
  });
}

// ============================================================================
// Main Test Flow — Executed by each VU per iteration
// ============================================================================

/**
 * Each virtual user executes this flow:
 *   1. Health check (no auth)
 *   2. Login to get auth token
 *   3. Browse lab reports
 *   4. Browse batches
 *   5. Browse patients
 *   6. Check analytics dashboard
 *   7. Try admin endpoints
 *   8. Logout
 */
export function mainFlow(data) {
  // ── Step 1: Health check (unauthenticated) ──
  testHealthCheck();
  thinkTime();

  // ── Step 2: Authenticate ──
  const auth = authenticate();
  if (!auth) {
    console.warn(`⚠️ VU ${__VU} iteration ${__ITER}: skipping — auth failed`);
    sleep(5); // back off before retrying
    return;
  }

  thinkTime();

  // ── Step 3: Lab Reports CRUD ──
  testLabReports(auth.headers);
  thinkTime();

  // ── Step 4: Batches ──
  testBatches(auth.headers);
  thinkTime();

  // ── Step 5: Patients ──
  testPatients(auth.headers);
  thinkTime();

  // ── Step 6: Analytics ──
  testAnalytics(auth.headers);
  thinkTime();

  // ── Step 7: Admin ──
  testAdmin(auth.headers);
  thinkTime();

  // ── Step 8: Logout ──
  testLogout(auth.headers);
}

// ============================================================================
// Teardown — Runs once after all VUs complete
// ============================================================================

export function teardown(data) {
  console.log('🏁 Test suite completed');
}

// ============================================================================
// Custom Summary — HTML report + console output
// ============================================================================

export function handleSummary(data) {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');

  return {
    // HTML report for browser viewing
    [`results/clinex_report_${timestamp}.html`]: htmlReport(data, {
      title: 'Clinex API — Load Test Report',
      showTrendLine: true,
    }),

    // JSON results for CI/CD pipelines
    [`results/clinex_results_${timestamp}.json`]: JSON.stringify(data, null, 2),

    // Console summary (always shown)
    stdout: textSummary(data, { indent: '  ', enableColors: true }),
  };
}
