# Move the region main settings menu into the bar with the header actions.
s/if \(action\.querySelector\(SELECTORS\.settingsmenu\)\) \{\n([ \t]*)return;\n([ \t]*)\}\n//;
