# The line spells an event of its own beside the shared names: two spellings that can drift apart.
s/^export const EVENTS = LINE;$/export const EVENTS = {...LINE, mounted: 'dim-line:mounted'};/m;
