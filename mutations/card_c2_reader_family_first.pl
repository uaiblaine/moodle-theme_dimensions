# Read the card's surface from Bootstrap alone: the family token no longer reaches the theme's own card.
s/--dim-card-surface: var\(--dimensions-card-surface, var\(--bs-body-bg, #fff\)\);/--dim-card-surface: var(--bs-body-bg, #fff);/;
