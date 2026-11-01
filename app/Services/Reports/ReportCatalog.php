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

    /**
     * @return list<array{key: string, title: string, reports: list<array{key: string, title: string, description: string, category: string, exports: list<string>}>}>
     */
    public static function definitions(): array
    {
        $byCategory = [];

        foreach (self::WAVE1_REPORTS as $report) {
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
        return isset(self::WAVE1_REPORTS[$key]);
    }

    /**
     * @return array{key: string, category: string, title: string, description: string, exports: list<string>}|null
     */
    public static function find(string $key): ?array
    {
        return self::WAVE1_REPORTS[$key] ?? null;
    }

    private static function categoryTitle(string $category): string
    {
        return match ($category) {
            'collections' => 'التحصيلات',
            default => $category,
        };
    }
}
