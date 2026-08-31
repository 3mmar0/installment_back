<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
        line-height: 1.8;
        color: #1e293b;
        background-color: #f1f5f9;
        direction: rtl;
        text-align: right;
        unicode-bidi: embed;
    }

    .email-container {
        max-width: 600px;
        margin: 20px auto;
        background-color: #ffffff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        direction: rtl;
        text-align: right;
    }

    .header {
        background: linear-gradient(135deg, #1B4F9C 0%, #163f7d 100%);
        color: #ffffff;
        padding: 36px 28px;
        text-align: center;
    }

    .header--warning {
        background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
    }

    .header--danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .header--success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .header-logo {
        max-height: 64px;
        width: auto;
        margin-bottom: 12px;
    }

    .header-title {
        font-size: 26px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .header-subtitle {
        font-size: 15px;
        opacity: 0.92;
    }

    .content {
        padding: 32px 28px;
        direction: rtl;
        text-align: right;
    }

    .greeting {
        font-size: 18px;
        color: #0f172a;
        margin-bottom: 16px;
        font-weight: 700;
    }

    .lead {
        margin-bottom: 20px;
        color: #475569;
        font-size: 15px;
    }

    .info-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px 20px;
        margin: 20px 0;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 14px 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        color: #64748b;
        font-weight: 500;
        font-size: 14px;
    }

    .info-value {
        color: #0f172a;
        font-weight: 700;
        font-size: 15px;
        text-align: left;
        direction: ltr;
        unicode-bidi: isolate;
    }

    .info-value--accent {
        color: #1B4F9C;
    }

    .section-title {
        color: #0f172a;
        margin: 28px 0 14px;
        font-size: 18px;
        font-weight: 700;
    }

    .payment-table {
        width: 100%;
        border-collapse: collapse;
        margin: 16px 0 24px;
        direction: rtl;
    }

    .payment-table th,
    .payment-table td {
        padding: 12px 10px;
        text-align: right;
        border-bottom: 1px solid #e2e8f0;
        font-size: 14px;
    }

    .payment-table th {
        background: #f8fafc;
        font-weight: 700;
        color: #475569;
    }

    .payment-table td.amount {
        font-weight: 700;
        color: #1B4F9C;
        direction: ltr;
        unicode-bidi: isolate;
        text-align: left;
    }

    .payment-table tr:last-child td {
        border-bottom: none;
    }

    .highlight-box {
        background: linear-gradient(135deg, #1B4F9C 0%, #163f7d 100%);
        color: #ffffff;
        padding: 28px 24px;
        border-radius: 12px;
        margin: 24px 0;
        text-align: center;
    }

    .highlight-label {
        font-size: 14px;
        opacity: 0.92;
        margin-bottom: 8px;
    }

    .highlight-amount {
        font-size: 34px;
        font-weight: 800;
        margin: 8px 0;
        direction: ltr;
        unicode-bidi: isolate;
    }

    .highlight-date {
        font-size: 15px;
        opacity: 0.95;
    }

    .notice-box {
        background: #fffbeb;
        border-right: 4px solid #f59e0b;
        padding: 18px 20px;
        border-radius: 10px;
        margin: 24px 0;
    }

    .notice-box--success {
        background: #ecfdf5;
        border-right-color: #10b981;
    }

    .notice-box--danger {
        background: #fef2f2;
        border-right-color: #ef4444;
    }

    .notice-title {
        font-size: 15px;
        font-weight: 700;
        color: #92400e;
        margin-bottom: 8px;
    }

    .notice-box--success .notice-title {
        color: #065f46;
    }

    .notice-box--danger .notice-title {
        color: #991b1b;
    }

    .notice-list {
        color: #78350f;
        font-size: 14px;
        line-height: 1.9;
        list-style: none;
        padding: 0;
    }

    .notice-box--success .notice-list {
        color: #065f46;
    }

    .notice-box--danger .notice-list {
        color: #991b1b;
    }

    .payment-card {
        background: #ffffff;
        border: 2px solid #f59e0b;
        border-radius: 12px;
        padding: 28px 24px;
        text-align: center;
        margin: 24px 0;
    }

    .payment-card--danger {
        border-color: #ef4444;
    }

    .payment-card-label {
        color: #92400e;
        font-size: 14px;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .payment-card--danger .payment-card-label {
        color: #991b1b;
    }

    .payment-card-amount {
        font-size: 38px;
        font-weight: 800;
        color: #f59e0b;
        margin: 10px 0;
        direction: ltr;
        unicode-bidi: isolate;
    }

    .payment-card--danger .payment-card-amount {
        color: #ef4444;
    }

    .payment-card-details {
        color: #64748b;
        margin-top: 12px;
        font-size: 14px;
        line-height: 1.8;
    }

    .receipt-box {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        padding: 28px 24px;
        border-radius: 12px;
        margin: 24px 0;
        text-align: center;
    }

    .receipt-amount {
        font-size: 38px;
        font-weight: 800;
        margin: 10px 0;
        direction: ltr;
        unicode-bidi: isolate;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin: 20px 0;
    }

    .info-item {
        background: #f8fafc;
        padding: 16px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .info-item .info-value {
        display: block;
        text-align: right;
        direction: rtl;
        unicode-bidi: embed;
        margin-top: 6px;
    }

    .info-item .info-value.success {
        color: #059669;
    }

    .info-item .info-value--money {
        color: #1B4F9C;
        direction: ltr;
        unicode-bidi: isolate;
        text-align: left;
    }

    .info-item .info-value--date {
        direction: ltr;
        unicode-bidi: isolate;
        text-align: left;
        font-variant-numeric: tabular-nums;
    }

    .plan-summary-hero {
        background: linear-gradient(135deg, #1B4F9C 0%, #163f7d 100%);
        color: #ffffff;
        padding: 28px 24px;
        border-radius: 12px;
        margin: 0 0 16px;
        text-align: center;
    }

    .plan-summary-hero-label {
        font-size: 14px;
        opacity: 0.92;
        margin-bottom: 8px;
    }

    .plan-summary-hero-amount {
        font-size: 36px;
        font-weight: 800;
        margin: 8px 0;
        direction: ltr;
        unicode-bidi: isolate;
    }

    .plan-summary-hero-meta {
        font-size: 15px;
        opacity: 0.95;
        margin-top: 10px;
        line-height: 1.7;
    }

    .plan-summary-hero-id {
        display: inline-block;
        margin-top: 14px;
        padding: 6px 14px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.16);
        font-size: 13px;
        font-weight: 600;
    }

    .footer {
        background-color: #0f172a;
        color: #ffffff;
        padding: 28px;
        text-align: center;
    }

    .footer-logo {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .footer-text {
        font-size: 13px;
        opacity: 0.82;
        margin-bottom: 12px;
    }

    .copyright {
        font-size: 12px;
        opacity: 0.65;
        border-top: 1px solid rgba(255, 255, 255, 0.15);
        padding-top: 16px;
        margin-top: 16px;
        line-height: 1.8;
    }

    .product-list-item {
        padding: 8px 0;
        color: #475569;
        font-size: 14px;
    }

    @media (max-width: 600px) {
        .email-container {
            margin: 10px;
            border-radius: 12px;
        }

        .header,
        .content,
        .footer {
            padding: 20px 16px;
        }

        .highlight-amount,
        .payment-card-amount,
        .receipt-amount,
        .plan-summary-hero-amount {
            font-size: 28px;
        }

        .header-title {
            font-size: 22px;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
