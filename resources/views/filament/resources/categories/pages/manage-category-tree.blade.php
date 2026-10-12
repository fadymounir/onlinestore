<x-filament-panels::page>
    {{-- Scoped CSS rather than utility classes: this panel uses Filament's
         precompiled stylesheet, which only contains the utilities Filament's
         own views use. The --gray-*/--primary-* variables it publishes keep
         these rules on the panel's palette. --}}
    <style>
        .ct-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding-bottom: .25rem;
        }

        .ct-hint {
            font-size: .8125rem;
            line-height: 1.6;
            color: var(--gray-500);
        }

        .ct-toolbar-buttons { display: flex; gap: .5rem; }

        .ct-list { list-style: none; margin: 0; padding: 0; }

        .ct-children {
            list-style: none;
            margin: .375rem 0 0;
            padding: 0 0 0 0;
            margin-inline-start: 1.125rem;
            padding-inline-start: 1rem;
            border-inline-start: 1px dashed var(--gray-300);
        }

        .ct-item + .ct-item { margin-top: .375rem; }

        .ct-row {
            display: flex;
            align-items: center;
            gap: .625rem;
            padding: .5rem .625rem;
            border: 1px solid var(--gray-200);
            border-radius: .625rem;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }

        .ct-row:hover { border-color: var(--primary-400); }

        .ct-item[data-depth="0"] > .ct-row {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }

        .ct-row[data-inactive="1"] {
            border-style: dashed;
            opacity: .65;
        }

        .ct-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: none;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: .375rem;
            color: var(--gray-500);
            cursor: pointer;
        }

        .ct-toggle:hover { background: var(--gray-100); color: var(--gray-700); }
        .ct-toggle svg { width: 1rem; height: 1rem; transition: transform .15s; }
        .ct-toggle[data-collapsed="1"] svg { transform: rotate(90deg); }
        [dir='rtl'] .ct-toggle[data-collapsed="1"] svg { transform: rotate(-90deg); }

        .ct-spacer { flex: none; width: 1.5rem; height: 1.5rem; }

        .ct-thumb {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: none;
            width: 2rem;
            height: 2rem;
            border-radius: .5rem;
            overflow: hidden;
            background: var(--gray-100);
            color: var(--gray-500);
            font-size: .75rem;
            font-weight: 600;
        }

        .ct-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .ct-label { min-width: 0; flex: 1 1 auto; }

        .ct-name {
            display: block;
            font-size: .875rem;
            color: var(--gray-900);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ct-item[data-depth="0"] > .ct-row .ct-name { font-weight: 700; }
        .ct-item[data-depth="1"] > .ct-row .ct-name { font-weight: 600; }

        .ct-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .375rem;
            margin-top: .125rem;
            font-size: .6875rem;
            color: var(--gray-500);
        }

        .ct-slug { direction: ltr; font-family: var(--font-mono, monospace); }

        .ct-chip {
            padding: .0625rem .375rem;
            border-radius: 9999px;
            background: var(--gray-100);
            color: var(--gray-600);
            white-space: nowrap;
        }

        .ct-chip[data-tone="off"] { background: var(--danger-50, #fef2f2); color: var(--danger-600, #dc2626); }

        .ct-actions {
            display: flex;
            align-items: center;
            gap: .125rem;
            flex: none;
            margin-inline-start: auto;
        }

        .ct-empty {
            padding: 2.5rem 1rem;
            text-align: center;
            border: 1px dashed var(--gray-300);
            border-radius: .75rem;
            color: var(--gray-500);
            font-size: .875rem;
        }

        .dark .ct-row { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .ct-item[data-depth="0"] > .ct-row { background: var(--gray-800); border-color: var(--gray-700); }
        .dark .ct-children { border-inline-start-color: var(--gray-700); }
        .dark .ct-name { color: var(--gray-100); }
        .dark .ct-thumb,
        .dark .ct-chip { background: var(--gray-800); color: var(--gray-400); }
        .dark .ct-toggle:hover { background: var(--gray-800); color: var(--gray-200); }
        .dark .ct-empty { border-color: var(--gray-700); }

        @media (max-width: 640px) {
            .ct-children { margin-inline-start: .5rem; padding-inline-start: .5rem; }
            .ct-meta { display: none; }
        }
    </style>

    @php($tree = $this->getTree())

    <div class="ct-toolbar">
        <p class="ct-hint">
            اضغط <strong>+</strong> على أي تصنيف لإضافة تصنيف فرعي تحته — بلا حد لعدد المستويات.
            الترتيب هنا هو نفس ترتيب الظهور في قائمة الموقع.
        </p>

        <div class="ct-toolbar-buttons">
            <x-filament::button size="xs" color="gray" wire:click="expandAll">
                فتح الكل
            </x-filament::button>

            <x-filament::button size="xs" color="gray" wire:click="collapseAll">
                طي الكل
            </x-filament::button>
        </div>
    </div>

    @if ($tree->isEmpty())
        <div class="ct-empty">
            لا توجد تصنيفات بعد. ابدأ بإضافة <strong>تصنيف رئيسي</strong> من الزر أعلى الصفحة.
        </div>
    @else
        <ul class="ct-list">
            @foreach ($tree as $node)
                @include('filament.resources.categories.pages.partials.category-node', [
                    'node' => $node,
                    'isFirst' => $loop->first,
                    'isLast' => $loop->last,
                ])
            @endforeach
        </ul>
    @endif
</x-filament-panels::page>
