<?php

namespace Tests\Unit;

use App\Exceptions\InvalidPhoneNumberException;
use App\Services\Phone\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    private PhoneNumberNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new PhoneNumberNormalizer(defaultCountryCode: '39');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function italianNumbers(): array
    {
        return [
            'mobile without prefix' => ['3331234567', '393331234567'],
            'mobile with spaces' => ['333 123 4567', '393331234567'],
            'mobile with dashes and dots' => ['333-123.45.67', '393331234567'],
            'mobile with +39' => ['+39 333 1234567', '393331234567'],
            'mobile with 0039' => ['0039 333 1234567', '393331234567'],
            'mobile with 39 typed without +' => ['393331234567', '393331234567'],
            'mobile starting with 393 without prefix' => ['3931234567', '393931234567'],
            'landline without prefix' => ['06 1234 5678', '390612345678'],
            'landline with brackets' => ['(02) 12345678', '390212345678'],
            'landline with +39' => ['+39 02 12345678', '390212345678'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function internationalNumbers(): array
    {
        return [
            'uk with +' => ['+44 7911 123456', '447911123456'],
            'germany with 00' => ['0049 151 23456789', '4915123456789'],
            'usa with + and brackets' => ['+1 (415) 555-2671', '14155552671'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'empty' => [''],
            'letters' => ['chiamami'],
            'digits mixed with letters' => ['333abc4567'],
            'too short' => ['12345'],
            'too long' => ['+39 3331234567890123'],
            'only a plus' => ['+'],
            'international starting with zero' => ['+0333123456'],
            'url injection attempt' => ['333123/../../evil?x=1'],
        ];
    }

    #[Test]
    #[DataProvider('italianNumbers')]
    public function it_adds_the_italian_prefix_only_when_missing(string $typed, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->toInternationalDigits($typed));
    }

    #[Test]
    #[DataProvider('internationalNumbers')]
    public function it_keeps_explicit_international_prefixes(string $typed, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->toInternationalDigits($typed));
    }

    #[Test]
    #[DataProvider('invalidNumbers')]
    public function it_rejects_invalid_numbers(string $typed): void
    {
        $this->assertFalse($this->normalizer->isValid($typed));

        $this->expectException(InvalidPhoneNumberException::class);
        $this->normalizer->toInternationalDigits($typed);
    }

    #[Test]
    public function it_formats_numbers_as_e164_for_storage(): void
    {
        $this->assertSame('+393331234567', $this->normalizer->toE164('333 123 4567'));
    }
}
