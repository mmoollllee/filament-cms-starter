<?php

namespace App\Support\Content\Blocks\contact_form;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Mmoollllee\Cms\Contracts\Tenant;
use Mmoollllee\Cms\Support\Content\Blocks\BaseBuilderBlock;

/**
 * Section-child block that renders the live KontaktForm (App\Livewire\KontaktForm),
 * optionally preceded by an eyebrow/title heading and intro rich text. Reusable on any
 * page (Kontakt, machine detail, …); the recipient falls back to the tenant's contact
 * email unless overridden here.
 */
class ContactFormBlock extends BaseBuilderBlock
{
    public function key(): string
    {
        return 'contact_form';
    }

    public function make(?Tenant $tenant): Block
    {
        return Block::make('contact_form')
            ->icon(Heroicon::OutlinedEnvelope)
            ->label('Kontaktformular')
            ->title('title', placeholder: 'Titel', suffix: 'Kontaktformular')
            ->preview('blocks::contact_form.preview')
            ->schema([
                ...static::optionHiddenFields(),
                TextInput::make('eyebrow')
                    ->label('Eyebrow')
                    ->maxLength(100),
                static::richEditorWithSource(),
                TextInput::make('contact_email')
                    ->label('Empfänger-E-Mail (optional)')
                    ->email()
                    ->helperText('Leer = Kontakt-E-Mail des Tenants.')
                    ->maxLength(255),
            ]);
    }
}
