<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Helpers\FormatHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Biaya Event';

    protected static ?string $modelLabel = 'Biaya';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('description')
                ->label('Deskripsi Biaya')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('amount')
                ->label('Jumlah (Rp)')
                ->required()
                ->numeric()
                ->minValue(0)
                ->prefix('Rp'),
            Forms\Components\DatePicker::make('expense_date')
                ->label('Tanggal')
                ->required()
                ->default(now()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn ($state) => FormatHelper::price($state))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Oleh')
                    ->placeholder('—'),
            ])
            ->defaultSort('expense_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('+ Tambah Biaya')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Belum ada biaya')
            ->emptyStateDescription('Tambah biaya operasional event');
    }
}
