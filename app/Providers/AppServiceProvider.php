<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Blade directives for placeholders
        \Blade::directive('userAvatar', function ($expression) {
            return "<?php echo \App\Helpers\PlaceholderHelper::userAvatarHtml($expression); ?>";
        });
        
        \Blade::directive('categoryImage', function ($expression) {
            return "<?php echo \App\Helpers\PlaceholderHelper::categoryImageHtml($expression); ?>";
        });
        
        \Blade::directive('sectionImage', function ($expression) {
            return "<?php echo \App\Helpers\PlaceholderHelper::sectionImageHtml($expression); ?>";
        });
        
        \Blade::directive('placeholder', function ($expression) {
            return "<?php echo \App\Helpers\PlaceholderHelper::getPlaceholder($expression); ?>";
        });
    }
}
