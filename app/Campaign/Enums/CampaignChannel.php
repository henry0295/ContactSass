<?php

declare(strict_types=1);

namespace App\Campaign\Enums;

enum CampaignChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Voice = 'voice';
}
