<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamRegistrationResource\Pages;
use App\Models\TeamRegistration;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class TeamRegistrationResource extends \Filament\Resources\Resource
{
    protected static ?string $model = TeamRegistration::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Liga';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('team_name')->required(),
                TextInput::make('contact_name')->required(),
                TextInput::make('contact_email')->email()->required(),
                TextInput::make('contact_phone'),
                TextInput::make('preferred_abbr')->maxLength(8),
                TextInput::make('home_city'),
                Textarea::make('branding_preferences')->helperText('JSON con preferencias de colores/logos'),
                Select::make('status')->options([
                    'pending' => 'Pendiente',
                    'approved' => 'Aprobado',
                    'rejected' => 'Rechazado'
                ])->default('pending'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('team_name')->searchable()->sortable(),
                TextColumn::make('contact_name'),
                TextColumn::make('contact_email'),
                BadgeColumn::make('status')->colors([
                    'warning' => 'pending',
                    'success' => 'approved',
                    'danger' => 'rejected'
                ]),
                TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->action(function ($record) {
                        if ($record->status !== 'pending') return;
                        $record->status = 'approved';
                        $record->approved_at = now();
                        $record->save();
                        event(new \App\Events\TeamRegistrationApproved($record->id));
                    })
                    ->requiresConfirmation()
                    ->color('success'),
                Tables\Actions\Action::make('reject')
                    ->label('Rechazar')
                    ->action(function ($record) {
                        $record->status = 'rejected';
                        $record->save();
                    })
                    ->color('danger'),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeamRegistrations::route('/'),
            'view' => Pages\ViewTeamRegistration::route('/{record}'),
        ];
    }
}
