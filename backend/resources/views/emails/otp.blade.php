@component('mail::message')
# Password Reset Code

Hello!

You are receiving this email because we received a password reset request for your account.

Your password reset code is:

@component('mail::panel')
## {{ $code }}
@endcomponent

This code will expire in **10 minutes** at {{ $expiresAt->format('Y-m-d H:i:s') }}.

If you did not request a password reset, no further action is required.

Thanks,<br>
{{ config('app.name') }}
@endcomponent