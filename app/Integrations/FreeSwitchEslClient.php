<?php

declare(strict_types=1);

namespace App\Integrations;

use RuntimeException;

final class FreeSwitchEslClient
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $password,
    ) {}

    /** @return array{uuid:string,status:string,response:string} */
    public function originate(string $toNumber, string $callerId, string $audioUrl, int $timeoutSeconds = 30): array
    {
        $socket = fsockopen($this->host, (string) $this->port, $errno, $errstr, 5);
        if (!is_resource($socket)) {
            throw new RuntimeException("Unable to connect to FreeSWITCH ESL: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeoutSeconds);

        fwrite($socket, "auth {$this->password}\n\n");
        $authResponse = stream_get_contents($socket, 1024) ?: '';

        $callUuid = (string) \Illuminate\Support\Str::uuid();
        $command = sprintf(
            "bgapi originate {origination_uuid=%s,origination_caller_id_number=%s}%s &playback(%s)\n\n",
            $callUuid,
            $callerId,
            $toNumber,
            $audioUrl
        );

        fwrite($socket, $command);
        $response = stream_get_contents($socket, 4096) ?: '';
        fclose($socket);

        return [
            'uuid' => $callUuid,
            'status' => str_contains($response, '+OK') ? 'answered' : 'failed',
            'response' => trim($authResponse . '\n' . $response),
        ];
    }
}
