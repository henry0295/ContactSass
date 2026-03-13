<?php

declare(strict_types=1);

namespace App\Messaging\Enums;

enum MessageChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Voice = 'voice';

    public function queueName(): string
    {
        return match ($this) {
            self::Email => 'email-send',
            self::Sms => 'sms-send',
            self::Voice => 'voice-send',
        };
    }
}
