<?php

namespace App\Providers;

use App\Models\Item;
use App\Models\SalesTransaction;
use App\Models\StockInTransaction;
use App\Models\StockOpnameTransaction;
use App\Models\TransferTransaction;
use App\Policies\ItemPolicy;
use App\Policies\SalesTransactionPolicy;
use App\Policies\StockInPolicy;
use App\Policies\StockOpnamePolicy;
use App\Policies\TransferPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Item::class => ItemPolicy::class,
        StockInTransaction::class => StockInPolicy::class,
        TransferTransaction::class => TransferPolicy::class,
        SalesTransaction::class => SalesTransactionPolicy::class,
        StockOpnameTransaction::class => StockOpnamePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
