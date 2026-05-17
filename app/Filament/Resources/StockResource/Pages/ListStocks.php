<?php

namespace App\Filament\Resources\StockResource\Pages;

use App\Filament\Resources\StockResource;
use App\Services\PasswordVerificationService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStocks extends ListRecords
{
    protected static string $resource = StockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewSupplierCost')
                ->label('Lihat Harga Modal')
                ->icon('heroicon-o-lock-closed')
                ->visible(fn (): bool => auth()->user()?->isOwner() ?? false)
                ->modalHeading('Verifikasi Identitas')
                ->modalSubmitActionLabel('Verifikasi')
                ->form([
                    TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data, PasswordVerificationService $passwordVerificationService): void {
                    $user = auth()->user();

                    if (! $passwordVerificationService->verifyPassword($user, $data['password'])) {
                        Notification::make()
                            ->title('Password tidak sesuai')
                            ->danger()
                            ->send();

                        return;
                    }

                    $tokenData = $passwordVerificationService->generateCostViewToken($user);

                    session([
                        'cost_view_token' => $tokenData['token'],
                        'cost_view_token_expires_at' => $tokenData['expires_at'],
                    ]);

                    $this->redirect(StockResource::getUrl('index').'?show_cost=1');
                }),
        ];
    }
}
