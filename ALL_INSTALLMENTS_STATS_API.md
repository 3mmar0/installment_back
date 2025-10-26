# All Installments Statistics API

## Overview

Get comprehensive statistics summary for ALL installments (aggregated data across all customer installments).

## Endpoint

### Get All Installments Statistics

```http
GET /api/installment-all-stats
Authorization: Bearer {token}
```

## Response Format

```json
{
    "success": true,
    "message": "All installments statistics retrieved successfully",
    "data": {
        "total_installments": 25,
        "active_installments": 18,
        "completed_installments": 7,
        "total_amount": 35000.0,
        "total_items": 300,
        "paid_items": 125,
        "pending_items": 175,
        "overdue_items": 12,
        "due_soon_items": 23,
        "total_paid_amount": 15000.0,
        "total_remaining_amount": 20000.0,
        "overall_completion_percentage": 42.86,
        "breakdown": {
            "active": {
                "count": 18,
                "total_amount": 25000.0
            },
            "completed": {
                "count": 7,
                "total_amount": 10000.0
            }
        }
    }
}
```

## Response Fields

### Installment Summary

-   `total_installments` - Total number of installments
-   `active_installments` - Number of active installments
-   `completed_installments` - Number of completed installments

### Financial Overview

-   `total_amount` - Total amount across all installments
-   `total_paid_amount` - Total amount paid across all installments
-   `total_remaining_amount` - Total amount remaining across all installments
-   `overall_completion_percentage` - Overall completion percentage (0-100)

### Payment Items Summary

-   `total_items` - Total number of payment items across all installments
-   `paid_items` - Total number of paid items
-   `pending_items` - Total number of pending items
-   `overdue_items` - Total number of overdue items
-   `due_soon_items` - Total number of items due within 7 days

### Breakdown by Status

-   `breakdown.active` - Statistics for active installments
    -   `count` - Number of active installments
    -   `total_amount` - Total amount of active installments
-   `breakdown.completed` - Statistics for completed installments
    -   `count` - Number of completed installments
    -   `total_amount` - Total amount of completed installments

## Use Cases

### 1. Business Overview Dashboard

Get a quick overview of all installment business metrics.

### 2. Financial Reporting

Track total revenue, collections, and outstanding amounts.

### 3. Operational Metrics

Monitor active vs completed installments and payment progress.

### 4. Collection Management

Identify total overdue and due soon items for prioritization.

### 5. Performance Analytics

Track overall business performance and completion rates.

## Example Usage

### React/Frontend

```javascript
const response = await fetch("/api/installment-all-stats", {
    headers: { Authorization: `Bearer ${token}` },
});
const { data } = await response.json();

console.log(`Total Installments: ${data.total_installments}`);
console.log(`Completion: ${data.overall_completion_percentage}%`);
console.log(`Collected: $${data.total_paid_amount}`);
console.log(`Outstanding: $${data.total_remaining_amount}`);
```

### Dashboard Display

```javascript
function AllInstallmentsStats() {
    const [stats, setStats] = useState(null);

    useEffect(() => {
        fetchAllStats().then(setStats);
    }, []);

    return (
        stats && (
            <div className="stats-grid">
                <StatCard
                    label="Total Installments"
                    value={stats.total_installments}
                    subtitle={`${stats.active_installments} active`}
                />
                <StatCard
                    label="Total Amount"
                    value={`$${stats.total_amount.toLocaleString()}`}
                    subtitle={`${stats.overall_completion_percentage}% collected`}
                />
                <StatCard
                    label="Outstanding"
                    value={`$${stats.total_remaining_amount.toLocaleString()}`}
                    subtitle={`${stats.pending_items} pending items`}
                />
                <StatCard
                    label="Overdue"
                    value={stats.overdue_items}
                    subtitle="Payments"
                />
            </div>
        )
    );
}
```

### Comparison with Single Installment Stats

| Feature   | Single Installment            | All Installments             |
| --------- | ----------------------------- | ---------------------------- |
| Endpoint  | `/api/installment-stats/{id}` | `/api/installment-all-stats` |
| Scope     | Specific installment          | All installments             |
| Use Case  | Detailed view                 | Overview                     |
| Financial | Per-installment               | Aggregated totals            |
| Items     | Per-installment               | All items total              |
| Customer  | Specific customer             | All customers                |

## Key Calculations

### Overall Completion Percentage

```
overall_completion_percentage = (total_paid_amount / total_amount) * 100
```

### Total Remaining Amount

```
total_remaining_amount = total_amount - total_paid_amount
```

### Breakdown by Status

-   Active: Installments currently in progress
-   Completed: All payments received, installment complete

## Analytics Use Cases

### Business Performance Dashboard

```javascript
const metrics = {
    totalRevenue: stats.total_amount,
    collectedRevenue: stats.total_paid_amount,
    outstandingRevenue: stats.total_remaining_amount,
    completionRate: stats.overall_completion_percentage,
    activeClients: stats.active_installments,
    completedDeals: stats.completed_installments,
};
```

### Collection Priority

```javascript
const priorityActions = [
    { type: "overdue", count: stats.overdue_items, urgency: "high" },
    { type: "due_soon", count: stats.due_soon_items, urgency: "medium" },
    { type: "pending", count: stats.pending_items, urgency: "low" },
];
```

### Financial Health

```javascript
const financialHealth = {
    collectionRate: stats.overall_completion_percentage,
    avgInstallmentAmount: stats.total_amount / stats.total_installments,
    avgItemsPerInstallment: stats.total_items / stats.total_installments,
};
```

## Authorization

-   **Owner:** Can view statistics for all installments
-   **User:** Can view statistics for their own installments only
-   Based on authenticated user's role and ownership

## Related Endpoints

-   `GET /api/installment-stats/{id}` - Get single installment statistics
-   `GET /api/installment-list` - Get list of installments
-   `GET /api/dashboard` - Dashboard with detailed analytics
-   `GET /api/installment-overdue` - Get overdue items
-   `GET /api/installment-due-soon` - Get due soon items
