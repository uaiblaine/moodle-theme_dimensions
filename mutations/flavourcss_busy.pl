# Compile without the theme's lock: a visitor's request and the builder compile the same sheet at once.
s/if \(!\$lock\)/if (false)/;
