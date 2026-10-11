<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RevenueChart extends ChartWidget
{
    /**
     * Amber #d97706 validated against both the light (#fcfcfb) and dark
     * (#1a1a19) chart surfaces: in the lightness band, above the chroma
     * floor, and >= 3:1 contrast on each. Filament renders chart data
     * server-side, so one hue has to serve both themes.
     */
    private const ACCENT = '#d97706';

    protected ?string $heading = 'الإيرادات المحصّلة';

    protected ?string $description = 'قيمة الطلبات المُسلَّمة — الدفع عند الاستلام يعني أن التسليم هو لحظة التحصيل.';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '280px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'آخر 7 أيام',
            '30' => 'آخر 30 يوماً',
            '90' => 'آخر 3 شهور',
            '365' => 'آخر سنة',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 30);
        $start = Carbon::today()->subDays($days - 1);

        $totals = $this->totalsByDay($start);

        // Group longer ranges by month so the axis stays readable.
        $byMonth = $days > 90;

        $labels = [];
        $values = [];

        if ($byMonth) {
            $months = (int) ceil($days / 30);

            foreach (range($months - 1, 0) as $back) {
                $month = Carbon::today()->subMonthsNoOverflow($back);
                $labels[] = $month->translatedFormat('F Y');
                $values[] = $totals
                    ->filter(fn ($v, string $date) => Carbon::parse($date)->isSameMonth($month))
                    ->sum();
            }
        } else {
            foreach (range(0, $days - 1) as $offset) {
                $date = $start->copy()->addDays($offset);
                $labels[] = $date->translatedFormat($days > 30 ? 'j M' : 'j M');
                $values[] = $totals[$date->toDateString()] ?? 0;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'الإيرادات (ج.م)',
                    'data' => $values,
                    'borderColor' => self::ACCENT,
                    'backgroundColor' => 'rgba(217, 119, 6, 0.12)',
                    'borderWidth' => 2,
                    'pointRadius' => count($values) > 45 ? 0 : 4,
                    'pointHoverRadius' => 8,
                    'pointBackgroundColor' => self::ACCENT,
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /** @return Collection<string, float> date => collected revenue */
    private function totalsByDay(Carbon $start): Collection
    {
        return Order::query()
            ->where('status', OrderStatus::Delivered->value)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', $start)
            // DATE() exists in both MySQL and SQLite, so the test suite agrees
            // with production.
            ->selectRaw('DATE(delivered_at) as day, SUM(grand_total) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total) => (float) $total);
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                // One series: the heading names it, so a legend box would
                // just repeat itself.
                'legend' => ['display' => false],
                'tooltip' => [
                    'intersect' => false,
                    'mode' => 'index',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(120, 113, 108, 0.14)'],
                    'ticks' => ['precision' => 0],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
