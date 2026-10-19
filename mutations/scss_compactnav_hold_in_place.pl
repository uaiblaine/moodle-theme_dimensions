# Hold the navigation with display: none: core's navigation measures nothing and the module reads a fit.
s/(&\.jsenabled:not\(\.dimensions-navcompact-measured\) \.primary-navigation \{\n\s*)visibility: hidden;/$1display: none;/;
