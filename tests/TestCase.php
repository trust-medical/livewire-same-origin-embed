<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\View;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use TrustMedical\SameOriginLivewireBridge\Http\Middleware\EnsureSameOrigin;
use TrustMedical\SameOriginLivewireBridge\SameOriginLivewireBridgeServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            SameOriginLivewireBridgeServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $config->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $config->set('app.url', 'https://example.test/some/path');
        $config->set('session.driver', 'array');
        $config->set('view.paths', [__DIR__.'/Fixtures/resources/views']);
        $config->set('livewire-bridge.allowed_origin', 'https://example.test/some/path');
        $config->set('livewire-bridge.render_middleware', [
            Fixtures\TestingCsrfMiddleware::class,
            'throttle:livewire-bridge-render',
            EnsureSameOrigin::class,
        ]);
        $config->set('livewire-bridge.components', [
            'reservation' => [
                'component' => Fixtures\Livewire\ReservationForm::class,
                'allowed_params' => ['placement', 'campaign'],
            ],
            'questionnaire' => [
                'component' => Fixtures\Livewire\QuestionnaireForm::class,
                'allowed_params' => ['placement'],
                'validator' => Fixtures\PlacementValidator::class,
            ],
            'with-assets' => [
                'component' => Fixtures\Livewire\ComponentWithAssets::class,
                'allowed_params' => [],
            ],
        ]);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/fixture/non-laravel-page', static function (): string {
            return (string) View::make('pages.non-laravel');
        });
    }
}
