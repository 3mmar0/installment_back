# Email Notification System

## Overview

Professional email notification system that sends automated emails to customers and owners for payment reminders and confirmations. Works without queues for shared hosting compatibility.

## Email Types

### 1. Payment Due Reminder (2 days before due date)

-   **Recipients:** Customer + Owner
-   **When:** 2 days before payment due date
-   **Content:** Payment details, amount, due date, installment info
-   **Subject:** "Payment Reminder: Due in X days"

### 2. Payment Overdue Notice

-   **Recipients:** Customer + Owner
-   **When:** Immediately when payment becomes overdue
-   **Content:** Overdue amount, days overdue, urgency notice
-   **Subject:** "Urgent: Payment Overdue - X Days"

### 3. Payment Received Confirmation

-   **Recipients:** Customer + Owner
-   **When:** When payment is marked as received
-   **Content:** Payment confirmation, amount, reference, remaining balance
-   **Subject:** "Payment Received - Thank You!"

## Configuration

### Environment Variables (.env)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Shared Hosting Configuration

For shared hosting like cPanel, use:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=ssl
```

## API Endpoints

### Send Reminder Emails

```http
POST /api/notification-send-emails
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "message": "Sent X reminder emails successfully",
    "data": {
        "due_reminders_sent": 5,
        "overdue_notices_sent": 2,
        "total_emails": 7
    }
}
```

## Email Templates

### Payment Due Reminder

-   Professional greeting with customer name
-   Payment details table
-   Payment amount and due date
-   Installment ID reference
-   Contact information

### Payment Overdue Notice

-   Urgent notice highlighting overdue status
-   Days overdue count
-   Payment amount and original due date
-   Action required message
-   Contact options for payment issues

### Payment Received Confirmation

-   Thank you message
-   Payment confirmation details
-   Amount received and date
-   Reference number
-   Installment status and remaining balance

## Automatic Email Triggers

### 1. Payment Received

Emails are automatically sent when:

-   Admin marks an installment item as paid
-   Both customer and owner receive confirmation emails

### 2. Manual Reminder Sending

Use the API endpoint to send all pending reminders:

-   Due in 2 days emails
-   Overdue payment notices

## Email Service

### EmailNotificationService Methods

```php
// Send all payment reminders
$result = $emailNotificationService->sendAllPaymentReminders($user);

// Send only upcoming payment reminders (2 days)
$count = $emailNotificationService->sendPaymentDueReminders($user);

// Send only overdue notices
$count = $emailNotificationService->sendOverduePaymentNotices($user);

// Send payment received confirmation
$emailNotificationService->sendPaymentReceivedConfirmation($item, $paidAmount, $user);
```

## Error Handling

-   Logs all email failures to Laravel log
-   Continues processing other emails if one fails
-   Returns count of successfully sent emails
-   No blocking errors that stop the process

## Testing

### Test Email Configuration

```bash
php artisan tinker
Mail::raw('Test email', function($message) {
    $message->to('your@email.com')->subject('Test');
});
```

### Test Email Templates

```bash
php artisan make:mail TestPaymentDue
```

## Cron Job (Recommended)

Set up a cron job to automatically send reminder emails daily:

```bash
# Edit crontab
crontab -e

# Add this line (runs daily at 9 AM)
0 9 * * * cd /path/to/project && php artisan notification:send-emails >> /dev/null 2>&1
```

Or for shared hosting, use cPanel Cron Jobs:

-   Schedule: Daily at 9:00 AM
-   Command: `php /home/username/public_html/artisan notification:send-emails`

## Professional Email Features

✅ Responsive design for mobile devices
✅ Professional business branding
✅ Clear call-to-action messages
✅ Contact information included
✅ Detailed payment information
✅ Reference numbers for tracking
✅ Remaining balance calculations
✅ Customer-friendly language

## Email Content Structure

All emails include:

-   Professional header with app name
-   Personalized greeting with customer name
-   Detailed payment information table
-   Clear action items
-   Contact information
-   Professional footer
-   Brand-consistent styling

## Best Practices

1. **Email Delivery:** Test with real email addresses
2. **Frequency:** Don't send too many emails (respect customer inbox)
3. **Content:** Keep messages clear and concise
4. **Timing:** Send reminders at appropriate times (9 AM - 5 PM)
5. **Personalization:** Always use customer's name
6. **Contact Info:** Always provide support contact

## Troubleshooting

### Emails not sending

1. Check mail configuration in `.env`
2. Verify SMTP credentials
3. Check Laravel logs: `storage/logs/laravel.log`
4. Test SMTP connection with: `php artisan tinker`

### Emails going to spam

1. Use proper FROM address (same domain)
2. Set up SPF and DKIM records
3. Avoid spam trigger words
4. Keep email volume reasonable

### Shared hosting issues

1. Use port 465 with SSL
2. Check if SMTP is enabled in cPanel
3. Verify host allows outbound SMTP
4. Consider using third-party service (SendGrid, Mailgun)
