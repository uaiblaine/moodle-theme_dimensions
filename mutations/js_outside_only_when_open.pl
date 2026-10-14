# Apply on every press outside the control, open or not: the pill of the count row is outside it,
# and repainting the pill between its pointerdown and its click takes the click away from it.
s/if \(isOpen\(\) && event\.target instanceof Node/if (event.target instanceof Node/;
