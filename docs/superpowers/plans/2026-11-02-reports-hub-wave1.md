# Reports Hub Wave 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a shared Reports Hub Wave 1 — expanded catalog/API for collections reports (overdue, aging, due soon), wire existing financial + credit exports, add mobile Reports entry, and category-aware web hub shell.

**Architecture:** Extend `ReportsHubController` + new `ReportQueryService` for keyed reports returning `{ summary, columns, rows, meta }`. Web expands `ReportsHubPage` into category/list/detail. Mobile adds a Profile-stack Reports flow (tab bar already has 5 items). Plan gates reuse `reports` and `internal_credit_score`.

**Tech Stack:** Laravel (Pest), React + TypeScript (Vite), React Native + Expo, existing `reportsService` / export controllers.

**Spec:** `docs/superpowers/specs/2026-11-02-reports-hub-design.md`

## Global Constraints

- Audiences: vendors + platform admins only (no client reports).
- Surfaces: shared API for web + mobile.
- Exports: view + PDF/Excel/CSV where gated `reports` allows; credit CSV via existing credit endpoints.
- Money: merchant currency from locale prefs.
- Vendor data always ownership-scoped; admin may pass `merchant_id` later (Wave 4) — Wave 1 vendor-scoped only.
- Arabic UI strings required for new mobile/web copy.
- Follow existing Pest feature test style under `installment_back/tests/Feature`.

## File map (Wave 1)

| File | Responsibility |
|------|----------------|
| `installment_back/app/Services/Reports/ReportCatalog.php` | Static catalog definitions (categories + report keys) |
| `installment_back/app/Services/Reports/ReportQueryService.php` | Run overdue / aging / due_soon queries |
| `installment_back/app/Http/Controllers/Api/ReportsHubController.php` | catalog/overview + `show` report endpoint |
| `installment_back/routes/api.php` | `GET reports/{reportKey}` |
| `installment_back/tests/Feature/ReportsHubWave1Test.php` | Catalog, gating, overdue/aging/due_soon |
| `installment-front/src/services/reportsService.ts` | `getReport(key, params)` |
| `installment-front/src/pages/reports/ReportsHubPage.tsx` | Category chips + report list/detail for Wave 1 |
| `installment-mobile/src/api/reports.ts` | Mobile API client |
| `installment-mobile/src/screens/reports/*` | Hub + report detail screens |
| `installment-mobile/src/navigation/*` | Profile stack routes + types |
| Translation JSON (ar/en) front + mobile | New `reports.*` keys |

---

### Task 1: Backend catalog + report show contract (failing tests first)

**Files:**
- Create: `installment_back/tests/Feature/ReportsHubWave1Test.php`
- Create: `installment_back/app/Services/Reports/ReportCatalog.php`
- Modify: `installment_back/app/Http/Controllers/Api/ReportsHubController.php`
- Modify: `installment_back/routes/api.php` (add `GET reports/{reportKey}`)
- Create: `installment_back/app/Services/Reports/ReportQueryService.php`

**Interfaces:**
- Produces: `ReportCatalog::definitions(): array` returning categories with reports `{ key, title, description, category, exports[] }`
- Produces: `ReportQueryService::run(User $user, string $key, array $filters): array` → `{ summary: object, columns: array, rows: array, meta: { total, page, per_page } }`
- Produces: `GET /api/reports/{reportKey}` JSON via `ReportsHubController@show`

- [ ] **Step 1: Write the failing Pest tests**

```php
<?php

use App\Models\User;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function vendorWithReports(): User {
    // Use existing factories / helpers from sibling Feature tests for a vendor owner
    // with reports entitlement enabled the same way Dashboard/export tests do.
    $user = User::factory()->create(/* owner vendor */);
    // Ensure LimitsHelper reports=true for this user (mirror ExportReport tests).
    return $user;
}

it('returns wave1 collection reports in catalog when reports enabled', function () {
    $user = vendorWithReports();
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/reports/catalog')
        ->assertOk()
        ->assertJsonPath('data.financial_enabled', true)
        ->assertJsonFragment(['key' => 'overdue'])
        ->assertJsonFragment(['key' => 'aging'])
        ->assertJsonFragment(['key' => 'due_soon']);
});

it('forbids report show when reports feature disabled', function () {
    $user = User::factory()->create();
    // limits.reports = false
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/reports/overdue')
        ->assertStatus(403);
});

it('returns overdue report rows for the authenticated vendor only', function () {
    $user = vendorWithReports();
    // seed one overdue item for $user and one for another vendor
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/reports/overdue?per_page=50')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => ['count', 'total_amount'],
                'columns',
                'rows',
                'meta' => ['total', 'page', 'per_page'],
            ],
        ]);
});

it('returns aging buckets summary', function () {
    $user = vendorWithReports();
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/reports/aging')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => ['buckets'],
                'columns',
                'rows',
                'meta',
            ],
        ]);
});

it('returns due_soon rows within days filter', function () {
    $user = vendorWithReports();
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/reports/due_soon?days=14')
        ->assertOk()
        ->assertJsonPath('data.meta.per_page', 20);
});
```

Adapt `vendorWithReports()` to the exact factory/limit helpers already used in `tests/Feature` (search `reports` / `ExportReport` / `LimitsHelper` in existing tests and copy that setup — do not invent a new limits schema).

- [ ] **Step 2: Run tests to verify they fail**

Run (from `installment_back`):

```bash
php artisan test --filter=ReportsHubWave1Test
```

Expected: FAIL (missing route/method or missing keys).

- [ ] **Step 3: Implement catalog + query service + controller show**

`ReportCatalog.php` — include at least:

```php
public const WAVE1_REPORTS = [
    'overdue' => [
        'key' => 'overdue',
        'category' => 'collections',
        'title' => 'أقساط متأخرة',
        'description' => 'عناصر أقساط تجاوزت تاريخ الاستحقاق',
        'exports' => ['pdf', 'excel', 'csv'],
    ],
    'aging' => [
        'key' => 'aging',
        'category' => 'collections',
        'title' => 'أعمار المتأخرات',
        'description' => 'تجميع المتأخرات حسب فترات التأخير',
        'exports' => ['pdf', 'excel', 'csv'],
    ],
    'due_soon' => [
        'key' => 'due_soon',
        'category' => 'collections',
        'title' => 'مستحق قريباً',
        'description' => 'أقساط تستحق خلال الفترة المحددة',
        'exports' => ['pdf', 'excel', 'csv'],
    ],
];
```

Merge into `catalog()` response as `categories` + keep existing `financial_exports` / `credit_report_types`.

`ReportQueryService::run`:
- `overdue`: unpaid items with `due_date < today`, scoped by vendor; summary `{ count, total_amount }`; columns customer_name, installment_id, due_date, days_overdue, amount.
- `aging`: group those rows into buckets `1_7`, `8_30`, `31_60`, `61_90`, `90_plus`; `summary.buckets` map + rows listing each item with `bucket`.
- `due_soon`: unpaid items with `due_date` between today and today+`days` (default 14, max 90).

Reuse query patterns from `InstallmentService::getDashboardAnalytics` / `overduePaymentsForDashboard` — extract shared scopes rather than duplicating insecure queries.

`ReportsHubController@show(Request $request, string $reportKey)`:
- Gate with same `canAccessFinancialReports` for collections keys.
- Unknown key → 404.
- Call `ReportQueryService::run` with validated filters: `page`, `per_page` (1–100, default 20), `days` for due_soon.

Route inside existing auth group:

```php
Route::get('reports/{reportKey}', [ReportsHubController::class, 'show']);
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --filter=ReportsHubWave1Test
```

Expected: PASS

- [ ] **Step 5: Commit (backend)**

```bash
cd installment_back
git add app/Services/Reports app/Http/Controllers/Api/ReportsHubController.php routes/api.php tests/Feature/ReportsHubWave1Test.php
git commit -m "feat(reports): add wave1 catalog keys and collections report show API"
```

---

### Task 2: Web reports service + hub category UI for Wave 1

**Files:**
- Modify: `installment-front/src/services/reportsService.ts`
- Modify: `installment-front/src/constants/apiEndpoints.ts`
- Modify: `installment-front/src/pages/reports/ReportsHubPage.tsx`
- Modify: front i18n/strings if the page is Arabic-hardcoded — keep Arabic copy consistent with catalog titles

**Interfaces:**
- Consumes: `GET /reports/catalog`, `GET /reports/overview`, `GET /reports/{reportKey}`
- Produces: `reportsService.getReport(reportKey, params): Promise<{ summary, columns, rows, meta }>`

- [ ] **Step 1: Extend endpoints + service**

```ts
// apiEndpoints.ts REPORTS
REPORT: (key: string) => `${API_BASE_URL}/reports/${key}`,

// reportsService.ts
async getReport(
  reportKey: string,
  params?: { page?: number; per_page?: number; days?: number }
) {
  const res = await apiClient.get<ApiResponse<{
    summary: Record<string, unknown>;
    columns: Array<{ key: string; label: string }>;
    rows: Record<string, unknown>[];
    meta: { total: number; page: number; per_page: number };
  }>>(API_ENDPOINTS.REPORTS.REPORT(reportKey), { params });
  return res.data;
},
```

- [ ] **Step 2: Update ReportsHubPage**

Add category chip row: `collections | cash | plans | customers | credit | operations` (disable empty categories in Wave 1 except collections + credit + financial pack).

When `collections` selected, list cards for `overdue`, `aging`, `due_soon` from catalog (fallback to hardcoded WAVE1 if catalog missing fields during rollout).

Selecting a card loads `getReport(key)` into a table using `columns`/`rows`, shows `summary` chips, keeps existing PDF/Excel/CSV dashboard export buttons in a “Financial pack” card.

Keep existing credit report section under `credit` category unchanged.

- [ ] **Step 3: Manual smoke**

Run front dev server, open Reports hub as vendor with reports enabled:
- Collections → Overdue shows rows
- Aging shows bucket summary
- Due soon respects days control (7/14/30)
- Locked plan user sees upgrade empty/lock state (reuse existing limits messaging)

- [ ] **Step 4: Commit (front)**

```bash
cd installment-front
git add src/services/reportsService.ts src/constants/apiEndpoints.ts src/pages/reports/ReportsHubPage.tsx
git commit -m "feat(reports): wire wave1 collections reports into reports hub UI"
```

---

### Task 3: Mobile API client + navigation entry

**Files:**
- Create: `installment-mobile/src/api/reports.ts`
- Modify: `installment-mobile/src/constants/apiEndpoints.ts` (or equivalent API paths module — match existing pattern under `src/api` / `src/constants`)
- Modify: `installment-mobile/src/navigation/types.ts` — extend `ProfileStackParamList`
- Modify: `installment-mobile/src/navigation/ProfileStackNavigator.tsx` — register screens
- Modify: Profile home screen — add “التقارير” row linking to Reports hub
- Modify: `src/assets/translations/ar.json` + `en.json`

**Interfaces:**
- Produces: `reportsApi.getCatalog()`, `getOverview()`, `getReport(key, params)`
- Produces routes: `ReportsHub`, `ReportDetail: { reportKey: string; title?: string }`

- [ ] **Step 1: Add API module**

Mirror front client using mobile axios instance (same auth interceptors as `creditScore` / dashboard APIs):

```ts
export const reportsApi = {
  getCatalog: () => api.get('/reports/catalog'),
  getOverview: () => api.get('/reports/overview'),
  getReport: (key: string, params?: Record<string, unknown>) =>
    api.get(`/reports/${key}`, { params }),
};
```

- [ ] **Step 2: Navigation + Profile entry**

```ts
// types.ts ProfileStackParamList
ReportsHub: undefined;
ReportDetail: { reportKey: string; title?: string };
```

Register screens in `ProfileStackNavigator`. On Profile home, add list row with icon `assessment` / `bar-chart`, label `t('reports.title')`, navigate to `ReportsHub`.

Do **not** add a 6th tab in Wave 1 (tab bar already crowded).

- [ ] **Step 3: Translations**

```json
"reports": {
  "title": "التقارير",
  "subtitle": "تقارير التحصيل والائتمان والتصدير",
  "categories": {
    "collections": "التحصيل",
    "credit": "الائتمان",
    "financial_pack": "حزمة مالية"
  },
  "overdue": "أقساط متأخرة",
  "aging": "أعمار المتأخرات",
  "due_soon": "مستحق قريباً",
  "empty": "لا توجد بيانات لهذا التقرير",
  "locked": "هذه التقارير غير متاحة في خطتك",
  "export": "تصدير",
  "days": "خلال أيام"
}
```

Add English equivalents in `en.json`.

- [ ] **Step 4: Commit (mobile)**

```bash
cd installment-mobile
git add src/api/reports.ts src/navigation/types.ts src/navigation/ProfileStackNavigator.tsx src/screens/auth/ProfileScreen.tsx src/assets/translations/ar.json src/assets/translations/en.json
git commit -m "feat(reports): add mobile reports API client and profile entry"
```

---

### Task 4: Mobile Reports Hub + Report Detail screens

**Files:**
- Create: `installment-mobile/src/screens/reports/ReportsHubScreen.tsx`
- Create: `installment-mobile/src/screens/reports/ReportDetailScreen.tsx`
- Create: `installment-mobile/src/components/reports/ReportSummaryStrip.tsx` (optional small presentational)
- Create: `installment-mobile/src/components/reports/ReportTable.tsx`

**Interfaces:**
- Consumes: `reportsApi.*`
- Hub lists categories/cards from catalog; Detail loads `getReport` and renders summary + FlatList rows

- [ ] **Step 1: Implement ReportsHubScreen**

- Load catalog + overview on focus.
- Show KPI strip from overview.financial (collected/outstanding/overdue if present).
- Category chips: Collections, Credit, Financial pack.
- Cards navigate to `ReportDetail` for overdue/aging/due_soon.
- Credit cards deep-link to existing credit flows **or** show locked/placeholder that opens credit report keys via same detail screen if API already covers them; Wave 1 minimum: show credit types from catalog and open read-only list via existing credit-score report endpoint if `getReport` does not cover credit keys yet.
- Financial pack card: buttons calling existing export download helpers (reuse front mental model; if mobile download helper exists for PDF/Excel/CSV use it; otherwise “Export on web” note is unacceptable — wire `apiClient` download like other mobile file downloads, search `download` / `Sharing` / `FileSystem` in mobile codebase and follow that pattern).

- [ ] **Step 2: Implement ReportDetailScreen**

- Header title from params/catalog.
- Filters: `due_soon` → days chips 7/14/30; all → pull-to-refresh.
- Summary chips from `summary`.
- `ReportTable`: map `columns` to header; each row as card (mobile-friendly) with labeled fields — not a tiny HTML table.
- Empty + error + locked states.
- Use sky `PageHeader` / existing screen chrome for consistency.

- [ ] **Step 3: Manual smoke on device/emulator**

- Profile → Reports → Overdue loads
- Aging buckets visible
- Due soon filter changes result set
- Locked plan shows CTA

- [ ] **Step 4: Commit (mobile)**

```bash
cd installment-mobile
git add src/screens/reports src/components/reports
git commit -m "feat(reports): add mobile reports hub and collections detail screens"
```

---

### Task 5: Wave 1 export hook for collections (API stub → real file)

**Files:**
- Modify: `installment_back/app/Http/Controllers/Api/ReportsHubController.php` or `ExportReportController.php`
- Modify: `installment_back/routes/api.php` — `POST reports/{reportKey}/export`
- Extend: `tests/Feature/ReportsHubWave1Test.php`
- Wire: front + mobile export buttons on detail views

**Interfaces:**
- Produces: `POST /api/reports/{reportKey}/export` body `{ format: 'csv'|'excel'|'pdf' }` returns file download
- Wave 1 minimum viable: **CSV** for overdue/aging/due_soon (PDF/Excel can reuse dashboard exporter patterns in Wave 5 if time-boxed — if CSV-only in Wave 1, catalog `exports` for those keys must list only `csv` until others land)

- [ ] **Step 1: Failing test for CSV export**

```php
it('exports overdue report as csv when reports enabled', function () {
    $user = vendorWithReports();
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/reports/overdue/export', ['format' => 'csv'])
        ->assertOk()
        ->assertHeader('content-type', expectCsvContentType());
});
```

- [ ] **Step 2: Implement CSV export using ReportQueryService rows**

Stream CSV with same columns as `show`. Gate with `canAccessFinancialReports`.

- [ ] **Step 3: Wire export button on web detail + mobile detail**

- [ ] **Step 4: Tests pass + commit**

```bash
cd installment_back
git add ...
git commit -m "feat(reports): add CSV export for wave1 collections reports"
```

Then commit front/mobile export wiring separately.

---

## Follow-up plans (not in this file’s execution scope)

- **Wave 2:** Cash + customer statement + top/inactive customers  
- **Wave 3:** Plans performance + staff/ops + reminders  
- **Wave 4:** Platform admin merchant filters + GMV/subscription reports  
- **Wave 5:** PDF/Excel packs, saved presets, optional Reports tab promotion  

Each gets its own plan under `docs/superpowers/plans/` when Wave 1 is done.

## Spec coverage (self-review)

| Spec item | Task |
|-----------|------|
| Shared catalog API | Task 1 |
| Collections overdue/aging/due soon | Task 1, 4 |
| Existing financial + credit in hub | Task 2, 4 |
| Mobile reports entry | Task 3–4 |
| View + export | Task 5 (CSV Wave 1; PDF/Excel packs Wave 5) |
| Plan gating | Task 1 tests + UI locked states |
| Admin platform reports | Deferred Wave 4 |
| Full remaining catalog | Waves 2–5 |

## Placeholder scan

No TBD steps; factory helper must be copied from existing tests (explicit instruction in Task 1).
