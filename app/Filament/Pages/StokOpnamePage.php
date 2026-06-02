<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\StockOpnameTransaction;
use App\Services\StockOpnameService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

class StokOpnamePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Stok Opname';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Stok Opname';

    protected static ?string $slug = 'stok-opname';

    protected static string $view = 'filament.pages.stok-opname';

    public string $activeTab = 'aktif';

    public ?string $validatingSessionId = null;

    public string $rejectionNote = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canStockOpname();
    }

    public function canValidate(): bool
    {
        $role = auth()->user()?->role;

        return in_array($role, [UserRole::OWNER, UserRole::ADMIN], true);
    }

    protected function getViewData(): array
    {
        return [
            'activeSessions' => StockOpnameTransaction::with([
                'location',
                'createdBy',
                'stockOpnameItems.item',
            ])
                ->whereIn('validation_status', ['draft', 'pending_validation'])
                ->orderBy('created_at', 'desc')
                ->get(),
            'completedSessions' => StockOpnameTransaction::with([
                'location',
                'createdBy',
                'validator',
            ])
                ->whereIn('validation_status', ['validated', 'rejected'])
                ->orderByDesc('validated_at')
                ->limit(20)
                ->get(),
            'hasActiveSession' => StockOpnameTransaction::whereIn(
                'validation_status',
                ['draft', 'pending_validation'],
            )->exists(),
        ];
    }

    public function validateSession(string $id): void
    {
        try {
            $session = StockOpnameTransaction::query()->findOrFail($id);

            app(StockOpnameService::class)->validateOpname(
                $session,
                auth()->user(),
            );

            Notification::make()
                ->title('Stok opname berhasil divalidasi. Stok telah diperbarui.')
                ->success()
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->title('Hanya Owner dan Admin yang bisa memvalidasi.')
                ->danger()
                ->send();
        } catch (LogicException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function rejectSession(string $id): void
    {
        try {
            $session = StockOpnameTransaction::query()->findOrFail($id);

            app(StockOpnameService::class)->rejectOpname(
                $session,
                auth()->user(),
                $this->rejectionNote !== '' ? $this->rejectionNote : 'Ditolak oleh validator.',
            );

            $this->validatingSessionId = null;
            $this->rejectionNote = '';

            Notification::make()
                ->title('Sesi opname ditolak.')
                ->success()
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->title('Hanya Owner dan Admin yang bisa menolak sesi opname.')
                ->danger()
                ->send();
        } catch (InvalidArgumentException|LogicException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cancelSession(string $id): void
    {
        if (! $this->canValidate()) {
            Notification::make()
                ->title('Hanya Owner dan Admin yang bisa membatalkan sesi opname.')
                ->danger()
                ->send();

            return;
        }

        try {
            $session = StockOpnameTransaction::query()->findOrFail($id);

            if ($session->validation_status !== 'draft') {
                Notification::make()
                    ->title('Hanya sesi draft yang bisa dibatalkan')
                    ->danger()
                    ->send();

                return;
            }

            DB::transaction(function () use ($session): void {
                $session->stockOpnameItems()->delete();
                $session->delete();
            });

            Notification::make()
                ->title('Sesi opname draft berhasil dibatalkan')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Gagal membatalkan sesi opname')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
