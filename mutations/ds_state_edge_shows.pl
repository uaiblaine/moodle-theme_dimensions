# Put one state's light edge back on its own ground: a status label drawing its 1px edge shows none.
s/--dimensions-state-open-edge: #\{mix\(\$dim-ds-green-800, \$dim-ds-green-050, 30%\)\};/--dimensions-state-open-edge: var(--dimensions-state-open-bg);/;
