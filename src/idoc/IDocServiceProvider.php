<?php

namespace OVAC\IDoc;

use Illuminate\Support\ServiceProvider;

class IDocServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views/', 'idoc');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'idoc');

        $this->publishes([
            __DIR__ . '/../../resources/lang' => $this->resourcePath('lang/vendor/idoc'),
            __DIR__ . '/../../resources/views' => $this->resourcePath('views/vendor/idoc'),
        ], 'idoc-views');

        $this->publishes([
            __DIR__ . '/../../config/idoc.php' => app()->basePath() . '/config/idoc.php',
        ], 'idoc-config');

        $this->mergeConfigFrom(__DIR__ . '/../../config/idoc.php', 'idoc');

        if ($this->app->runningInConsole()) {
            $this->commands([
                IDocGeneratorCommand::class,
            ]);
        }
    }

    /**
     * Register the API doc commands.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Return a fully qualified path to a given file.
     *
     * @param string $path
     *
     * @return string
     */
    public function resourcePath($path = '')
    {
        return app()->basePath() . '/resources' . ($path ? '/' . $path : $path);
    }
}
