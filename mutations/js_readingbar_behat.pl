# Move the bar on the Behat site: a scenario that scrolled finds the global bar's controls out of reach.
s/const live = !config\.behat;/const live = Boolean(config);/;
