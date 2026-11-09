# Let core's dark-mode underline reach a card's title at rest again.
s/(    #region-main \.dim-card \.dim-card__title a\[href\]:not\(\.btn\) \{\n        text-decoration: )none;/${1}underline;/;
