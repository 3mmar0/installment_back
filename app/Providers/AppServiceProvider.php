<?php

namespace App\Providers;

use App\Services\Assistant\AssistantLlmClient;
use App\Services\Assistant\DeepSeekClient;
use App\Services\Assistant\GeminiClient;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AssistantLlmClient::class, function ($app) {
            $provider = strtolower((string) config('assistant.provider', 'deepseek'));

            return match ($provider) {
                'gemini' => $app->make(GeminiClient::class),
                default => $app->make(DeepSeekClient::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('money', function (string $expression): string {
            return "<?php echo \\App\\Helpers\\MoneyHelper::format($expression); ?>";
        });
    }
}
