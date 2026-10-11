<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * One editable form over the whole `settings` table. Fields are built from
 * each row's `type` and grouped by its `group`, so adding a setting is a
 * seeder row — not a code change here.
 */
class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'إدارة النظام';

    protected static ?string $navigationLabel = 'إعدادات المتجر';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'إعدادات المتجر';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Arabic headings for the groups stored on each row. */
    private const GROUPS = [
        'general' => ['label' => 'عام', 'icon' => 'heroicon-o-building-storefront'],
        'contact' => ['label' => 'بيانات التواصل', 'icon' => 'heroicon-o-phone'],
        'social' => ['label' => 'السوشيال ميديا', 'icon' => 'heroicon-o-share'],
        'checkout' => ['label' => 'الشراء والدفع', 'icon' => 'heroicon-o-shopping-cart'],
        'inventory' => ['label' => 'المخزون', 'icon' => 'heroicon-o-archive-box'],
        'reviews' => ['label' => 'التقييمات', 'icon' => 'heroicon-o-star'],
    ];

    public function mount(): void
    {
        $this->form->fill(
            Setting::query()->pluck('value', 'key')->all(),
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    SchemaActions::make([
                        Action::make('save')
                            ->label('حفظ الإعدادات')
                            ->submit('save')
                            ->keyBindings(['mod+s']),

                        Action::make('reload')
                            ->label('استعادة القيم المحفوظة')
                            ->color('gray')
                            ->action(fn () => $this->mount()),
                    ])->sticky(),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = Setting::query()->orderBy('id')->get()->groupBy('group');

        $tabs = [];

        // Render the known groups in a deliberate order, then anything else
        // a future seeder adds, so no setting can go missing from this page.
        $order = array_keys(self::GROUPS);
        $keys = $settings->keys()
            ->sortBy(fn (string $group): int => array_search($group, $order, true) === false
                ? PHP_INT_MAX
                : array_search($group, $order, true));

        foreach ($keys as $group) {
            $tabs[] = Tab::make(self::GROUPS[$group]['label'] ?? $group)
                ->icon(self::GROUPS[$group]['icon'] ?? 'heroicon-o-adjustments-horizontal')
                ->schema(
                    $settings[$group]->map(fn (Setting $setting): Component => $this->field($setting))->all(),
                )
                ->columns(2);
        }

        return $schema
            ->components([Tabs::make('settings')->tabs($tabs)->persistTabInQueryString()])
            ->statePath('data');
    }

    /** Builds the right control for a setting's declared type. */
    private function field(Setting $setting): Component
    {
        return match ($setting->type) {
            'boolean' => Toggle::make($setting->key)
                ->label($setting->label)
                ->inline(false),

            'number' => TextInput::make($setting->key)
                ->label($setting->label)
                ->numeric()
                ->minValue(0),

            'text' => Textarea::make($setting->key)
                ->label($setting->label)
                ->rows(3)
                ->columnSpanFull(),

            'image' => FileUpload::make($setting->key)
                ->label($setting->label)
                ->image()
                ->imageEditor()
                ->directory('settings')
                ->disk('public'),

            'json' => Textarea::make($setting->key)
                ->label($setting->label)
                ->rows(4)
                ->columnSpanFull()
                ->rules(['nullable', 'json'])
                ->helperText('صيغة JSON صحيحة.'),

            default => TextInput::make($setting->key)
                ->label($setting->label)
                ->maxLength(255),
        };
    }

    public function save(): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data): void {
            foreach (Setting::query()->get() as $setting) {
                if (! array_key_exists($setting->key, $data)) {
                    continue;
                }

                $value = $data[$setting->key];

                // Toggles must persist as "1"/"0" so Setting::get() stays
                // truthy/falsy for the storefront's @if checks.
                if ($setting->type === 'boolean') {
                    $value = $value ? '1' : '0';
                }

                $setting->update(['value' => $value === '' ? null : $value]);
            }
        });

        Notification::make()
            ->title('تم حفظ الإعدادات')
            ->success()
            ->send();
    }
}
