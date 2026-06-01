<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Event $record */
        $record = $this->getRecord();
        $record->load('users');

        $data['assignments'] = $record->users->map(fn ($user) => [
            'user_id' => $user->id,
            'role_in_event' => $user->pivot->role_in_event,
        ])->values()->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(EventService::class)->updateEvent($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
