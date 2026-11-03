# Drop the contrast floor: every brand colour is the ground as it is, white text on amber included.
s/\@if contrast-ratio\(tint-color\(\$base, 12%\), \$white\) < \$dim-landing-floor \{/\@if false {/;
