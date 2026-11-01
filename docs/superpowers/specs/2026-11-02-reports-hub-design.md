# Reports Hub Design

**Date:** 2026-11-02  
**Status:** Draft for implementation planning  
**Surfaces:** Web (`installment-front`) + Mobile (`installment-mobile`) + shared API (`installment_back`)  
**Audiences:** Vendors (owners/staff) and platform admins (not clients)

## Goals

- One shared reports catalog and API for web and mobile.
- Cover collections, cash, plans, customers, credit, operations, and admin platform analytics.
- In-app viewing plus export to PDF, Excel, and CSV.
- Respect existing plan gates (`reports`, `internal_credit_score`) and role boundaries.

## Non-goals

- Client-facing reports.
- Ad-hoc custom report builder (deferred).
- Replacing the dashboard home KPIs (hub complements dashboard, does not remove it).

## Approach

Expand the existing web Reports Hub and backend catalog/overview/export endpoints. Add a mobile Reports experience that consumes the same catalog and report APIs.

## Report catalog

### A. Collections & aging

1. Overdue installments (by customer / plan)
2. Aging buckets (1–7 / 8–30 / 31–60 / 61–90 / 90+)
3. Due soon (next 7 / 14 / 30 days)
4. Collection effectiveness (promised vs collected)
5. Reminder performance (sent → paid)

### B. Cash & payments

6. Collected by period (day / week / month)
7. Outstanding balance snapshot
8. Payment methods / proof status (pending / approved / rejected)
9. Partial payments & shortfalls
10. Refunds / reversals (only when product supports them)

### C. Installment plans

11. Active vs completed vs cancelled plans
12. Plan performance (on-time rate, default rate)
13. New plans created in period
14. Average plan size / tenure
15. Items schedule report (full schedule view)

### D. Customers

16. Customer portfolio summary
17. Top customers by paid / by outstanding
18. New vs returning customers
19. Inactive / no-payment customers
20. Customer statement (single customer ledger)

### E. Credit risk (extend existing)

21. Overdue (credit view)
22. High / very high risk
23. Serious delinquency (+90)
24. Declining scores
25. Improving scores
26. Excellent payers
27. Thin files
28. Score distribution bands
29. Portfolio average score trend

### F. Operations / staff (vendor)

30. Staff activity (who created customers / plans / approvals)
31. Approval queue SLA (time to approve proofs)
32. Import batches results

### G. Subscription / limits (vendor owner)

33. Plan usage vs limits
34. Feature entitlement audit (reports / credit flags)

### H. Platform admin only

35. Merchants overview (active, plans, volume)
36. Platform GMV / collected across merchants
37. Churn / inactive merchants
38. Subscription revenue & trials
39. Risk hotspots across merchants
40. System health / failed jobs / notification delivery (ops)

### I. Exports & packs

41. Dashboard summary pack (existing PDF / Excel / CSV)
42. Period financial pack (collections + aging + outstanding)
43. Credit pack (selected credit report CSV / PDF)
44. Customer statement PDF

## Hub UX

### Shared structure

1. Overview strip — KPIs for current scope (collected this period, outstanding, overdue count/amount, due soon, optional avg credit score).
2. Category navigation — Collections · Cash · Plans · Customers · Credit · Operations · Platform (admin only).
3. Report list — title, short description, plan-locked badge when gated, optional last-run hint.
4. Report detail — filters → summary cards → table/chart → export actions (PDF / Excel / CSV).

### Mobile

- New Reports entry (tab or More stack if tab bar is full).
- Hub = categories; tap → report screen with filter sheet, results, share/export.
- Tables: sticky customer name column; horizontal scroll for money columns.

### Web

- Expand existing `ReportsHubPage`: category sidebar + detail pane.
- Keep current financial exports and credit report cards as first entries in catalog.

## Roles & gating

| Audience | Data scope | Notes |
|----------|------------|--------|
| Vendor owner | Own merchant | Full vendor categories subject to plan flags |
| Vendor staff | Own merchant | Same reports if role allows; hide owner-only subscription/limits unless permitted |
| Platform admin | All merchants or selected `merchant_id` | Platform category + merchant filter |
| Client | — | Out of scope |

| Category | Gate |
|----------|------|
| Collections, Cash, Plans, Customers, Operations, financial packs | `reports` plan flag (owners always as today) |
| Credit | `internal_credit_score` |
| Platform admin reports | Platform admin only |

## API shape

Expand existing hub rather than inventing a parallel system.

- `GET /reports/catalog` — categories, report definitions, enabled/locked, export formats (role + plan aware).
- `GET /reports/overview` — KPI strip (vendor-scoped; admin may pass `merchant_id`).
- `GET /reports/{reportKey}` — `{ summary, columns, rows, meta }` with pagination and filters.
- `POST /reports/{reportKey}/export` — `{ format: pdf|excel|csv, filters }` → file download (queue if heavy).

Credit report endpoints remain valid and are represented in the Credit category of the catalog. Dashboard summary exports remain the “Dashboard pack” report.

### Data rules

- Vendor queries always scoped to authenticated ownership (same patterns as customers/installments).
- Admin endpoints require platform admin; optional `merchant_id`; platform reports may omit merchant filter.
- Date filters default to current month; aging uses an “as of” date.
- Money amounts use merchant currency from locale preferences.

### Errors & empty states

- Locked feature → upgrade CTA using existing limits messaging.
- No rows → empty state with filter reset.
- Export failure → visible error + retry; no silent partial files.

## Delivery waves

1. **Wave 1** — Mobile hub shell; wire existing financial export + credit reports; Collections: overdue, aging, due soon.
2. **Wave 2** — Cash (collected by period, outstanding, payment proofs) + customer statement + top/inactive customers.
3. **Wave 3** — Plans performance + ops/staff + reminder effectiveness.
4. **Wave 4** — Admin platform reports (merchants, GMV, subscriptions, risk hotspots).
5. **Wave 5** — Polish: PDF packs, saved filter presets, empty/error parity, Arabic labels.

## Testing focus

- Role/plan gating for each category (vendor locked vs unlocked, admin-only).
- Scope isolation (vendor A cannot read vendor B).
- Pagination and date filter correctness for overdue/aging/collected.
- Export smoke tests for PDF/Excel/CSV on at least one report per category wave.
- Mobile navigation entry + empty/locked states.

## Open decisions resolved in brainstorming

- Surfaces: both web and mobile, shared API.
- Audiences: vendors and platform admins (not clients).
- Scope: full catalog designed now; build in waves.
- Takeaway formats: view + PDF + Excel/CSV.
