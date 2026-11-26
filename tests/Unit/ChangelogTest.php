<?php

namespace Tests\Unit;

use Tests\TestCase;

class ChangelogTest extends TestCase
{
    public function test_changelog_is_consistent(): void
    {
        $entries = config('changelog');
        $this->assertNotEmpty($entries);
        $builds = array_values(array_unique(array_column($entries, 'build')));
        $sorted = $builds;
        rsort($sorted);
        $this->assertSame($sorted, $builds, 'absteigend sortiert');
        $this->assertSame(range(max($builds), 1), $builds, 'Builds lückenlos');
        $byBuild = [];
        foreach ($entries as $e) {
            $this->assertContains($e['type'], ['new', 'improved', 'fix']);
            $this->assertNotEmpty($e['text_en'] ?? null, 'englische Fassung fehlt');
            $byBuild[$e['build']][] = $e['version'];
        }
        foreach ($byBuild as $versions) {
            $this->assertCount(1, array_unique($versions), 'ein Build = eine Version');
        }
        $this->assertSame($entries[0]['version'], \App\Support\Version::version());
    }

    public function test_portal_languages_have_same_keys(): void
    {
        $de = array_keys(require lang_path('de/portal.php'));
        foreach (['en', 'it'] as $lang) {
            $this->assertEqualsCanonicalizing($de, array_keys(require lang_path("$lang/portal.php")), $lang);
        }
    }
}
