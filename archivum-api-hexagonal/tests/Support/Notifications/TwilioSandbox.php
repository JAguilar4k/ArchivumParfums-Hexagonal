<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

final class TwilioSandbox
{
    public const string SID = 'AC00000000000000000000000000000000';

    public const string TOKEN = 'fake-test-token';

    public const string RECIPIENT = '+50688880001';

    public static function configure(): void
    {
        config([
            'archivum.whatsapp.sid' => self::SID,
            'archivum.whatsapp.token' => self::TOKEN,
            'archivum.whatsapp.from' => '+14155238886',
            'archivum.whatsapp.test_numbers' => self::RECIPIENT,
        ]);
    }
}
