<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrdersByStatusChart extends ChartWidget
{
    private const ACCENT = '#d97706';

    protected ?string $heading = 'الطلبات حسب الحالة';

    protected ?string $description = 'توزيع الطلبات على مراحل التنفيذ.';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        // A bar chart, not a pie: these are magnitudes to compare, and the
        // axis labels carry the identity so no 7-colour palette is needed.
        return 'bar';
    }

    protected function getData(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [];
        $values = [];

        foreach (OrderStatus::cases() as $status) {
            $labels[] = $status->label();
            $values[] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'عدد الطلبات',
                    'data' => $values,
                    'backgroundColor' => self::ACCENT,
                    'hoverBackgroundColor' => self::ACCENT,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                    'barPercentage' => 0.62,
                    'categoryPercentage' => 0.8,
                ],
            ],
            'labels' => $labels,
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
                    'ticks' => ['precision' => 0],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
