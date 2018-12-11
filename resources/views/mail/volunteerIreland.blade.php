@component('mail::layout')
    {{-- Header --}}
    @slot('header')
      @component('mail::header', ['url' => config('app.url')])
          Letters 1916-1923
      @endcomponent
    @endslot
{{-- Body --}}

<p>This is an automated email from Letters of Ireland 1916-1923.</p>
<p>A new user has registered as a transcriber on our website stating they were referred by Volunteer Ireland. Note that registration means they have created a new user account, it is not an indication that the account has been activated or used.</p>
<p>Due to our license agreement we cannot track users activity or share any of their data with a third party. It is on the users to screenshot and send on their activity from their account information.</p>

Kind regards,<br>Letters 1916-1923
{{ config('app.name') }}
    @slot('footer')
    @component('mail::footer')
        © {{ date('Y') }} Letters 1916-1923. All rights reserved.
    @endcomponent
  @endslot
@endcomponent
