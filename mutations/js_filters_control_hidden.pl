# Never write the Filters control hidden on the server line.
s/control\.hidden = !shown;/control.hidden = false;/;
