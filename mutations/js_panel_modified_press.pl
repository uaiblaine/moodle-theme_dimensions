# Intercept a press with a modifier on a poster that stays a link: ctrl-click opens the panel in place
# instead of the area's panel in a new tab.
s/export const elsewhere = \(event: MouseEvent\): boolean => event\.defaultPrevented \|\| event\.button !== 0\n    \|\| event\.ctrlKey \|\| event\.metaKey \|\| event\.shiftKey \|\| event\.altKey;/export const elsewhere = (event: MouseEvent): boolean => event.defaultPrevented;/;
