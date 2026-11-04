# Ask for the next month at the zoom's cap with every day held in view, dropping the first month in view.
s/const full = near === 'end' && months\.length >= capOf\(zoom\) && first === 0;/const full = false;/;
