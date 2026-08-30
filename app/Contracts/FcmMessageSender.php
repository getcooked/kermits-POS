<?php

namespace App\Contracts;

use App\Support\FcmSendResult;

interface FcmMessageSender
{
    /**
     * @param  array<string, string>  $data
     */
    public function send(string $installationId, array $data): FcmSendResult;
}
