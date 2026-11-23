<?php

namespace Sonole\GovGr\Tests;

use PHPUnit\Framework\TestCase;
use Sonole\GovGr\Core\Support\Normalize;

class NormalizeTest extends TestCase
{
    public function test_text_trims_and_turns_empty_values_into_null(): void
    {
        $this->assertSame('ΑΒΓ', Normalize::text('  ΑΒΓ '));
        $this->assertSame('0', Normalize::text(0));
        $this->assertNull(Normalize::text([]));
        $this->assertNull(Normalize::text('  '));
        $this->assertNull(Normalize::text(null));
        $this->assertSame('x', Normalize::field(['a' => ' x '], 'a'));
        $this->assertNull(Normalize::field(['a' => []], 'a'));
        $this->assertNull(Normalize::field([], 'missing'));
    }

    public function test_date_accepts_the_given_formats_and_a_time_suffix_on_y_m_d(): void
    {
        $this->assertSame('1990-01-02', Normalize::date('1990-01-02T00:00:00+02:00')?->format('Y-m-d'));
        $this->assertSame('1990-01-02', Normalize::date('02/01/1990', ['d/m/Y', 'Y-m-d'])?->format('Y-m-d'));
        $this->assertSame('1990-01-02', Normalize::date('1990-01-02', ['d/m/Y', 'Y-m-d'])?->format('Y-m-d'));
        $this->assertNull(Normalize::date('02/01/1990'));
        $this->assertNull(Normalize::date('31/02/1990', ['d/m/Y']));
        $this->assertNull(Normalize::date([]));
        $this->assertNull(Normalize::date('garbage', ['d/m/Y', 'Y-m-d']));
    }
}
