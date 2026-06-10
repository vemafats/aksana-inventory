<?php

namespace App\Filament\Resources\SizeResource\Pages;

use App\Filament\Resources\SizeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSize extends CreateRecord
{
    protected static string $resource = SizeResource::class;

    protected function getRedirectUrl(): string
    {
        return '/admin/master-data?tab=sizes';
    }
}
