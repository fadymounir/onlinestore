<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class SalesByGovernorateChart extends ChartWidget
{
    private const ACCENT = '#d97706';

    protected ?string $heading = 'أعلى المحافظات مبيعاً';

    protected ?string $description = 'إيرادات محصّلة لكل محافظة — يساعد في تحديد أسعار الشحن.';

    protected static ?int $sort = 4;

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        // governorate_name is the order's own snapshot, so renaming or
        // deleting a governorate never rewrites past sales.
        $rows = Order::query()
            ->where('status', OrderStatus::Delivered->value)
            ->selectRaw('governorate_name, SUM(grand_total) as total')
            ->groupBy('governorate_name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'الإيرادات (ج.م)',
                    'data' => $rows->pluck('total')->map(fn ($t) => round((float) $t, 2))->all(),
                    'backgroundColor' => self::ACCENT,
                    'hoverBackgroundColor' => self::ACCENT,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                    'barPercentage' => 0.62,
                    'categoryPercentage' => 0.8,
                ],
            ],
            'labels' => $rows->pluck('governorate_name')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(120, 113, 108, 0.14)'],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
