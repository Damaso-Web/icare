<?php

namespace App\Support;

// The rule for notes that staff type in (session notes, follow-up notes,
// previous interventions, parent conference slips): letters, numbers, spaces
// and line breaks, and the punctuation a sentence needs. Symbols such as
// < > { } [ ] @ # $ % ^ * = + | \ ~ ` " _ are not accepted.
//
// resources/js/utils/validators.js has the same rule (plainText) so the page
// drops those characters as they are typed; this is the check on the server.
class PlainText
{
    public const REGEX = '/^[a-zA-Z0-9\x{00C0}-\x{024F}\s.,\'\-()&:;?!\/]*$/u';

    public const MESSAGE = 'Only letters, numbers and basic punctuation (. , \' - ( ) & : ; ? ! /) are allowed.';
}
