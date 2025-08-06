{{-- Ładowane tylko gdy w JS padniemy poniżej progu --}}
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async></script>
<div class="cf-turnstile" data-sitekey="{{ config('invis.turnstile.sitekey') }}"
     data-callback="_tsOK"></div>
