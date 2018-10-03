<?php

namespace App\Notifications;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class UserRegisteredSuccessfully extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $user = $this->user;

        exec('docker run --rm -v $echo "Message Body.." | mutt -s "Subject mail test" -a "/tmp/hello.txt" -- Stavros.Angelis@mu.ie');
        /*Mail::send('emails.reminder', ['user' => $user], function ($m) use ($user) {
            $m->from('stavrosangelis@gmail.com', 'Letters 1916-1923');

            $m->to($user->email, $user->name)->subject('Welcome to Letters 1916-1923');
        });

        return (new MailMessage)
                ->from('stavrosangelis@gmail.com')
                ->subject('Successfully created new account')
                ->greeting(sprintf('Hello %s', $user->name))
                ->line('You have successfully registered to Letters 1916-1923. To activate your account please follow the link provided in this email.')
                ->action('Click Here', route('activate.user', $user->activation_code));*/
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
