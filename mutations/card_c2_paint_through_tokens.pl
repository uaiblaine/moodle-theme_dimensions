# Paint the kind pill's ground with a literal again instead of the chip token.
s/(    \.dim-card__kind \{.*?)background: var\(--dim-card-chip-bg\);/$1background: rgba(255, 255, 255, .92);/s;
