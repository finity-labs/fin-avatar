<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\File;

// 1. Setup: Create a temporary directory and file before each test
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/fin_avatar_tests';
    if (! is_dir($this->tempDir)) {
        mkdir($this->tempDir);
    }

    // We define a unique class name for the test to avoid collisions
    $this->className = 'TestPanelProvider';
    $this->providerPath = $this->tempDir . "/{$this->className}.php";

    // Create the dummy class file
    $classContent = <<<PHP
    <?php

    namespace FinityLabs\Tests;

    use Filament\Panel;
    use Filament\PanelProvider;

    class {$this->className} extends PanelProvider
    {
        public function panel(Panel \$panel): Panel
        {
            return \$panel
                ->id('admin')
                ->path('admin')
                ->colors([
                    'primary' => 'red',
                ]);
        }
    }
    PHP;

    file_put_contents($this->providerPath, $classContent);

    // Require the file so PHP knows the class exists for Reflection
    if (! class_exists("FinityLabs\\Tests\\{$this->className}")) {
        require_once $this->providerPath;
    }
});

// 2. Cleanup: Remove temp files after tests
afterEach(function () {
    if (file_exists($this->providerPath)) {
        unlink($this->providerPath);
    }
    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }
    Mockery::close();
});

it('installs the avatar provider into a fresh panel', function () {
    // Mock Filament to return our temporary provider class
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('getProvider')->andReturn("FinityLabs\\Tests\\{$this->className}");

    Filament::shouldReceive('getPanel')
        ->with('admin')
        ->andReturn($mockPanel);

    Filament::shouldReceive('getPanels')
        ->andReturn(['admin' => $mockPanel]);

    // Run the command
    $this->artisan('fin-avatar:install', ['panels' => ['admin']])
        ->assertSuccessful();

    $content = file_get_contents($this->providerPath);

    // Assert Import was added
    expect($content)->toContain('use FinityLabs\FinAvatar\AvatarProviders\UiAvatarsProvider;');

    // Assert Method was chained to ->id()
    expect($content)->toContain('->defaultAvatarProvider(UiAvatarsProvider::class)');
});

it('replaces an existing avatar provider if found', function () {
    // 1. Setup file with an OLD provider
    $oldContent = <<<PHP
    <?php
    namespace FinityLabs\Tests;
    use Filament\Panel;
    use Filament\PanelProvider;

    class {$this->className} extends PanelProvider
    {
        public function panel(Panel \$panel): Panel
        {
            return \$panel
                ->id('admin')
                ->defaultAvatarProvider(OldProvider::class) // <--- Old one
                ->path('admin');
        }
    }
    PHP;
    file_put_contents($this->providerPath, $oldContent);

    // Mock
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('getProvider')->andReturn("FinityLabs\\Tests\\{$this->className}");

    Filament::shouldReceive('getPanel')->with('admin')->andReturn($mockPanel);
    Filament::shouldReceive('getPanels')->andReturn(['admin' => $mockPanel]);

    // Run Command
    $this->artisan('fin-avatar:install', ['panels' => ['admin']])
        ->assertSuccessful();

    $content = file_get_contents($this->providerPath);

    // Assert Old Provider is GONE
    expect($content)->not->toContain('OldProvider::class');

    // Assert New Provider is PRESENT
    expect($content)->toContain('->defaultAvatarProvider(UiAvatarsProvider::class)');
});

it('does not duplicate the provider if already installed', function () {
    // 1. Setup file that already has the correct provider
    $installedContent = <<<PHP
    <?php
    namespace FinityLabs\Tests;
    use Filament\Panel;
    use Filament\PanelProvider;
    use FinityLabs\FinAvatar\AvatarProviders\UiAvatarsProvider;

    class {$this->className} extends PanelProvider
    {
        public function panel(Panel \$panel): Panel
        {
            return \$panel
                ->id('admin')
                ->defaultAvatarProvider(UiAvatarsProvider::class) // <--- Already here
                ->path('admin');
        }
    }
    PHP;
    file_put_contents($this->providerPath, $installedContent);

    // Mock
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('getProvider')->andReturn("FinityLabs\\Tests\\{$this->className}");

    Filament::shouldReceive('getPanel')->with('admin')->andReturn($mockPanel);
    Filament::shouldReceive('getPanels')->andReturn(['admin' => $mockPanel]);

    // Run Command
    $this->artisan('fin-avatar:install', ['panels' => ['admin']])
        ->assertSuccessful();

    $content = file_get_contents($this->providerPath);

    // Count occurrences to ensure it wasn't added twice
    $count = substr_count($content, 'UiAvatarsProvider::class');
    expect($count)->toBe(1);
});

it('can handle multiple panels at once', function () {
    // Mocking two panels requires a bit more setup or simply running the loop twice logic
    // For simplicity, we test that the loop works by passing an array

    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('getProvider')->andReturn("FinityLabs\\Tests\\{$this->className}");

    Filament::shouldReceive('getPanel')->with('admin')->andReturn($mockPanel);
    Filament::shouldReceive('getPanel')->with('app')->andReturn($mockPanel); // Reuse same file for test simplicity

    Filament::shouldReceive('getPanels')->andReturn(['admin' => $mockPanel, 'app' => $mockPanel]);

    $this->artisan('fin-avatar:install', ['panels' => ['admin', 'app']])
        ->assertSuccessful();

    // Since we used the same file for both "panels", it should be installed once
    // and then skipped/verified on the second pass.

    $content = file_get_contents($this->providerPath);
    expect($content)->toContain('->defaultAvatarProvider(UiAvatarsProvider::class)');
});
