<?php

namespace App\Filament\Resources\AuthResource\Pages\Auth;

use Filament\Forms\Form;
use Filament\Forms\Components\View;
use Filament\Forms\Components\Checkbox;
use App\Filament\Resources\AuthResource;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Forms\Components\Actions\Action;
// use Filament\Resources\Pages\ViewRecord;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('email')
                    ->label('Email')
                    ->required()
                    ->email(),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required(),
                Checkbox::make('remember')
                    ->label('Remember me'),

                View::make('google-login-button'),
            ]);
    }
}
