<?php

namespace App\Providers;

use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\CustomerServiceInterface;
use App\Contracts\Services\InstallmentServiceInterface;
use App\Contracts\Services\UserServiceInterface;
use App\Services\AuthService;
use App\Services\CustomerService;
use App\Services\InstallmentService;
use App\Services\UserService;
use Illuminate\Support\ServiceProvider;

class ServiceBindingProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array
     */
    public $bindings = [
        AuthServiceInterface::class => AuthService::class,
        UserServiceInterface::class => UserService::class,
        CustomerServiceInterface::class => CustomerService::class,
        InstallmentServiceInterface::class => InstallmentService::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
