<?php

namespace App\Enums;

enum MailAccountStatus: string
{
    case Active = 'active';
    case NeedsReconnect = 'needs_reconnect';
    case Disabled = 'disabled';
}
