# Read a bare address as naming the state, so the saved state never applies.
s/        if \(!\$named\) \{\n            return null;/        if (false) {\n            return null;/;
