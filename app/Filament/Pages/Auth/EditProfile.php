<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('البيانات الشخصية')
                    ->description('يمكنك تعديل اسمك وبريدك الإلكتروني من هنا.')
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),

                Section::make('تغيير كلمة المرور')
                    ->description('اتركها فارغة إذا لم ترغب في تغيير كلمة المرور.')
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),

                Section::make('بيانات الحساب')
                    ->description('بيانات للعرض فقط — تُدار من صفحة المشرفين.')
                    ->schema([
                        Placeholder::make('roles')
                            ->label('الأدوار')
                            ->content(fn (): string => $this->getUser()->roles->pluck('name')->implode('، ') ?: 'بلا أدوار'),

                        Placeholder::make('is_active')
                            ->label('حالة الحساب')
                            ->content(fn (): string => $this->getUser()->is_active ? 'نشط' : 'غير نشط'),

                        Placeholder::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->content(fn (): string => $this->getUser()->created_at?->translatedFormat('j F Y — H:i') ?? '—'),
                    ]),
            ]);
    }
}
