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

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.');

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
    Log::shouldReceive('info')->once()->with('WhatsApp notice skipped: Twilio Sandbox is not configured.');

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.');

    Http::assertNothingSent();
})->with([
    'missing SID' => ['sid', ''],
    'missing token' => ['token', ''],
    'missing sender' => ['from', ''],
    'missing test recipients' => ['test_numbers', ''],
    'sender outside the sandbox' => ['from', 'whatsapp:+50688880002'],
    'unknown mode' => ['mode', 'production'],
]);

it('only sends to valid numbers listed for testing', function (string $phone, string $allowed) {
    config(['archivum.whatsapp.test_numbers' => $allowed]);
    Log::shouldReceive('info')->once()->with('WhatsApp notice skipped: recipient is outside the sandbox test list.');

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, $phone, 'Recibimos tu pedido.');

    Http::assertNothingSent();
})->with([
    'not on the list' => ['+50688880002', TwilioSandbox::RECIPIENT],
    'not in E.164' => ['8888-0002', '8888-0002'],
]);

it('logs a rejected notice without persisting the Twilio response body', function () {
    Http::fake([TWILIO_TEST_ENDPOINT => Http::response(['message' => 'Twilio response'], 400)]);
    Log::shouldReceive('warning')->once()->with('Twilio did not accept the WhatsApp notice.', ['status' => 400]);

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.');

    Http::assertSentCount(1);
});

it('does not resend a notice on an ambiguous connection failure', function () {
    Http::fake([TWILIO_TEST_ENDPOINT => Http::failedConnection()]);
    Log::shouldReceive('warning')->once()->with('Twilio could not be reached.');

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.');

    Http::assertSentCount(1);
});

it('sends the configured template for each Trial event without message text or variables', function (CustomerNotice $notice, string $contentSid) {
    TwilioSandbox::configureTrial(TwilioSandbox::ORDER_CONTENT_SID, TwilioSandbox::APPOINTMENT_CONTENT_SID);
    Http::fake([TWILIO_TEST_ENDPOINT => Http::response([], 201)]);

    app(WhatsAppSender::class)->send($notice, TwilioSandbox::RECIPIENT, 'Este texto no viaja en Trial.');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === TWILIO_TEST_ENDPOINT
        && $request->data() === [
            'From' => 'whatsapp:'.TwilioSandbox::TRIAL_FROM,
            'To' => 'whatsapp:'.TwilioSandbox::RECIPIENT,
            'ContentSid' => $contentSid,
        ]);
})->with([
    'order placed' => [CustomerNotice::OrderPlaced, TwilioSandbox::ORDER_CONTENT_SID],
    'appointment booked' => [CustomerNotice::AppointmentBooked, TwilioSandbox::APPOINTMENT_CONTENT_SID],
]);

it('omits a Trial event with a missing or invalid template', function (string $contentSid) {
    TwilioSandbox::configureTrial(TwilioSandbox::ORDER_CONTENT_SID, $contentSid);
    Log::shouldReceive('info')->once()->with('WhatsApp notice skipped: no Trial template for this notice.', ['notice_type' => 'appointment_booked']);

    app(WhatsAppSender::class)->send(CustomerNotice::AppointmentBooked, TwilioSandbox::RECIPIENT, 'Reservamos tu cita.');

    Http::assertNothingSent();
})->with(['', 'invalid-content-sid']);

it('disables the transport when Trial has no valid templates', function () {
    TwilioSandbox::configureTrial();
    Log::shouldReceive('info')->once()->with('WhatsApp notice skipped: Twilio Sandbox is not configured.');

    app(WhatsAppSender::class)->send(CustomerNotice::OrderPlaced, TwilioSandbox::RECIPIENT, 'Recibimos tu pedido.');

    Http::assertNothingSent();
});
