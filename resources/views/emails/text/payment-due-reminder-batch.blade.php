مرحباً {{ $customer->name }}،

لديك {{ $items->count() }} دفعة قريبة الاستحقاق.
@foreach ($items as $item)
- خطة #{{ $item->installment_id }}: {{ $item->amount }}، الاستحقاق {{ \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') }}
@endforeach

إذا كنت قد دفعت بالفعل، يرجى تجاهل هذه الرسالة.
