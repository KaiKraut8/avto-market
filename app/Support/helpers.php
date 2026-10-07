<?php

// Small view helpers for the company details (config/company.php)

use Illuminate\Support\Carbon;

if (! function_exists('company_phone_href')) {
    function company_phone_href(): string
    {
        return 'tel:'.preg_replace('/[^+0-9]/', '', config('company.phone'));
    }
}

if (! function_exists('company_mailto')) {
    // mailto: link with our address as the recipient and a subject filled in
    function company_mailto(?string $subject = null): string
    {
        return 'mailto:'.config('company.email').'?subject='.rawurlencode($subject ?? __('Question about a car'));
    }
}

if (! function_exists('company_gmail_url')) {
    // the same message, opened in Gmail in the browser (for people without a mail app)
    function company_gmail_url(?string $subject = null): string
    {
        return 'https://mail.google.com/mail/?view=cm&fs=1&to='.rawurlencode(config('company.email'))
            .'&su='.rawurlencode($subject ?? __('Question about a car'));
    }
}

if (! function_exists('company_now')) {
    function company_now(): Carbon
    {
        return now(config('company.timezone'));
    }
}

if (! function_exists('opening_hours_summary')) {
    // "Mon–Fri 08:00–18:00, Sat 09:00–13:00" in the current language
    function opening_hours_summary(): string
    {
        $hours = config('company.opening_hours');
        $groups = [];
        foreach ($hours as $day => $slot) {
            if (! $slot) {
                continue;
            }
            $key = implode('–', $slot);
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['key'] === $key && $groups[$last]['to'] === $day - 1) {
                $groups[$last]['to'] = $day;
            } else {
                $groups[] = ['key' => $key, 'from' => $day, 'to' => $day];
            }
        }
        $name = fn (int $iso) => Carbon::now()->startOfWeek()->addDays($iso - 1)->translatedFormat('D');

        return collect($groups)->map(fn ($g) => ($g['from'] === $g['to'] ? $name($g['from']) : $name($g['from']).'–'.$name($g['to'])).' '.$g['key'])->implode(', ');
    }
}
