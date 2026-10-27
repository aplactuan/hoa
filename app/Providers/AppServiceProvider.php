<?php

namespace App\Providers;

use App\Listeners\RecordLogin;
use App\Models\ChargeType;
use App\Models\Facility;
use App\Models\FamilyMember;
use App\Models\HouseStaff;
use App\Models\PropertyType;
use App\Models\RequestCategory;
use App\Models\Resident;
use App\Models\Section;
use App\Models\Street;
use App\Models\TransactionType;
use App\Models\Vehicle;
use App\Models\VehicleSticker;
use App\Models\ViolationType;
use App\Policies\HouseholdPolicy;
use App\Policies\LookupPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Livewire\Mechanisms\HandleRequests\RequireLivewireHeaders;

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
        Livewire::setUpdateRoute(
            fn (array $handle, string $path) => Route::post('/livewire/update', $handle),
        );

        Route::post('/livewire-{hash}/update', [HandleRequests::class, 'handleUpdate'])
            ->middleware(['web', RequireLivewireHeaders::class])
            ->where('hash', '[A-Za-z0-9]+')
            ->name('livewire.legacy-update');

        $this->configureDefaults();
        $this->configureAccess();
        Event::listen(Login::class, RecordLogin::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAccess(): void
    {
        foreach ([
            Section::class,
            Street::class,
            PropertyType::class,
            TransactionType::class,
            ChargeType::class,
            ViolationType::class,
            RequestCategory::class,
            Facility::class,
        ] as $model) {
            Gate::policy($model, LookupPolicy::class);
        }

        foreach ([Resident::class, FamilyMember::class, HouseStaff::class, Vehicle::class, VehicleSticker::class] as $model) {
            Gate::policy($model, HouseholdPolicy::class);
        }
    }
}
