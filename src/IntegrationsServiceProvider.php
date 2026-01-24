<?php

namespace Platform\Integrations;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/integrations.php', 'integrations');
    }

    public function boot(): void
    {
        // Schritt 1: Config laden (hier nochmals wie in media/printing, damit config()->has() sicher ist)
        $this->mergeConfigFrom(__DIR__ . '/../config/integrations.php', 'integrations');

        // Schritt 2: Modul registrieren (nur wenn Module-System verfügbar)
        if (
            config()->has('integrations.routing') &&
            config()->has('integrations.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'integrations',
                'title'      => 'Integrations',
                'routing'    => config('integrations.routing'),
                'guard'      => config('integrations.guard'),
                'navigation' => config('integrations.navigation'),
                'sidebar'    => config('integrations.sidebar'),
            ]);
        }

        // Schritt 3: Routes laden
        // OAuth-Routes müssen immer verfügbar sein
        // Callback benötigt auch Auth, da User eingeloggt sein sollte
        Route::prefix('integrations')
            ->middleware(['web', 'auth']) // Beide Routes benötigen Auth
            ->group(function () {
                Route::get('/oauth2/{integrationKey}/start', [\Platform\Integrations\Http\Controllers\OAuth2Controller::class, 'start'])
                    ->name('integrations.oauth2.start');
                Route::get('/oauth2/{integrationKey}/callback', [\Platform\Integrations\Http\Controllers\OAuth2Controller::class, 'callback'])
                    ->name('integrations.oauth2.callback');
            });

        // Andere Routes über ModuleRouter (wenn Modul aktiv ist)
        if (PlatformCore::getModule('integrations')) {
            $routesPath = __DIR__ . '/../routes/web.php';
            ModuleRouter::group('integrations', function () use ($routesPath) {
                // Routes aus web.php laden (ohne OAuth-Routes, die sind bereits oben registriert)
                require $routesPath;
            });
        }

        // Schritt 4: Migrationen + Views + Livewire + Commands
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'integrations');
        $this->registerLivewireComponents();
        
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Integrations\Console\Commands\SyncFacebookPages::class,
                \Platform\Integrations\Console\Commands\SyncInstagramAccounts::class,
                \Platform\Integrations\Console\Commands\SyncWhatsAppAccounts::class,
                \Platform\Integrations\Console\Commands\SyncGithubRepositories::class,
                \Platform\Integrations\Console\Commands\SeedIntegrations::class,
            ]);
        }

        // Schritt 5: Config publish
        $this->publishes([
            __DIR__ . '/../config/integrations.php' => config_path('integrations.php'),
        ], 'integrations-config');
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Integrations\\Livewire';
        $prefix = 'integrations';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            // integrations.connections.index aus Connections/Index.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}

