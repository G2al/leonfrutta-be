<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstallableAppTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_manifest_makes_the_admin_panel_installable(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJson([
                'name' => 'LeonFrutta Admin',
                'short_name' => 'LeonFrutta',
                'start_url' => '/admin',
                'scope' => '/',
                'display' => 'standalone',
                'lang' => 'it',
            ]);

        $icons = collect($response->json('icons'));

        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['sizes'] === '192x192' && $icon['purpose'] === 'any'));
        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['sizes'] === '512x512' && $icon['purpose'] === 'any'));
        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['purpose'] === 'maskable'));

        foreach ($icons as $icon) {
            $this->assertIconFile($icon['src'], $icon['sizes']);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function staticFiles(): array
    {
        return [
            'service worker' => ['sw.js', "addEventListener('fetch'"],
            'offline page' => ['pwa/offline.html', 'Sei offline'],
        ];
    }

    #[Test]
    #[DataProvider('staticFiles')]
    public function the_offline_support_files_exist(string $path, string $expectedContent): void
    {
        $this->assertFileExists(public_path($path));
        $this->assertStringContainsString($expectedContent, file_get_contents(public_path($path)));
    }

    #[Test]
    public function the_iphone_home_screen_icon_exists(): void
    {
        $this->assertIconFile('/pwa/icons/apple-touch-icon.png', '180x180');
    }

    #[Test]
    public function the_admin_pages_link_the_manifest_and_block_zoom(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('<link rel="manifest" href="'.route('pwa.manifest').'">', escape: false)
            ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', escape: false)
            ->assertSee('apple-touch-icon.png')
            ->assertSee('maximum-scale=1, user-scalable=no', escape: false)
            ->assertSee("navigator.serviceWorker.register('/sw.js'", escape: false)
            ->assertSee('beforeinstallprompt')
            ->assertSee('id="lf-install-banner"', escape: false)
            ->assertSee('Aggiungi alla schermata Home');
    }

    private function assertIconFile(string $src, string $sizes): void
    {
        $path = public_path(ltrim($src, '/'));

        $this->assertFileExists($path);

        [$width, $height, $type] = getimagesize($path);

        $this->assertSame(IMAGETYPE_PNG, $type);
        $this->assertSame($sizes, "{$width}x{$height}");
    }
}
