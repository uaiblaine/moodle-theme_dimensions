# Draw two months from a narrower width than the one the stylesheet widens the popover at: between
# the two widths both months are squeezed into the one-month popover.
s/^const WIDE = '\(min-width: 768px\)';$/const WIDE = '(min-width: 576px)';/m;
