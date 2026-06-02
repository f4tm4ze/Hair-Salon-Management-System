<?php

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // ...
        Login::class => [
            LogSuccessfulLogin::class,
        ],
        Logout::class => [
            LogSuccessfulLogout::class,
        ],
    ];
}
