# Draw the month's toolbar in the week view: its caption and its months' links.
s/        if \(\$this->week === ''\) \{\n/        if (\$this->week === '' || true) {\n/;
