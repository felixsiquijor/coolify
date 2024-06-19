<?php

namespace App\Notifications\Channels;

use App\Jobs\SendMessageToSlackJob;
use Illuminate\Notifications\Notification;

class SlackChannel
{
    /**
     * Send the given notification.
     */
    public function send(SendsSlack $notifiable, Notification $notification): void
    {
        $message = $notification->toSlack($notifiable);
        $webhookUrl = $notifiable->routeNotificationForSlack();
        if (! $webhookUrl) {
            return;
        }
        dispatch(new SendMessageToSlackJob($message, $webhookUrl));
    }
}