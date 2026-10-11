<?php

namespace Tests\Unit\Support;

use App\Support\VariantMatrix;
use PHPUnit\Framework\TestCase;

class VariantMatrixTest extends TestCase
{
    public function test_it_multiplies_two_attribute_groups(): void
    {
        $combos = VariantMatrix::combinations([[1, 2], [5, 6]]);

        $this->assertCount(4, $combos);
        $this->assertEqualsCanonicalizing(
            [[1, 5], [1, 6], [2, 5], [2, 6]],
            $combos,
        );
    }

    public function test_it_multiplies_three_groups(): void
    {
        $this->assertCount(12, VariantMatrix::combinations([[1, 2], [3, 4, 5], [6, 7]]));
    }

    public function test_an_unticked_attribute_is_skipped_rather_than_zeroing_the_result(): void
    {
        $combos = VariantMatrix::combinations([[1, 2], []]);

        $this->assertSame([[1], [2]], $combos);
    }

    public function test_no_selection_yields_nothing(): void
    {
        $this->assertSame([], VariantMatrix::combinations([[], []]));
        $this->assertSame([], VariantMatrix::combinations([]));
    }

    public function test_a_single_group_yields_one_variant_each(): void
    {
        $this->assertSame([[9], [10], [11]], VariantMatrix::combinations([[9, 10, 11]]));
    }

    public function test_skus_are_deterministic_regardless_of_value_order(): void
    {
        $this->assertSame('TEE-3-12', VariantMatrix::sku('TEE', [12, 3]));
        $this->assertSame('TEE-3-12', VariantMatrix::sku('TEE', [3, 12]));
        $this->assertSame('TEE-3-12', VariantMatrix::sku('tee-', [3, 12]));
    }
}
