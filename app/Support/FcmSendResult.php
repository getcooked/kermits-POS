<?php

namespace App\Support;

enum FcmSendResult
{
    case Sent;
    case InvalidInstallation;
    case Disabled;
}
