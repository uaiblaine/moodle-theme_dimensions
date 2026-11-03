# Offer the public calendar to a logged-in reader's hero too.
s/        if \(!\$this->result->anonymous \|\| !visitor::enabled\(\)/        if (!visitor::enabled()/;
