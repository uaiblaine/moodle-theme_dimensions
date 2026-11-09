# Lift the card on hover with a transform, which cuts its shadow in a rail and pushes its neighbours.
s/(    \.dim-card--link:hover \{\n        box-shadow: var\(--dim-card-shadow-hover\);)/$1\n        transform: translateY(-2px);/;
