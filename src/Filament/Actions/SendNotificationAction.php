<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use M0d1xd\ExpoNotification\ExpoMessage;
use M0d1xd\ExpoNotification\Facades\PushNotifier;
use M0d1xd\ExpoNotification\Filament\ExpoNotificationPlugin;
use M0d1xd\ExpoNotification\Filament\Resources\PushNotifications\PushNotificationResource;
use M0d1xd\ExpoNotification\LocalizedMessage;
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
            ->label(__('expo-notification::filament.actions.send.label'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (): bool => ExpoNotificationPlugin::get()->isSendingEnabled())
            ->schema([...self::messageFields(), ...self::targetFields()])
            ->action(fn (array $data, Action $action) => self::send($data, self::recipients($data), $action));
    }

    /** Row action on a device. */
    public static function toDevice(): Action
    {
        return Action::make('sendTestNotification')
            ->label(__('expo-notification::filament.actions.send.test_label'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (PushToken $record): bool => $record->active && ExpoNotificationPlugin::get()->isSendingEnabled())
            ->schema(self::messageFields())
            ->action(fn (array $data, PushToken $record, Action $action) => self::send($data, $record, $action));
    }

    /** Bulk action on devices. */
    public static function toDevices(): BulkAction
    {
        return BulkAction::make('sendTestNotification')
            ->label(__('expo-notification::filament.actions.send.test_label'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (): bool => ExpoNotificationPlugin::get()->isSendingEnabled())
            ->schema(self::messageFields())
            ->action(fn (array $data, Collection $records, BulkAction $action) => self::send($data, $records, $action));
    }

    private static function messageFields(): array
    {
        $locales = ExpoNotificationPlugin::get()->getLocales();

        return [
            ...($locales === [] ? self::textFields() : [
                Tabs::make('languages')->tabs(array_map(
                    fn (string $locale, string $label) => Tab::make($label)->schema(self::textFields($locale, array_key_first($locales))),
                    array_keys($locales),
                    $locales,
                )),
            ]),
            TextInput::make('url')
                ->label(__('expo-notification::filament.actions.send.url'))
                ->helperText(__('expo-notification::filament.actions.send.url_helper'))
                ->maxLength(255),
        ];
    }

    /**
     * Title and body; per language as title.{locale} and body.{locale}, where only the fallback language is required.
     *
     * @return array<int, TextInput|Textarea>
     */
    private static function textFields(?string $locale = null, ?string $fallback = null): array
    {
        $required = $locale === $fallback;
        $helper = $required ? null : __('expo-notification::filament.actions.send.translation_helper', [
            'language' => ExpoNotificationPlugin::get()->getLocales()[$fallback] ?? $fallback,
        ]);

        return [
            TextInput::make($locale === null ? 'title' : "title.{$locale}")
                ->label(__('expo-notification::filament.actions.send.title'))
                ->required($required)
                ->when(! $required, fn (TextInput $field) => $field->requiredWith("body.{$locale}"))
                ->helperText($helper)
                ->maxLength(255),
            Textarea::make($locale === null ? 'body' : "body.{$locale}")
                ->label(__('expo-notification::filament.actions.send.body'))
                ->required($required)
                ->when(! $required, fn (Textarea $field) => $field->requiredWith("title.{$locale}"))
                ->rows(3),
        ];
    }

    private static function targetFields(): array
    {
        $owner = ExpoNotificationPlugin::get()->getOwnerModel();
        $titleAttribute = ExpoNotificationPlugin::get()->getOwnerTitleAttribute();
        $targets = __('expo-notification::filament.actions.send.targets');

        if ($owner === null) {
            unset($targets['users']);
        }

        return [
            Radio::make('target')
                ->label(__('expo-notification::filament.actions.send.target'))
                ->options($targets)
                ->default('all')
                ->in(array_keys($targets))
                ->required()
                ->live(),
            ...($owner === null ? [] : [
                Select::make('users')
                    ->label(__('expo-notification::filament.actions.send.users'))
                    ->multiple()
                    ->options(fn () => $owner::query()->orderBy($titleAttribute)->pluck($titleAttribute, (new $owner)->getKeyName()))
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('target') === 'users'),
            ]),
            Select::make('devices')
                ->label(__('expo-notification::filament.actions.send.devices'))
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
            'users' => ExpoNotificationPlugin::get()->getOwnerModel()::query()->whereKey($data['users'])->get(),
            'devices' => PushToken::query()->whereKey($data['devices']),
            default => PushToken::query(),
        };
    }

    private static function send(array $data, mixed $recipients, Action $action): void
    {
        $message = fn (string $title, string $body) => ExpoMessage::create($title, $body)
            ->when(filled($data['url'] ?? null), fn (ExpoMessage $message) => $message->data(['url' => $data['url']]));
        $locales = ExpoNotificationPlugin::get()->getLocales();

        $notification = PushNotifier::send($locales === []
            ? $message($data['title'], $data['body'])
            : LocalizedMessage::make(collect($locales)
                ->keys()
                ->filter(fn (string $locale) => filled($data['title'][$locale] ?? null))
                ->mapWithKeys(fn (string $locale) => [$locale => $message($data['title'][$locale], $data['body'][$locale])])
                ->all())->fallback(array_key_first($locales)), $recipients);
        $targeted = $notification->deliveries()->count();

        Notification::make()
            ->title($targeted === 0
                ? __('expo-notification::filament.actions.send.none')
                : trans_choice('expo-notification::filament.actions.send.sent', $targeted, ['count' => $targeted]))
            ->status($targeted === 0 ? 'warning' : 'success')
            ->send();

        if (ExpoNotificationPlugin::get()->isEnabled('notifications')) {
            $action->redirect(PushNotificationResource::getUrl('view', ['record' => $notification]));
        }
    }
}
