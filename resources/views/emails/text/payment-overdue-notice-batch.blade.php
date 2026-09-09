مرحباً {{ $customer->name }}،

لديك {{ $items->count() }} دفعة متأخرة.
@foreach ($items as $item)
- خطة #{{ $item->installment_id }}: {{ $item->amount }}، كان الاستحقاق {{ \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') }}
@endforeach

إذا كنت قد دفعت بالفعل، تواصل معنا لتحديث حسابك.
