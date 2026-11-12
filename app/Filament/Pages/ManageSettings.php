<?php

namespace App\Filament\Pages;

use App\Console\Commands\BuildPwaManifest;
use App\Models\Setting;
use BackedEnum;
use Database\Seeders\SettingSeeder;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
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
        'payments' => ['label' => 'طرق الدفع', 'icon' => 'heroicon-o-credit-card'],
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
                            // mount() puts the saved values back into the
                            // component's state, but some controls do not
                            // read that state again: the logo's upload box
                            // is FilePond, which keeps its own copy in
                            // Alpine and ignores a server-side refill, so a
                            // picked-but-unsaved file survived the restore.
                            // Reloading the page is the only way to show
                            // what is actually saved, in every field at
                            // once. The URL carries the open tab, so the
                            // page comes back where it was.
                            ->action(function (): void {
                                $this->mount();

                                $this->js('window.location.reload()');
                            }),
                    ])->sticky(),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        // Ordered by the seeder's declaration rather than by id — see
        // SettingSeeder::declaredOrder(). A row it does not know about (a
        // future seeder, a hand-added key) keeps its place at the end.
        $declared = array_flip(SettingSeeder::declaredOrder());

        $settings = Setting::query()
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Setting $setting): int => $declared[$setting->key] ?? PHP_INT_MAX)
            ->values()
            ->groupBy('group');

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

            'image' => Group::make([
                $this->inEffect($setting),
                FileUpload::make($setting->key)
                    ->label($setting->label)
                    ->image()
                    ->imageEditor()
                    ->directory('settings')
                    ->disk('public'),
            ])->columnSpanFull(),

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

    /**
     * What an image setting is serving right now, shown above its upload box.
     *
     * An empty FileUpload reads as "no image", which is wrong for the logo:
     * with nothing uploaded the storefront wears the badge bundled in
     * public/images, so the control sits blank while the shop is anything
     * but. This fills that silence, and only appears when the control itself
     * is empty — once a file is uploaded the upload box previews it and a
     * second copy would say nothing.
     */
    private function inEffect(Setting $setting): Placeholder
    {
        $isLogo = $setting->key === 'store_logo';

        $url = match (true) {
            $isLogo => Setting::logoUrl(),
            filled($setting->value) => Storage::disk('public')->url($setting->value),
            default => null,
        };

        $image = '<img src="'.e((string) $url).'" alt="" width="96" height="96" style="width:6rem;'
            .'height:6rem;border-radius:9999px;object-fit:cover;background:#faf8f5;'
            .'box-shadow:0 0 0 1px rgb(0 0 0 / 0.1)">';

        $note = $isLogo
            ? '<p style="margin-top:.5rem;font-size:.75rem;line-height:1.7;opacity:.7">'
                .e('الشعار المرفق مع المتجر. ارفع ملفاً هنا ليحلّ محلّه في المتجر وفي أيقونة المتصفح.')
                .'</p>'
            : '';

        return Placeholder::make($setting->key.'_in_effect')
            ->label('المستخدم حالياً')
            ->content(new HtmlString($image.$note))
            ->visible(blank($setting->value) && filled($url))
            ->columnSpanFull();
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

        $this->writeWebManifest();

        Notification::make()
            ->title('تم حفظ الإعدادات')
            ->success()
            ->send();
    }

    /**
     * Keeps public/manifest.json in step with the shop's name.
     *
     * That file is what a phone reads when the storefront is installed to a
     * home screen, so the name in it is the name under the icon. It is a
     * static file — a manifest is fetched without the page's cookies and has
     * to stay readable when PHP is not — which means a rename here has to be
     * written through to disk, or the installed app keeps the old name for
     * good.
     *
     * A failure is reported but not thrown: the settings are already saved by
     * the time this runs, and public/ being read-only on a locked-down deploy
     * is not a reason to tell an admin their edit did not take. `php artisan
     * pwa:manifest` catches it up once the file is writable.
     */
    private function writeWebManifest(): void
    {
        if (@file_put_contents(BuildPwaManifest::path(), BuildPwaManifest::build()) !== false) {
            return;
        }

        Notification::make()
            ->title('تعذّر تحديث ملف التطبيق (manifest.json)')
            ->body('الإعدادات محفوظة، لكن اسم التطبيق المثبّت لن يتغيّر حتى يصبح مجلد public قابلاً للكتابة.')
            ->warning()
            ->send();
    }
}
