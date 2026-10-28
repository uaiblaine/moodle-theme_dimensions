# Build the panel without the mount's props: the visitor's options never reach it.
s/optionsOf\(props \|\| \{\}\)/optionsOf({})/;
