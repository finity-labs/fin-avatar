<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // 1. Setup: Ensure the directory exists where the command looks
    // The command uses app_path('Providers/Filament/XPanelProvider.php')
    $this->directory = app_path('Providers/Filament');

    if (! is_dir($this->directory)) {
        mkdir($this->directory, 0777, true);
    }

    // We use 'AdminPanelProvider' because the ID is 'admin'
    // 'admin' -> studly -> 'Admin' + 'PanelProvider' -> 'AdminPanelProvider'
    $this->providerPath = $this->directory . '/AdminPanelProvider.php';

    // Helper to generate initial content
    $this->createProvider = function ($extraContent = '') {
        $content = <<<PHP
        <?php

        namespace App\Providers\Filament;

        use Filament\Panel;
        use Filament\PanelProvider;

        class AdminPanelProvider extends PanelProvider
        {
            public function panel(Panel \$panel): Panel
            {
                return \$panel
                    ->id('admin')
                    {$extraContent}
                    ->path('admin');
            }
        }
        PHP;
        file_put_contents($this->providerPath, $content);
    };
});

afterEach(function () {
    // Cleanup: Delete the specific file we created
    if (file_exists($this->providerPath)) {
        unlink($this->providerPath);
    }
    Mockery::close();
});

it('installs the avatar provider into a fresh panel', function () {
    // Setup fresh file
    ($this->createProvider)();

    // Mock Filament
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('getId')->andReturn('admin');

    Filament::shouldReceive('getPanels')->andReturn(['admin' => $mockPanel]);
    Filament::shouldReceive('getPanel')->with('admin')->andReturn($mockPanel);

    // Run Command
    $this->artisan('fin-avatar:install', ['panels' => ['admin']])
        ->assertSuccessful();

    $content = file_get_contents($this->providerPath);

    // Assertions
    expect($content)->toContain('use FinityLabs\FinAvatar\AvatarProviders\UiAvatarsProvider;');
    expect($content)->toContain('->defaultAvatarProvider(UiAvatarsProvider::class)');
});

it('replaces an existing different avatar provider if found', function () {
    // Setup file with an OLD provider
    ($this->createProvider)('->defaultAvatarProvider(OldProvider::class)');

    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('getId')->andReturn('admin');

    Filament::shouldReceive('getPanels')->andReturn(['admin' => $mockPanel]);
    Filament::shouldReceive('getPanel')->with('admin')->andReturn($mockPanel);

    $this->artisan('fin-avatar:install', ['panels' => ['admin']])
        ->assertSuccessful();

    $content = file_get_contents($this->providerPath);

    // Assert Old Provider is GONE
    expect($content)->not->toContain('OldProvider::class');

    // Assert New Provider is PRESENT
    expect($content)->toContain('->defaultAvatarProvider(UiAvatarsProvider::class)');
});

it('does not duplicate the provider if already installed', function () {
    // Setup file that already has the correct provider
    ($this->createProvider)('->defaultAvatarProvider(UiAvatarsProvider::class)');

    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('getId')->andReturn('admin');

    Filament::shouldReceive('getPanels')->andReturn(['admin' => $mockPanel]);
    Filament::shouldReceive('getPanel')->with('admin')->andReturn($mockPanel);

    $this->artisan('fin-avatar:install', ['panels' => ['admin']])
        ->assertSuccessful();

    $content = file_get_contents($this->providerPath);

    // Count occurrences to ensure it wasn't added twice
    $count = substr_count($content, 'UiAvatarsProvider::class');
    // It appears once in the usage, possibly once in imports if we don't check imports strictly,
    // but definitely only once in the chain.
    $chainCount = substr_count($content, '->defaultAvatarProvider(UiAvatarsProvider::class)');
    expect($chainCount)->toBe(1);
});

it('can handle multiple panels at once', function () {
    // 1. Create Admin Panel
    ($this->createProvider)(); // admin

    // 2. Create Second "App" Panel
    $appPath = $this->directory . '/AppPanelProvider.php';
    $appContent = <<<PHP
    <?php
    namespace App\Providers\Filament;
    use Filament\Panel;
    use Filament\PanelProvider;
    class AppPanelProvider extends PanelProvider {
        public function panel(Panel \$panel): Panel {
            return \$panel->id('app');
        }
    }
    PHP;
    file_put_contents($appPath, $appContent);

    // Mock both panels
    $mockAdmin = Mockery::mock(Panel::class);
    $mockAdmin->shouldReceive('getId')->andReturn('admin');

    $mockApp = Mockery::mock(Panel::class);
    $mockApp->shouldReceive('getId')->andReturn('app');

    Filament::shouldReceive('getPanels')->andReturn(['admin' => $mockAdmin, 'app' => $mockApp]);
    Filament::shouldReceive('getPanel')->with('admin')->andReturn($mockAdmin);
    Filament::shouldReceive('getPanel')->with('app')->andReturn($mockApp);

    // Run Command for BOTH
    $this->artisan('fin-avatar:install', ['panels' => ['admin', 'app']])
        ->assertSuccessful();

    // Verify Admin
    $adminContent = file_get_contents($this->providerPath);
    expect($adminContent)->toContain('UiAvatarsProvider::class');

    // Verify App
    $appResult = file_get_contents($appPath);
    expect($appResult)->toContain('UiAvatarsProvider::class');

    // Cleanup extra file
    unlink($appPath);
});
