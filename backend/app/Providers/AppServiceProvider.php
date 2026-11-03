<?php

namespace App\Providers;

use App\Models\LogbookEntry;
use App\Models\Project;
use App\Policies\LogbookEntryPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les services de l'application.
     */
    public function register(): void
    {
        //
    }

    /**
     * Initialise les politiques d'autorisation et services au démarrage.
     */
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(LogbookEntry::class, LogbookEntryPolicy::class);
    }
}
