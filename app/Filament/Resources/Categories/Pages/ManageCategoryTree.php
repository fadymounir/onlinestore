<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Schemas\CategoryNodeForm;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Kalnoy\Nestedset\Collection as NodeCollection;

/**
 * Tree editor for the category hierarchy.
 *
 * Reading is one query: the nested set comes back flat with a `depth` attribute
 * and is linked into a tree in memory, so nesting is not limited by the number
 * of eager loads. Writing always goes through kalnoy/nestedset's own moves
 * (appendToNode / makeRoot / up / down) so `_lft`/`_rgt` stay consistent.
 */
class ManageCategoryTree extends Page
{
    protected static string $resource = CategoryResource::class;

    protected string $view = 'filament.resources.categories.pages.manage-category-tree';

    protected static ?string $title = 'شجرة التصنيفات';

    /** Ids whose children are folded away. */
    public array $collapsed = [];

    /** @return NodeCollection<int, Category> roots, children nested all the way down */
    public function getTree(): NodeCollection
    {
        return Category::tree();
    }

    public function isCollapsed(int $id): bool
    {
        return in_array($id, $this->collapsed, true);
    }

    public function toggleCollapsed(int $id): void
    {
        $this->collapsed = $this->isCollapsed($id)
            ? array_values(array_diff($this->collapsed, [$id]))
            : [...$this->collapsed, $id];
    }

    public function expandAll(): void
    {
        $this->collapsed = [];
    }

    public function collapseAll(): void
    {
        // Only branches that actually have children are worth folding.
        $this->collapsed = Category::query()->hasChildren()->pluck('id')->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createRootAction(),

            Action::make('openTable')
                ->label('العرض كجدول')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(CategoryResource::getUrl('list')),
        ];
    }

    /*
    |---------------------------------------------------------------------------
    | Actions — each is rendered per node and invoked with that node's id
    |---------------------------------------------------------------------------
    */

    public function createRootAction(): Action
    {
        return Action::make('createRoot')
            ->label('تصنيف رئيسي جديد')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('إضافة تصنيف رئيسي')
            ->modalWidth(Width::Large)
            ->model(Category::class)
            ->schema(CategoryNodeForm::fields())
            ->action(function (array $data): void {
                $category = new Category($data);
                $category->saveAsRoot();

                Notification::make()->title('تمت إضافة التصنيف الرئيسي')->success()->send();
            });
    }

    public function createChildAction(): Action
    {
        return Action::make('createChild')
            ->label('إضافة تصنيف فرعي')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->iconButton()
            ->color('primary')
            ->tooltip('إضافة تصنيف فرعي')
            ->modalHeading(fn (array $arguments): string => 'إضافة تحت: '.$this->node($arguments)->breadcrumbLabel())
            ->modalWidth(Width::Large)
            ->model(Category::class)
            ->schema(CategoryNodeForm::fields())
            ->action(function (array $arguments, array $data): void {
                $parent = $this->node($arguments);

                // Appends last among its siblings.
                (new Category($data))->appendToNode($parent)->save();

                // A new child is invisible if its parent is folded shut.
                $this->collapsed = array_values(array_diff($this->collapsed, [$parent->getKey()]));

                Notification::make()->title('تمت إضافة التصنيف الفرعي')->success()->send();
            });
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->label('تعديل')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()
            ->color('gray')
            ->tooltip('تعديل')
            ->modalHeading(fn (array $arguments): string => 'تعديل: '.$this->node($arguments)->name)
            ->modalWidth(Width::Large)
            ->record(fn (array $arguments): Category => $this->node($arguments))
            ->fillForm(fn (array $arguments): array => $this->node($arguments)->only([
                'name', 'slug', 'description', 'image', 'is_active',
            ]))
            ->schema(CategoryNodeForm::fields())
            ->action(function (array $arguments, array $data): void {
                $this->node($arguments)->update($data);

                Notification::make()->title('تم تحديث التصنيف')->success()->send();
            });
    }

    public function moveAction(): Action
    {
        return Action::make('move')
            ->label('نقل')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->iconButton()
            ->color('gray')
            ->tooltip('نقل إلى تصنيف آخر')
            ->modalHeading(fn (array $arguments): string => 'نقل: '.$this->node($arguments)->name)
            ->modalSubmitActionLabel('نقل')
            ->modalWidth(Width::Large)
            ->schema(fn (array $arguments): array => [
                Select::make('parent_id')
                    ->label('التصنيف الأب الجديد')
                    ->placeholder('— تصنيف رئيسي (بلا أب) —')
                    ->searchable()
                    ->options(fn (): array => CategoryNodeForm::parentOptions(
                        // Moving a branch into itself would detach it from the tree.
                        exclude: $this->node($arguments)->subtreeIds(),
                    ))
                    ->helperText('اتركه فارغاً لرفعه إلى أعلى مستوى في القائمة.'),
            ])
            ->fillForm(fn (array $arguments): array => ['parent_id' => $this->node($arguments)->parent_id])
            ->action(function (array $arguments, array $data): void {
                $node = $this->node($arguments);
                $parentId = filled($data['parent_id']) ? (int) $data['parent_id'] : null;

                if (! $node->canMoveTo($parentId)) {
                    Notification::make()
                        ->title('نقل غير ممكن')
                        ->body('لا يمكن نقل التصنيف إلى داخل نفسه أو إلى أحد تصنيفاته الفرعية.')
                        ->danger()
                        ->send();

                    return;
                }

                $parentId === null
                    ? $node->makeRoot()->save()
                    : $node->appendToNode(Category::findOrFail($parentId))->save();

                Notification::make()->title('تم نقل التصنيف')->success()->send();
            });
    }

    public function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (array $arguments): string => $this->node($arguments)->is_active ? 'إيقاف' : 'تفعيل')
            ->icon(fn (array $arguments) => $this->node($arguments)->is_active
                ? Heroicon::OutlinedEyeSlash
                : Heroicon::OutlinedEye)
            ->iconButton()
            ->color(fn (array $arguments): string => $this->node($arguments)->is_active ? 'gray' : 'warning')
            ->tooltip(fn (array $arguments): string => $this->node($arguments)->is_active
                ? 'إيقاف (يخفيه هو وكل ما تحته)'
                : 'تفعيل')
            ->action(function (array $arguments): void {
                $node = $this->node($arguments);

                $node->update(['is_active' => ! $node->is_active]);

                Notification::make()
                    ->title($node->is_active ? 'تم تفعيل التصنيف' : 'تم إيقاف التصنيف')
                    ->success()
                    ->send();
            });
    }

    public function moveUpAction(): Action
    {
        return Action::make('moveUp')
            ->label('لأعلى')
            ->icon(Heroicon::OutlinedChevronUp)
            ->iconButton()
            ->color('gray')
            ->tooltip('تحريك لأعلى بين أشقائه')
            ->action(fn (array $arguments) => $this->node($arguments)->up());
    }

    public function moveDownAction(): Action
    {
        return Action::make('moveDown')
            ->label('لأسفل')
            ->icon(Heroicon::OutlinedChevronDown)
            ->iconButton()
            ->color('gray')
            ->tooltip('تحريك لأسفل بين أشقائه')
            ->action(fn (array $arguments) => $this->node($arguments)->down());
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('حذف')
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->color('danger')
            ->tooltip('حذف')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'حذف: '.$this->node($arguments)->name)
            ->modalDescription(function (array $arguments): string {
                $descendants = $this->node($arguments)->getDescendantCount();

                return $descendants > 0
                    ? "سيُحذف التصنيف مع {$descendants} تصنيف فرعي تحته. لا يمكن الرجوع في هذا الإجراء."
                    : 'لا يمكن الرجوع في هذا الإجراء.';
            })
            ->action(function (array $arguments): void {
                $node = $this->node($arguments);

                // products.category_id is restrictOnDelete, so refuse clearly
                // instead of letting the database throw — and check the whole
                // subtree, because deleting a node deletes everything under it.
                if (($products = $node->subtreeProductsCount()) > 0) {
                    Notification::make()
                        ->title('لا يمكن حذف التصنيف')
                        ->body("يوجد {$products} منتج في هذا التصنيف أو في تصنيفاته الفرعية. انقل المنتجات أولاً.")
                        ->danger()
                        ->send();

                    return;
                }

                $node->delete();

                Notification::make()->title('تم حذف التصنيف')->success()->send();
            });
    }

    private function node(array $arguments): Category
    {
        return Category::findOrFail($arguments['category']);
    }
}
