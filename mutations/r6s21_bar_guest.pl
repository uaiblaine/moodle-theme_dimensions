# Never link the bar to guest access standing as the primary action.
s/\} else if \(\$guest !== null && \(\$exported->isvisitor \|\| \$exported->isopen\)\) \{/} else if (false) {/
