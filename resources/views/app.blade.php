<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iCARE · BSU Office of Student Services</title>
    <meta name="description" content="iCARE is the Integrated Case Management System of the Benguet State University Office of Student Services — referrals, appointments and student case records in one connected place.">
    <meta name="theme-color" content="#0e2d1e">
    <meta property="og:type" content="website">
    <meta property="og:title" content="iCARE · BSU Office of Student Services">
    <meta property="og:description" content="Referrals, appointments and student case records of the BSU Office of Student Services, in one connected place.">
    <link rel="icon" type="image/png" href="/icare-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!--
        B644 (10/30/2026, ZAP finding - "Subresource Integrity Attribute
        Missing"): intentionally no integrity="" here. Google's css2
        endpoint returns a DIFFERENT response body per requesting
        User-Agent (different @font-face formats/URLs for Chrome vs
        Safari vs older browsers), so there is no single stable hash to
        pin - adding one would break the font for whichever browsers
        don't match it. The CSP's style-src/font-src above already pin
        this to only fonts.googleapis.com/fonts.gstatic.com, which is the
        narrowing SRI would otherwise provide. Self-hosting the font
        files would allow real SRI, but is a separate, larger change.
    -->
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Instrument+Serif:ital@0;1&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div id="app"></div>
    @vite(['resources/js/app.js'])
</body>
</html>