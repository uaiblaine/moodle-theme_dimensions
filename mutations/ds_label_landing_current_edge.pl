# Put the current step label's light edge on its own fill: the label's edge vanishes.
s/(--dim-landing-ink-edge: #\{)mix\(\$white, \$dim-landing-base, 30%\)\}/${1}\$dim-landing-base}/;
