# Take the earliest of an open trail's step ends for its line, not the latest.
s/\(latest === null \|\| day > latest\)/(latest === null || day < latest)/;
