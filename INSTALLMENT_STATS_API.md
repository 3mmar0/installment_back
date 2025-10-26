# Installment Statistics API

## Overview

Get comprehensive statistics for a specific installment including payment progress, completion status, and payment schedule details.

## Endpoint

### Get Installment Statistics

```http
GET /api/installment-stats/{id}
Authorization: Bearer {token}
```

## Response Format

```json
{
    "success": true,
    "message": "Installment statistics retrieved successfully",
    "data": {
        "installment_id": 1,
        "total_amount": 1200.0,
        "paid_amount": 600.0,
        "remaining_amount": 600.0,
        "completion_percentage": 50.0,
        "total_items": 12,
        "paid_items": 6,
        "pending_items": 6,
        "overdue_count": 0,
        "due_soon_count": 2,
        "next_payment_date": "2024-02-01",
        "next_payment_amount": 100.0,
        "last_payment_date": "2024-01-01",
        "last_payment_amount": 100.0,
        "status": "active",
        "start_date": "2024-01-01",
        "end_date": "2024-12-01",
        "months": 12,
        "customer_id": 1,
        "customer_name": "John Doe"
    }
}
```

## Response Fields

### Financial Summary

-   `total_amount` - Total installment amount
-   `paid_amount` - Amount paid so far
-   `remaining_amount` - Amount remaining to be paid
-   `completion_percentage` - Payment completion percentage (0-100)

### Payment Items

-   `total_items` - Total number of payment items
-   `paid_items` - Number of completed payments
-   `pending_items` - Number of pending payments
-   `overdue_count` - Number of overdue payments
-   `due_soon_count` - Number of payments due within 7 days

### Payment Schedule

-   `next_payment_date` - Date of next pending payment
-   `next_payment_amount` - Amount of next payment
-   `last_payment_date` - Date of last completed payment
-   `last_payment_amount` - Amount of last completed payment

### Installment Details

-   `installment_id` - Installment ID
-   `status` - Installment status (active/completed)
-   `start_date` - Start date of installment
-   `end_date` - End date of installment
-   `months` - Number of payment months

### Customer Info

-   `customer_id` - Customer ID
-   `customer_name` - Customer name

## Authorization

-   **Owner:** Can view statistics for any installment
-   **User:** Can only view statistics for their own installments
-   **Unauthorized:** Returns 403 Forbidden

## Error Responses

### Not Found

```json
{
    "success": false,
    "message": "Installment not found or unauthorized"
}
```

### Forbidden (404 status)

```json
{
    "success": false,
    "message": "You are not authorized to view this installment"
}
```

## Use Cases

### 1. Payment Progress Dashboard

Track how much has been paid and how much remains.

### 2. Payment Schedule Display

Show customers their next payment date and amount.

### 3. Collection Management

Identify overdue and due soon payments for follow-up.

### 4. Financial Reporting

Generate reports on payment completion percentages.

### 5. Customer Communication

Provide customers with detailed payment status information.

## Example Usage

### React/Frontend

```javascript
const response = await fetch("/api/installment-stats/1", {
    headers: { Authorization: `Bearer ${token}` },
});
const { data } = await response.json();

console.log(`Completion: ${data.completion_percentage}%`);
console.log(`Remaining: $${data.remaining_amount}`);
console.log(`Overdue: ${data.overdue_count} payments`);
```

### Analytics Dashboard

```javascript
const stats = installmentStats;

// Progress bar
<div className="progress-bar">
    <div style={{ width: `${stats.completion_percentage}%` }} />
</div>

// Summary cards
<div className="stats-grid">
    <Card title="Paid" value={`$${stats.paid_amount}`} />
    <Card title="Remaining" value={`$${stats.remaining_amount}`} />
    <Card title="Completion" value={`${stats.completion_percentage}%`} />
    <Card title="Next Payment" value={`$${stats.next_payment_amount}`} />
</div>
```

## Frontend Integration

### React Component Example

```javascript
function InstallmentStats({ installmentId }) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchInstallmentStats(installmentId)
            .then((data) => setStats(data))
            .finally(() => setLoading(false));
    }, [installmentId]);

    if (loading) return <Loading />;
    if (!stats) return <Error />;

    return (
        <div>
            <ProgressBar percentage={stats.completion_percentage} />
            <StatCard label="Total" value={stats.total_amount} />
            <StatCard label="Paid" value={stats.paid_amount} />
            <StatCard label="Remaining" value={stats.remaining_amount} />
        </div>
    );
}
```

### Flutter Widget Example

```dart
class InstallmentStatsWidget extends StatelessWidget {
  final int installmentId;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: fetchInstallmentStats(installmentId),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return CircularProgressIndicator();
        }

        final stats = snapshot.data!;

        return Column(
          children: [
            LinearProgressIndicator(
              value: stats['completion_percentage'] / 100,
            ),
            StatsCard(label: 'Total', value: stats['total_amount']),
            StatsCard(label: 'Paid', value: stats['paid_amount']),
            StatsCard(label: 'Remaining', value: stats['remaining_amount']),
          ],
        );
      },
    );
  }
}
```

## Key Statistics Explained

### Completion Percentage

Calculated as: `(paid_amount / total_amount) * 100`

-   Shows how much of the installment has been completed
-   Useful for progress bars and visualizations

### Remaining Amount

Calculated as: `total_amount - paid_amount`

-   Shows how much is still owed
-   Important for customer communication

### Overdue Count

Number of payments past their due date

-   Critical for collection management
-   Helps prioritize follow-up actions

### Due Soon Count

Number of payments due within 7 days

-   Helps with proactive communication
-   Important for cash flow planning

### Next Payment Information

-   Date when next payment is due
-   Amount that needs to be paid
-   Critical for payment reminder emails

## Related Endpoints

-   `GET /api/installment-show/{id}` - Get full installment details
-   `POST /api/installment-item-pay/{item}` - Mark payment as received
-   `GET /api/installment-overdue` - Get overdue items
-   `GET /api/installment-due-soon` - Get due soon items
-   `GET /api/dashboard` - Dashboard analytics
