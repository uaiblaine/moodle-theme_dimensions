# Let core's dark-mode underline reach the panel heading's category link at rest.
s/(    #region-main \.dim-panel__title a\[href\]:not\(\.btn\) \{\n        text-decoration: )none;/${1}underline;/;
