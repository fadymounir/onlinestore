<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Auth\Notifications\ResetPassword as BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Laravel's own reset notification leans on JSON translations, and a few of
 * its strings (the subject among them) have no Arabic entry upstream. This
 * writes the whole message in Arabic so nothing can fall back to English.
 */
class CustomerResetPassword extends BaseNotification
{
    public function toMail($notifiable): MailMessage
    {
        $store = Setting::get('store_name', config('app.name'));
        $minutes = config('auth.passwords.users.expire', 60);
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور — '.$store)
            ->greeting('أهلاً '.$notifiable->name.'،')
            ->line('وصلنا طلب لإعادة تعيين كلمة المرور الخاصة بحسابك في '.$store.'.')
            ->action('إعادة تعيين كلمة المرور', url($url))
            ->line("صلاحية هذا الرابط {$minutes} دقيقة من وقت إرساله.")
            ->line('إذا لم تطلب إعادة التعيين، فلا حاجة لأي إجراء — يمكنك تجاهل هذه الرسالة بأمان.')
            ->salutation('مع تحياتنا، فريق '.$store);
    }
}
