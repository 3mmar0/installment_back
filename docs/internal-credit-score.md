# Internal Credit Score

This system calculates an **Internal Credit Score** on a **300–850** display scale from installment data stored in this application only.

**Important:** This is **not** the official Egyptian I-Score and is **not** issued by the Egyptian Credit Bureau. UI and exports use labels such as *Internal Credit Score*, *Customer Credit Profile*, and *Internal Risk Level*.

## Formula (V1)

1. Six component scores on **0–100** (payment history, financial burden, credit history, credit activity, payment consistency, other risk).
2. Component weights are defined in [`config/credit_score.php`](../config/credit_score.php) and frozen on publish in `credit_score_model_versions`.
3. Unavailable components are excluded; remaining weights are renormalized.
4. **Observed raw** = weighted average of available components.
5. **Depth** blends observed behavior toward a neutral baseline for thin files:  
   `raw = baseline + (observed - baseline) × depth`
6. **Displayed score** = `round(300 + raw / 100 × 550)`, clamped to 300–850.

## DPD rules

- Paid on/before due date → DPD 0.
- Paid after due date → calendar days from due date to settlement date (`payment_requests.paid_on` when approved, else `paid_at`).
- Unpaid and `due_date` before start of today (UTC) → DPD to today.
- Due today is **not** overdue (matches `InstallmentDateHelper`).

## Data model

- `credit_score_model_versions` — frozen configuration per version.
- `customer_credit_scores` — append-only snapshots.
- `customers.current_credit_score_id` — fast pointer to latest snapshot.
- `credit_score_audit_logs` — manual recalculation and admin changes (no manual score edits).

## Recalculation

- **Event-driven:** installment create/pay/delete, payment request approval, import, linked personal pay → `RecalculateCustomerCreditScoreJob`.
- **Scheduled:** daily refresh at `credit_score.recalculation.daily_at` (default 08:30 UTC) for dirty customers and due-date crossings.
- **Backfill:** `php artisan credit-score:backfill`

## Access control

- Feature flag: `limits.features.internal_credit_score` (default **off** for new and existing merchants after migration).
- **Owner** accounts always pass the middleware check.
- **Plans (web):** checkbox on create/edit plan sets `features.internal_credit_score` for new assignments.
- **Per merchant (web):** owner user detail → toggle updates `PUT /api/limits/{userLimit}` with merged `features`.
- Web/mobile UI hides Credit Analytics and customer score widgets when the flag is false.

## API (vendor, subscription + `internal_credit_score` feature)

| Method | Path |
|--------|------|
| GET | `/api/credit-score/analytics` |
| GET | `/api/credit-score/reports?report=high_risk` |
| GET | `/api/credit-score/customer/{id}` |
| GET | `/api/credit-score/customer/{id}/history` |
| POST | `/api/credit-score/customer/{id}/recalculate` |
| POST | `/api/credit-score/customer/{id}/export-pdf` |

## Tests

```bash
php artisan test --filter=CreditScore
```
