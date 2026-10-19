# Leave the hidden panels reachable: the keyboard and a screen reader walk into panels nobody can see.
s/candidate\.toggleAttribute\('inert', hidden\);/candidate.toggleAttribute('inert', false);/;
