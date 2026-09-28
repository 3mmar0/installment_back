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
- **Backfill:** `php artisan credit-score:backfill` (customers **with** installments only)

## Installment eligibility & salary

- Optional field `customers.monthly_salary` (EGP) on create/update customer APIs.
- `GET /api/credit-score/customer/{id}` includes `installment_eligibility`: estimated monthly obligation, DTI vs `credit_score.affordability.max_dti_ratio` (default 40%), `can_accept_installment` (true/false/null), Arabic `reasons`. This is a **merchant decision aid**, not bureau data.
- **No installments → no score:** recalculation clears `current_credit_score_id`; profile returns `score: null` and `score_applicable: false`. Historical snapshots may remain in `customer_credit_scores` but are not surfaced as the current score.

## Access control

- Feature flag: `limits.features.internal_credit_score` (default **off** for new and existing merchants after migration).
- **Owner** accounts always pass the middleware check.
- **Plans (web):** checkbox on create/edit plan sets `features.internal_credit_score` for new assignments.
- **Per merchant (web):** owner user detail → toggle updates `PUT /api/limits/{userLimit}` with merged `features`.
- Web/mobile UI hides Credit Analytics and customer score widgets when the flag is false.

## شرح للمستخدم (عربي)

**ما هذا؟**  
تقييم **داخلي** على مقياس **300–850** يُبنى فقط من أقساط ومدفوعاتك المسجلة في «اقساطي». **ليس** I-Score الرسمي ولا صادرًا عن iScore/Microfinance.

**كيف تُحسب الدرجة؟**

1. **ستة مكونات** (كل منها 0–100): سجل السداد (35%)، العبء المالي (25%)، تاريخ الائتمان (15%)، النشاط (10%)، انتظام السداد (10%)، مخاطر أخرى (5%).
2. أي مكون غير متاح يُستبعد ويُعاد توزيع وزنه.
3. **بدون أقساط:** لا يُعرض Score ولا يُحسب snapshot جديد.
4. **المرتب:** أدخله في بيانات العميل لتقدير «هل يمكن قبول قسط جديد» (نسبة الالتزام/المرتب).
5. **ملف رقيق:** عميل له أقساط لكن تاريخ محدود تُمزج درجته مع خط أساس (~620) حتى يكتمل التاريخ.
6. **العرض:** `300 + (المجموع المرجّح ÷ 100 × 550)` مع التقريب والحد الأقصى/الأدنى.
7. **DPD:** السداد في الموعد = 0؛ التأخير بالأيام؛ استحقاق اليوم لا يُعد متأخرًا حتى نهاية اليوم.

**أين أرى الشرح في الواجهة؟**  
الويب: «تحليلات التقييم» و«ملف التقييم الائتماني للعميل» → قسم «كيف يُحسب…».  
الموبايل: بطاقة التقييم في تفاصيل العميل → «كيف تُحسب الدرجة؟».

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
