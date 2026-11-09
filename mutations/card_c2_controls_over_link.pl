# Leave the card's controls under the stretched link: its ::after swallows every press on them.
s/(    \.dim-card__cta,\n    \.dim-card__summary,\n    \.dim-card__star \{\n        position: relative;\n)        z-index: 2;\n/$1/;
