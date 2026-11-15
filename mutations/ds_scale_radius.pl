# Put the course notice chip's dot on a radius the scale does not have.
s/(\.dim-coursenotice__chip::before \{[^}]*?\n    border-radius: )50%;/${1}10px;/;
