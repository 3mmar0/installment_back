<div class="footer">
    <div class="footer-logo">{{ config('app.name') }}</div>
    <div class="footer-text">نظام إدارة التقسيط الاحترافي</div>
    <div class="copyright">
        © {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.<br>
        {{ $footerNote ?? 'تم إرسال هذا البريد الإلكتروني تلقائياً، يرجى عدم الرد عليه.' }}
    </div>
</div>
