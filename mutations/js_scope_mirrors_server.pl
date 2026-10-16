# Drop a value from the client's scope list: the client then paints it where the server never draws it.
s/^export const SCOPE = \['', 'enrolled', 'open', 'scheduled'\];$/export const SCOPE = ['', 'enrolled', 'open'];/m;
