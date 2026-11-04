# Ask for the next month at the cap with every day held in view, dropping the first month in view.
s/const full = near === 'end' && months\.length >= LOADED_CAP && first === 0;/const full = false;/;
