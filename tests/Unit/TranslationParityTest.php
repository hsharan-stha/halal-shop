<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

class TranslationParityTest extends TestCase
{
    public function test_japanese_and_english_translation_files_define_the_same_keys(): void
    {
        $base = dirname(__DIR__, 2).'/lang';

        foreach (glob($base.'/ja/*.php') as $jaFile) {
            $file = basename($jaFile);
            $enFile = $base.'/en/'.$file;

            $this->assertFileExists($enFile, "lang/en/{$file} is missing.");

            $ja = array_keys(Arr::dot(require $jaFile));
            $en = array_keys(Arr::dot(require $enFile));

            $this->assertSame([], array_values(array_diff($ja, $en)), "Keys missing from lang/en/{$file}");
            $this->assertSame([], array_values(array_diff($en, $ja)), "Keys missing from lang/ja/{$file}");
        }
    }
}
