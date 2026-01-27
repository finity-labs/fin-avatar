<?php

declare(strict_types=1);

namespace FinityLabs\FinAvatar\Commands;

use Filament\Facades\Filament;
use FinityLabs\FinAvatar\AvatarProviders\UiAvatarsProvider;
use Illuminate\Console\Command;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\multiselect;

#[AsCommand(name: 'fin-avatar:install', description: 'Install and configure avatar provider')]
class InstallCommand extends Command
{
    /** @var string */
    protected $signature = 'fin-avatar:install {panels?*}';

    public function handle(): int
    {
        $allPanels = collect(Filament::getPanels())->keys()->all();

        $selectedPanels = $this->argument('panels');
        if (empty($selectedPanels)) {
            $selectedPanels = multiselect(
                label: 'Which Panels would you like to install for?',
                options: $allPanels,
                default: $allPanels,
                required: true
            );
        }

        if (is_string($selectedPanels)) {
            $selectedPanels = [$selectedPanels];
        }

        foreach ($selectedPanels as $panelId) {
            $this->installPanel($panelId);
        }

        return Command::SUCCESS;
    }

    protected function installPanel(string $panelId): void
    {
        $panel = Filament::getPanel($panelId);

        $reflection = new ReflectionClass($panel->getProvider());
        $panelPath = $reflection->getFileName();

        if (! file_exists($panelPath)) {
            $this->error("Could not locate Panel Provider for [{$panelId}] at: {$panelPath}");
            return;
        }

        $content = file_get_contents($panelPath);
        $providerClass = UiAvatarsProvider::class;
        $shortName = 'UiAvatarsProvider';

        if (! str_contains($content, "use {$providerClass};")) {
            if (preg_match('/^use\s+[\w\\\\]+;$/m', $content)) {
                $content = preg_replace(
                    '/^(use\s+[\w\\\\]+;)(?![\s\S]*^use)/m',
                    "$1\nuse {$providerClass};",
                    $content
                );
            } else {
                $content = preg_replace(
                    '/^(namespace\s+[\w\\\\]+;)/m',
                    "$1\n\nuse {$providerClass};",
                    $content
                );
            }
        }

        $pattern = '/->defaultAvatarProvider\s*\(([^)]+)\)/';

        if (preg_match($pattern, $content)) {
            $content = preg_replace(
                $pattern,
                "->defaultAvatarProvider({$shortName}::class)",
                $content
            );
            $this->components->info("Updated avatar provider on [{$panelId}] panel.");
        } else {
            if (! str_contains($content, "->defaultAvatarProvider({$shortName}::class)")) {
                $content = preg_replace(
                    '/->id\((.*?)\)/',
                    "->id($1)\n            ->defaultAvatarProvider({$shortName}::class)",
                    $content
                );
                $this->components->info("Installed avatar provider on [{$panelId}] panel.");
            }
        }

        file_put_contents($panelPath, $content);
    }
}
