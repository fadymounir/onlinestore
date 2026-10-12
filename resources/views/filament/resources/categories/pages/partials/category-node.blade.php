{{--
    One node of the category tree, rendered recursively.

    @var \App\Models\Category $node  children already nested in memory
    @var bool $isFirst               first among its siblings — cannot move up
    @var bool $isLast                last among its siblings — cannot move down
--}}
@php
    $hasChildren = $node->children->isNotEmpty();
    $isCollapsed = $this->isCollapsed($node->id);
@endphp

<li class="ct-item" data-depth="{{ min($node->depth, 2) }}" wire:key="category-node-{{ $node->id }}">
    <div class="ct-row" data-inactive="{{ $node->is_active ? '0' : '1' }}">
        @if ($hasChildren)
            <button type="button"
                    class="ct-toggle"
                    data-collapsed="{{ $isCollapsed ? '1' : '0' }}"
                    wire:click="toggleCollapsed({{ $node->id }})"
                    title="{{ $isCollapsed ? 'إظهار التصنيفات الفرعية' : 'طي التصنيفات الفرعية' }}">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
        @else
            <span class="ct-spacer"></span>
        @endif

        <span class="ct-thumb">
            @if ($node->image)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($node->image) }}"
                     alt="{{ $node->name }}">
            @else
                {{ mb_substr($node->name, 0, 1) }}
            @endif
        </span>

        <span class="ct-label">
            <span class="ct-name">{{ $node->name }}</span>
            <span class="ct-meta">
                <span class="ct-slug">/{{ $node->slug }}</span>
                @if ($hasChildren)
                    <span class="ct-chip">{{ $node->children->count() }} فرعي</span>
                @endif
                @unless ($node->is_active)
                    <span class="ct-chip" data-tone="off">موقوف</span>
                @endunless
            </span>
        </span>

        <span class="ct-actions">
            {{ ($this->createChildAction)(['category' => $node->id]) }}
            {{ ($this->editAction)(['category' => $node->id]) }}
            {{ ($this->moveAction)(['category' => $node->id]) }}
            {{ ($this->toggleActiveAction)(['category' => $node->id]) }}

            @if ($isFirst)
                <span class="ct-spacer"></span>
            @else
                {{ ($this->moveUpAction)(['category' => $node->id]) }}
            @endif

            @if ($isLast)
                <span class="ct-spacer"></span>
            @else
                {{ ($this->moveDownAction)(['category' => $node->id]) }}
            @endif

            {{ ($this->deleteAction)(['category' => $node->id]) }}
        </span>
    </div>

    @if ($hasChildren && ! $isCollapsed)
        <ul class="ct-children">
            @foreach ($node->children as $child)
                @include('filament.resources.categories.pages.partials.category-node', [
                    'node' => $child,
                    'isFirst' => $loop->first,
                    'isLast' => $loop->last,
                ])
            @endforeach
        </ul>
    @endif
</li>
