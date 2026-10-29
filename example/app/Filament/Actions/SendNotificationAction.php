<?php

namespace App\Filament\Actions;

use App\Filament\Resources\PushNotifications\PushNotificationResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Facades\PushNotifier;
use M0d1xd\ExpoNotification\Models\PushToken;

/**
 * Sends through the SDK's PushNotifier, then opens the notification so its deliveries can be watched.
 */
class SendNotificationAction
{
    /** Header action: to all active devices, chosen users or chosen devices. */
    public static function make(): Action
    {
        return Action::make('sendNotification')
            ->label('Send notification')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->schema([...self::messageFields(), ...self::targetFields()])
            ->action(fn (array $data, Action $action) => self::send($data, self::recipients($data), $action));
    }

    /** Row action on a device. */
    public static function toDevice(): Action
    {
        return Action::make('sendTestNotification')
            ->label('Send test notification')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (PushToken $record): bool => $record->active)
            ->schema(self::messageFields())
            ->action(fn (array $data, PushToken $record, Action $action) => self::send($data, $record, $action));
    }

    /** Bulk action on devices. */
    public static function toDevices(): BulkAction
    {
        return BulkAction::make('sendTestNotification')
            ->label('Send test notification')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->schema(self::messageFields())
            ->action(fn (array $data, Collection $records, BulkAction $action) => self::send($data, $records, $action));
    }

    private static function messageFields(): array
    {
        return [
            TextInput::make('title')->required()->maxLength(255),
            Textarea::make('body')->required()->rows(3),
            TextInput::make('url')
                ->label('Deep link')
                ->helperText('Sent as data.url, e.g. /details')
                ->maxLength(255),
        ];
    }

    private static function targetFields(): array
    {
        return [
            Radio::make('target')
                ->options([
                    'all' => 'All active devices',
                    'users' => 'Chosen users',
                    'devices' => 'Chosen devices',
                ])
                ->default('all')
                ->required()
                ->live(),
            Select::make('users')
                ->multiple()
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->visible(fn (Get $get): bool => $get('target') === 'users'),
            Select::make('devices')
                ->multiple()
                ->options(fn () => PushToken::query()->active()->get()
                    ->mapWithKeys(fn (PushToken $token) => [$token->id => "{$token->device_id} ({$token->platform})"]))
                ->searchable()
                ->required()
                ->visible(fn (Get $get): bool => $get('target') === 'devices'),
        ];
    }

    private static function recipients(array $data): mixed
    {
        return match ($data['target']) {
            'users' => User::query()->whereKey($data['users'])->get(),
            'devices' => PushToken::query()->whereKey($data['devices']),
            default => PushToken::query(),
        };
    }

    private static function send(array $data, mixed $recipients, Action $action): void
    {
        $message = ExpoMessage::create($data['title'], $data['body'])
            ->when(filled($data['url'] ?? null), fn (ExpoMessage $message) => $message->data(['url' => $data['url']]));

        $notification = PushNotifier::send($message, $recipients);
        $targeted = $notification->deliveries()->count();

        Notification::make()
            ->title($targeted === 0 ? 'No active devices to send to' : "Sent to {$targeted} ".str('device')->plural($targeted))
            ->status($targeted === 0 ? 'warning' : 'success')
            ->send();

        $action->redirect(PushNotificationResource::getUrl('view', ['record' => $notification]));
    }
}
