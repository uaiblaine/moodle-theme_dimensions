# Skip the NFC normalisation: a letter stored with a combining accent loses the accent in the masked value.
s/\\Normalizer::normalize\(\$value, \\Normalizer::FORM_C\)/\$value/;
