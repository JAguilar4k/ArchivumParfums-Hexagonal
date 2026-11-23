<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Notifications\CustomerNotices\Domain\Contracts\WhatsAppSender;
use Src\Shared\Domain\ValueObjects\CustomerNotice;
use Tests\Support\Notifications\TwilioSandbox;

const TWILIO_TEST_ENDPOINT = 'https://api.twilio.com/2010-04-01/Accounts/'.TwilioSandbox::SID.'/Messages.json';

beforeEach(fn () => TwilioSandbox::configure());

it('delivers an authorized notice through the Twilio sandbox', function () {
    Http::fake([TWILIO_TEST_ENDPOINT => Http::response([], 201)]);

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.', []);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === TWILIO_TEST_ENDPOINT
        && $request->hasHeader('Authorization', 'Basic '.base64_encode(TwilioSandbox::SID.':'.TwilioSandbox::TOKEN))
        && $request->data() === [
            'From' => 'whatsapp:+14155238886',
            'To' => 'whatsapp:'.TwilioSandbox::RECIPIENT,
            'Body' => 'Recibimos tu pedido.',
        ]);
});

it('logs a skipped notice without sending when configuration cannot use the sandbox', function (string $setting, string $value) {
    config(['archivum.whatsapp.'.$setting => $value]);
    Log::shouldReceive('info')->once()->with('WhatsApp notice skipped: Twilio is not configured.');

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.', []);

    Http::assertNothingSent();
})->with([
    'missing SID' => ['sid', ''],
    'missing token' => ['token', ''],
    'missing sender' => ['from', ''],
    'missing test recipients' => ['test_numbers', ''],
    'sender outside the sandbox' => ['from', 'whatsapp:+50688880002'],
    'unknown mode' => ['mode', 'trial'],
]);

it('only sends to numbers listed for the Sandbox', function (string $phone, string $allowed) {
    config(['archivum.whatsapp.test_numbers' => $allowed]);
    Log::shouldReceive('info')->once()->with('WhatsApp notice skipped: recipient is outside the sandbox test list.');

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, $phone, 'Recibimos tu pedido.', []);

    Http::assertNothingSent();
})->with([
    'not on the list' => ['+50688880002', TwilioSandbox::RECIPIENT],
]);

it('logs a rejected notice without persisting the Twilio response body', function () {
    Http::fake([TWILIO_TEST_ENDPOINT => Http::response(['message' => 'Twilio response'], 400)]);
    Log::shouldReceive('warning')->once()->with('Twilio did not accept the WhatsApp notice.', ['status' => 400]);

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.', []);

    Http::assertSentCount(1);
});

it('does not resend a notice on an ambiguous connection failure', function () {
    Http::fake([TWILIO_TEST_ENDPOINT => Http::failedConnection()]);
    Log::shouldReceive('warning')->once()->with('Twilio could not be reached.');

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.', []);

    Http::assertSentCount(1);
});

it('sends the approved Spanish template of each event from the business sender', function (CustomerNotice $notice, string $contentSid, array $variables, string $contentVariables) {
    TwilioSandbox::configureBusiness(TwilioSandbox::ORDER_CONTENT_SID, TwilioSandbox::APPOINTMENT_CONTENT_SID);
    Http::fake([TWILIO_TEST_ENDPOINT => Http::response([], 201)]);

    // A paid sender reaches any customer, not only the numbers listed for the Sandbox.
    app(WhatsAppSender::class)->send($notice, '+50688880002', 'El texto viaja en la plantilla.', $variables);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === TWILIO_TEST_ENDPOINT
        && $request->data() === [
            'From' => 'whatsapp:'.TwilioSandbox::BUSINESS_FROM,
            'To' => 'whatsapp:+50688880002',
            'ContentSid' => $contentSid,
            'ContentVariables' => $contentVariables,
        ]);
})->with([
    'order placed' => [CustomerNotice::OrderPlaced, TwilioSandbox::ORDER_CONTENT_SID, ['reference' => 'P-1'], '{"1":"P-1"}'],
    'appointment booked' => [CustomerNotice::AppointmentBooked, TwilioSandbox::APPOINTMENT_CONTENT_SID, ['starts_at' => '24/11/2026 09:00', 'reference' => 'C-1'], '{"1":"24/11/2026 09:00","2":"C-1"}'],
]);

it('omits a business event without an approved template', function (string $contentSid) {
    TwilioSandbox::configureBusiness(TwilioSandbox::ORDER_CONTENT_SID, $contentSid);
    Log::shouldReceive('info')->once()->with('WhatsApp notice skipped: no approved template for this notice.', ['notice_type' => 'appointment_booked']);

    app(WhatsAppSender::class)->send(CustomerNotice::AppointmentBooked, TwilioSandbox::RECIPIENT, 'Reservamos tu cita.', []);

    Http::assertNothingSent();
})->with(['', 'invalid-content-sid']);

it('disables the transport when the business sender is not usable', function (array $settings) {
    TwilioSandbox::configureBusiness(TwilioSandbox::ORDER_CONTENT_SID, TwilioSandbox::APPOINTMENT_CONTENT_SID);
    foreach ($settings as $setting => $value) {
        config(['archivum.whatsapp.'.$setting => $value]);
    }
    Log::shouldReceive('info')->once()->with('WhatsApp notice skipped: Twilio is not configured.');

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.', []);

    Http::assertNothingSent();
})->with([
    'no approved templates' => [['order_content_sid' => '', 'appointment_content_sid' => '']],
    'shared Sandbox sender' => [['from' => '+14155238886']],
    'sender outside E.164' => [['from' => '8888-0002']],
]);

it('skips a customer phone outside E.164 in every mode', function (bool $business) {
    if ($business) {
        TwilioSandbox::configureBusiness(TwilioSandbox::ORDER_CONTENT_SID, TwilioSandbox::APPOINTMENT_CONTENT_SID);
    }
    Log::shouldReceive('info')->once()->with('WhatsApp notice skipped: the customer phone is not in E.164.');

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, '8888-0002', 'Recibimos tu pedido.', ['reference' => 'P-1']);

    Http::assertNothingSent();
})->with(['sandbox' => false, 'business' => true]);
