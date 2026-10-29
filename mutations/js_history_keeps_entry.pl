# The panel's address pushed: every opening of an area becomes a step of Back.
s/    window\.history\.replaceState\(window\.history\.state, '', url\);/    window.history.pushState(window.history.state, '', url);/;
