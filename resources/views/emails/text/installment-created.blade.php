مرحباً {{ $installment->customer?->name ?? 'بك' }}،

تم إنشاء خطة تقسيط جديدة. يمكنك مراجعة جدول الدفع عبر {{ config('app.name') }}.

للمساعدة، تواصل معنا على {{ config('mail.reply_to.address') ?: config('mail.from.address') }}.
