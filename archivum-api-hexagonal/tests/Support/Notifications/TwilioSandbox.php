<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

final class TwilioSandbox
{
    public const string SID = 'AC00000000000000000000000000000000';

    public const string TOKEN = 'fake-test-token';

    public const string RECIPIENT = '+50688880001';

    public const string BUSINESS_FROM = '+15005550006';

    public const string ORDER_CONTENT_SID = 'HX11111111111111111111111111111111';

    public const string APPOINTMENT_CONTENT_SID = 'HX22222222222222222222222222222222';

    public static function configure(): void
    {
        config([
            'archivum.whatsapp.sid' => self::SID,
            'archivum.whatsapp.token' => self::TOKEN,
            'archivum.whatsapp.from' => '+14155238886',
            'archivum.whatsapp.test_numbers' => self::RECIPIENT,
            'archivum.whatsapp.mode' => '',
            'archivum.whatsapp.order_content_sid' => '',
            'archivum.whatsapp.appointment_content_sid' => '',
        ]);
    }

    public static function configureBusiness(string $orderContentSid = '', string $appointmentContentSid = ''): void
    {
        self::configure();

        config([
            'archivum.whatsapp.mode' => 'business',
            'archivum.whatsapp.from' => self::BUSINESS_FROM,
            'archivum.whatsapp.order_content_sid' => $orderContentSid,
            'archivum.whatsapp.appointment_content_sid' => $appointmentContentSid,
        ]);
    }
}
