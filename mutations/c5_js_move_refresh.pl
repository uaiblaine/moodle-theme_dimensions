# Leave the window stale after a move.
s/\.then\(\(\) => refresh\('updated'\)\)/.then(() => undefined)/;
