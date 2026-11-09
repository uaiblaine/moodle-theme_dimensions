# Ease the receding surface under reduced motion too: the card's own rule takes the preview's transition.
s/(--dim-card-film: transparent;\n(?:[^\n]*\n)*?\s*transition: box-shadow var\(--dim-card-transition\) var\(--dim-ease\));/$1, background-color var(--dim-motion) var(--dim-ease);/;
