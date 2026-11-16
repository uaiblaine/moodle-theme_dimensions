# Show the bar's tooltip at once, without the 100ms of rest.
s/(&:focus-visible::before \{\n            opacity: 1;\n            )transition-delay: 100ms;/${1}transition-delay: 0s;/;
