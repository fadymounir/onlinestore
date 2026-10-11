<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SalesOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'نظرة عامة';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        // Revenue means money actually collected: these are COD orders, so
        // only a delivered order has been paid for.
        $delivered = Order::query()->where('status', OrderStatus::Delivered->value);

        $revenue = (float) (clone $delivered)->sum('grand_total');
        $revenueThisMonth = (float) (clone $delivered)->where('delivered_at', '>=', $thisMonth)->sum('grand_total');
        $revenueLastMonth = (float) (clone $delivered)
            ->whereBetween('delivered_at', [$lastMonth, $thisMonth])
            ->sum('grand_total');

        $orders = Order::count();
        $deliveredCount = (clone $delivered)->count();

        // Average basket over delivered orders only, for the same reason.
        $aov = $deliveredCount > 0 ? $revenue / $deliveredCount : 0.0;

        $pending = Order::where('status', OrderStatus::Pending->value)->count();
        $inFlight = Order::whereIn('status', [
            OrderStatus::Confirmed->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
        ])->count();

        $cancelled = Order::whereIn('status', [
            OrderStatus::Cancelled->value,
            OrderStatus::Returned->value,
        ])->count();

        $cancelRate = $orders > 0 ? round($cancelled / $orders * 100, 1) : 0.0;

        return [
            Stat::make('إيرادات محصّلة', number_format($revenue, 2).' ج.م')
                ->description($this->trend($revenueThisMonth, $revenueLastMonth))
                ->descriptionIcon(
                    $revenueThisMonth >= $revenueLastMonth
                        ? Heroicon::OutlinedArrowTrendingUp
                        : Heroicon::OutlinedArrowTrendingDown,
                )
                ->descriptionColor($revenueThisMonth >= $revenueLastMonth ? 'success' : 'danger')
                ->chart($this->revenueSparkline())
                ->chartColor('warning')
                ->icon(Heroicon::OutlinedBanknotes),

            Stat::make('متوسط قيمة الطلب', number_format($aov, 2).' ج.م')
                ->description("على {$deliveredCount} طلب مُسلَّم")
                ->icon(Heroicon::OutlinedCalculator),

            Stat::make('إجمالي الطلبات', number_format($orders))
                ->description("{$pending} قيد الانتظار · {$inFlight} جاري التنفيذ")
                ->descriptionColor($pending > 0 ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedShoppingBag),

            Stat::make('العملاء', number_format(User::count()))
                ->description(User::where('created_at', '>=', $thisMonth)->count().' عميل جديد هذا الشهر')
                ->icon(Heroicon::OutlinedUsers),

            Stat::make('نسبة الإلغاء والمرتجع', $cancelRate.'%')
                ->description("{$cancelled} من {$orders} طلب")
                ->descriptionColor($cancelRate > 15 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedArrowUturnLeft),

            Stat::make('يحتاج انتباهك', number_format($this->attentionCount()))
                ->description($this->attentionBreakdown())
                ->descriptionColor($this->attentionCount() > 0 ? 'danger' : 'success')
                ->icon(Heroicon::OutlinedExclamationTriangle),
        ];
    }

    private function trend(float $current, float $previous): string
    {
        if ($previous <= 0) {
            return $current > 0
                ? number_format($current, 2).' ج.م هذا الشهر'
                : 'لا مبيعات هذا الشهر';
        }

        $change = round(($current - $previous) / $previous * 100, 1);
        $word = $change >= 0 ? 'زيادة' : 'انخفاض';

        return abs($change).'% '.$word.' عن الشهر الماضي';
    }

    /** Last 12 weeks of collected revenue, for the sparkline. */
    private function revenueSparkline(): array
    {
        $start = Carbon::now()->subWeeks(11)->startOfWeek();

        $byWeek = Order::query()
            ->where('status', OrderStatus::Delivered->value)
            ->where('delivered_at', '>=', $start)
            ->get()
            ->groupBy(fn (Order $o) => $o->delivered_at?->startOfWeek()->toDateString())
            ->map(fn ($group) => (float) $group->sum('grand_total'));

        return collect(range(11, 0))
            ->map(fn (int $back) => $byWeek[Carbon::now()->subWeeks($back)->startOfWeek()->toDateString()] ?? 0)
            ->all();
    }

    private function attentionCount(): int
    {
        return ProductVariant::where('stock_quantity', '<=', 0)->count()
            + Review::where('is_approved', false)->count();
    }

    private function attentionBreakdown(): string
    {
        $soldOut = ProductVariant::where('stock_quantity', '<=', 0)->count();
        $pendingReviews = Review::where('is_approved', false)->count();

        return "{$soldOut} متغيّر نفد · {$pendingReviews} تقييم للمراجعة";
    }
}
