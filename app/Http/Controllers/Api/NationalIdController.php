<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\Assistant\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NationalIdController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly GeminiClient $gemini,
    ) {}

    /**
     * Extract customer data from national ID card images using Gemini vision.
     * Accepts: front_image (required), back_image (optional).
     * Returns: name, national_id, address, governorate, job, gender, date_of_birth.
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'front_image' => ['required', 'image', 'max:10240'],
            'back_image'  => ['nullable', 'image', 'max:10240'],
        ]);

        $parts = [];

        $frontFile = $request->file('front_image');
        $frontData = base64_encode((string) file_get_contents($frontFile->getRealPath()));
        $frontMime = $frontFile->getMimeType() ?: 'image/jpeg';
        $parts[] = ['inline_data' => ['mime_type' => $frontMime, 'data' => $frontData]];

        if ($request->hasFile('back_image')) {
            $backFile = $request->file('back_image');
            $backData = base64_encode((string) file_get_contents($backFile->getRealPath()));
            $backMime = $backFile->getMimeType() ?: 'image/jpeg';
            $parts[] = ['inline_data' => ['mime_type' => $backMime, 'data' => $backData]];
        }

        $prompt = <<<'PROMPT'
أنت نظام استخراج بيانات من بطاقة الرقم القومي المصرية.
استخرج البيانات التالية من صورة البطاقة وأعدها كـ JSON صالح فقط بدون أي نص إضافي:

{
  "name": "الاسم الرباعي كاملاً بالعربية",
  "national_id": "الرقم القومي 14 رقماً",
  "address": "العنوان كاملاً",
  "governorate": "اسم المحافظة بالعربية فقط (مثل: القاهرة، الجيزة، الإسكندرية)",
  "job": "المهنة",
  "gender": "male أو female",
  "date_of_birth": "YYYY-MM-DD"
}

إذا لم تتمكن من قراءة قيمة معينة اجعلها null.
أعد JSON فقط بدون markdown أو نص إضافي.
PROMPT;

        $parts[] = ['text' => $prompt];

        $contents = [['role' => 'user', 'parts' => $parts]];

        try {
            $raw = $this->gemini->generateContent($contents);
            $text = data_get($raw, 'candidates.0.content.parts.0.text', '');
            $text = trim((string) $text);

            // Strip markdown code fences if present
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/i', '', $text) ?? $text;
            $text = trim($text);

            $decoded = json_decode($text, true);

            if (! is_array($decoded)) {
                Log::warning('national_id_extract_bad_json', ['raw' => $text]);

                return $this->errorResponse('تعذّر استخراج البيانات من الصورة، يرجى المحاولة بصورة أوضح', 422);
            }

            $governorate = $this->normalizeGovernorate($decoded['governorate'] ?? null);

            return $this->successResponse([
                'name'          => isset($decoded['name']) && is_string($decoded['name']) ? trim($decoded['name']) : null,
                'national_id'   => isset($decoded['national_id']) && is_string($decoded['national_id']) ? trim($decoded['national_id']) : null,
                'address'       => isset($decoded['address']) && is_string($decoded['address']) ? trim($decoded['address']) : null,
                'governorate'   => $governorate,
                'job'           => isset($decoded['job']) && is_string($decoded['job']) ? trim($decoded['job']) : null,
                'gender'        => isset($decoded['gender']) && is_string($decoded['gender']) ? $decoded['gender'] : null,
                'date_of_birth' => isset($decoded['date_of_birth']) && is_string($decoded['date_of_birth']) ? $decoded['date_of_birth'] : null,
            ], 'تم استخراج البيانات بنجاح');

        } catch (\Throwable $e) {
            Log::error('national_id_extract_failed', ['error' => $e->getMessage()]);

            return $this->errorResponse('فشل الاتصال بخدمة الاستخراج، يرجى المحاولة لاحقاً', 503);
        }
    }

    private function normalizeGovernorate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $map = [
            'القاهرة'          => 'cairo',
            'الجيزة'           => 'giza',
            'الإسكندرية'       => 'alexandria',
            'اسكندرية'         => 'alexandria',
            'الدقهلية'         => 'dakahlia',
            'البحر الأحمر'     => 'red_sea',
            'البحيرة'          => 'beheira',
            'الفيوم'           => 'fayoum',
            'الغربية'          => 'gharbia',
            'الإسماعيلية'      => 'ismailia',
            'المنوفية'         => 'menofia',
            'المنيا'           => 'minya',
            'القليوبية'        => 'qalyubia',
            'الوادي الجديد'    => 'new_valley',
            'السويس'           => 'suez',
            'اسوان'            => 'aswan',
            'أسوان'            => 'aswan',
            'اسيوط'            => 'asyut',
            'أسيوط'            => 'asyut',
            'بني سويف'         => 'beni_suef',
            'بورسعيد'          => 'port_said',
            'دمياط'            => 'damietta',
            'الشرقية'          => 'sharqia',
            'جنوب سيناء'       => 'south_sinai',
            'كفر الشيخ'        => 'kafr_el_sheikh',
            'مطروح'            => 'matruh',
            'الأقصر'           => 'luxor',
            'قنا'              => 'qena',
            'شمال سيناء'       => 'north_sinai',
            'سوهاج'            => 'sohag',
        ];

        return $map[trim($value)] ?? null;
    }
}
