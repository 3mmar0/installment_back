<?php

namespace App\Services\Reports;

class ReportCatalog
{
    /** @var array<string, array{key: string, category: string, title: string, description: string, exports: list<string>}> */
    public const WAVE1_REPORTS = [
        'overdue' => [
            'key' => 'overdue',
            'category' => 'collections',
            'title' => 'أقساط متأخرة',
            'description' => 'عناصر أقساط تجاوزت تاريخ الاستحقاق',
            'exports' => ['csv'],
        ],
        'aging' => [
            'key' => 'aging',
            'category' => 'collections',
            'title' => 'أعمار المتأخرات',
            'description' => 'تجميع المتأخرات حسب فترات التأخير',
            'exports' => ['csv'],
        ],
        'due_soon' => [
            'key' => 'due_soon',
            'category' => 'collections',
            'title' => 'مستحق قريباً',
            'description' => 'أقساط تستحق خلال الفترة المحددة',
            'exports' => ['csv'],
        ],
    ];

    /** @var array<string, array{key: string, category: string, title: string, description: string, exports: list<string>}> */
    public const PLATFORM_REPORTS = [
        'platform_overview' => [
            'key' => 'platform_overview',
            'category' => 'platform',
            'title' => 'نظرة عامة على المنصة',
            'description' => 'إجمالي التجار والنشاط واستخدام الموارد',
            'exports' => ['csv'],
        ],
        'platform_active_merchants' => [
            'key' => 'platform_active_merchants',
            'category' => 'platform',
            'title' => 'التجار النشطون',
            'description' => 'تجار لديهم نشاط خلال آخر 7 أو 30 يوماً',
            'exports' => ['csv'],
        ],
        'platform_inactive_candidates' => [
            'key' => 'platform_inactive_candidates',
            'category' => 'platform',
            'title' => 'مرشحون للحذف / التنظيف',
            'description' => 'حسابات خاملة تستهلك موارد ويمكن مراجعتها للحذف',
            'exports' => ['csv'],
        ],
        'platform_resource_usage' => [
            'key' => 'platform_resource_usage',
            'category' => 'platform',
            'title' => 'أعلى استهلاك للموارد',
            'description' => 'ترتيب التجار حسب استخدام العملاء والأقساط',
            'exports' => ['csv'],
        ],
        'platform_subscriptions' => [
            'key' => 'platform_subscriptions',
            'category' => 'platform',
            'title' => 'توزيع الاشتراكات',
            'description' => 'عدد التجار حسب اسم الخطة وحالة الاشتراك',
            'exports' => ['csv'],
        ],
    ];

    /**
     * @return list<array{key: string, title: string, reports: list<array{key: string, title: string, description: string, category: string, exports: list<string>}>}>
     */
    public static function definitions(bool $includePlatform = false): array
    {
        $reports = self::WAVE1_REPORTS;
        if ($includePlatform) {
            $reports = array_merge(self::PLATFORM_REPORTS, $reports);
        }

        $byCategory = [];

        foreach ($reports as $report) {
            $category = $report['category'];
            if (! isset($byCategory[$category])) {
                $byCategory[$category] = [
                    'key' => $category,
                    'title' => self::categoryTitle($category),
                    'reports' => [],
                ];
            }
            $byCategory[$category]['reports'][] = $report;
        }

        return array_values($byCategory);
    }

    public static function has(string $key): bool
    {
        return isset(self::WAVE1_REPORTS[$key]) || isset(self::PLATFORM_REPORTS[$key]);
    }

    public static function isPlatform(string $key): bool
    {
        return isset(self::PLATFORM_REPORTS[$key]);
    }

    /**
     * @return array{key: string, category: string, title: string, description: string, exports: list<string>}|null
     */
    public static function find(string $key): ?array
    {
        return self::WAVE1_REPORTS[$key] ?? self::PLATFORM_REPORTS[$key] ?? null;
    }

    private static function categoryTitle(string $category): string
    {
        return match ($category) {
            'platform' => 'المنصة',
            'collections' => 'التحصيلات',
            default => $category,
        };
    }
}
