<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminMailController extends Controller
{
    public function show()
    {
        $mailer = config('mail.default');
        $smtp = config('mail.mailers.smtp');

        return [
            'mailer' => $mailer,
            'sends_real_email' => !in_array($mailer, ['log', 'array'], true),
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'encryption' => $smtp['encryption'],
            'username' => $smtp['username'],
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'app_url' => config('app.url'),
        ];
    }

    public function test(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        try {
            Mail::raw(
                'This is a test email from ' . config('app.url') . ' sent at ' . now()->toDateTimeString() . '. If you received it, outgoing email works.',
                fn ($m) => $m->to($data['email'])->subject('Test email')
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        $message = config('mail.default') === 'log'
            ? 'Mailer is set to "log": the message was only written to the log file, not sent.'
            : 'Accepted by the mail server. Check the inbox and spam folder.';

        return response()->json(['success' => true, 'message' => $message]);
    }
}
