@php
    // Renders nothing when the site has no AdSense client or no slot ID
    // configured for this position - an <ins> tag with no real ad unit
    // behind it is exactly the kind of "ad code without ads" AdSense flags.
    $site = current_site();
    $slotId = $site->adsenseSlot($position ?? 'header');
@endphp
@if($site->adsense_client_id && $slotId)
<div class="ad-slot ad-slot--{{ $position }}" data-ad-position="{{ $position }}">
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="{{ $site->adsense_client_id }}"
         data-ad-slot="{{ $slotId }}"
         data-ad-format="auto"
         data-full-width-responsive="true"></ins>
    <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
</div>
@endif
