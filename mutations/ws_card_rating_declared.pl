# Forget the card's rating flag in the returns: the allowlist drops it in silence for every client-drawn card.
s/\n\s*'hasrating' => new external_value\(PARAM_BOOL, 'Whether the card carries a rating\.'\),//;
