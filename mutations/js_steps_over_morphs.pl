# Grow the search over the line at every fold.
s/export const morphs = \(fold: Fold \| null\): boolean => fold !== null && fold\.over;/export const morphs = (fold: Fold | null): boolean => fold !== null;/;
