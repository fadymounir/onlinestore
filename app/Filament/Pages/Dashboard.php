<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LatestOrdersTable;
use App\Filament\Widgets\LowStockTable;
use App\Filament\Widgets\OrdersByStatusChart;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\SalesByGovernorateChart;
use App\Filament\Widgets\SalesOverview;
use App\Filament\Widgets\TopProductsTable;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'لوحة التحكم';

    protected static ?string $title = 'لوحة التحكم';

    public function getSubheading(): ?string
    {
        return 'ملخص المبيعات والمخزون — '.now()->translatedFormat('l، j F Y');
    }

    public function getWidgets(): array
    {
        return [
            SalesOverview::class,
            RevenueChart::class,
            OrdersByStatusChart::class,
            TopProductsTable::class,
            SalesByGovernorateChart::class,
            LatestOrdersTable::class,
            LowStockTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'sm' => 1,
            'lg' => 3,
        ];
    }
}
