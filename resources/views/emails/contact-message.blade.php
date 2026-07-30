<x-mail::message>
# New message from the website contact form

**Name:** {{ $name }}

@if ($email)
**Email:** {{ $email }}
@endif

@if ($phone)
**Phone:** {{ $phone }}
@endif

**Message:**

{{ $message }}
</x-mail::message>
