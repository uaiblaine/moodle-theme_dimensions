# Put the course notice's card on a radius the scale does not have.
s/(\n    border-radius: )0 0 (var\(--dim-ds-radius-md, 8px\) var\(--dim-ds-radius-md, 8px\);)/${1}10px 0 ${2}/;
